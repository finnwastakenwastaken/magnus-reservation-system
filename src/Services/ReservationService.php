<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Container;
use App\Core\ValidationException;
use PDO;

/**
 * Reservation business rules and persistence.
 *
 * This service contains the most important shared-room constraints:
 * - only one active reservation may occupy a time slot
 * - bookings must be in the future and within allowed daily hours
 * - weekly/monthly user quotas are enforced using ISO weeks and calendar months
 */
final class ReservationService
{
    private PDO $db;
    private SettingsService $settings;
    private AuditService $audit;

    public function __construct()
    {
        $this->db = Container::get('db');
        $this->settings = new SettingsService();
        $this->audit = new AuditService();
    }

    /**
     * Create a reservation inside a transaction and a MariaDB advisory lock.
     *
     * The advisory lock serializes competing booking attempts for the shared
     * room. The transaction then re-checks overlap with FOR UPDATE so two users
     * cannot book the same slot concurrently.
     */
    public function create(array $user, string $startInput, string $endInput): void
    {
        $rules = $this->settings->bookingRules();
        $timezone = new \DateTimeZone($rules['timezone']);
        try {
            $start = new \DateTimeImmutable($startInput, $timezone);
            $end = new \DateTimeImmutable($endInput, $timezone);
        } catch (\Exception) {
            throw new ValidationException(['start_datetime' => 'validation.reservation_future']);
        }

        $errors = $this->validate($user, $start, $end, $rules);
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $lockKey = 'shared_living_room_reservation';
        $this->db->beginTransaction();

        try {
            $lockStmt = $this->db->prepare('SELECT GET_LOCK(:lock_key, 10)');
            $lockStmt->execute(['lock_key' => $lockKey]);
            if ((int) $lockStmt->fetchColumn() !== 1) {
                throw new ValidationException(['general' => 'validation.reservation_busy']);
            }

            $overlapStmt = $this->db->prepare(
                'SELECT id FROM reservations
                 WHERE status = :status
                   AND start_datetime < :end_datetime
                   AND end_datetime > :start_datetime
                 LIMIT 1
                 FOR UPDATE'
            );
            $overlapStmt->execute([
                'status' => 'active',
                'start_datetime' => $start->format('Y-m-d H:i:s'),
                'end_datetime' => $end->format('Y-m-d H:i:s'),
            ]);
            if ($overlapStmt->fetch()) {
                throw new ValidationException(['start_datetime' => 'validation.reservation_overlap']);
            }

            $limitErrors = $this->limitErrors((int) $user['id'], $start, $end, $rules);
            if ($limitErrors !== []) {
                throw new ValidationException($limitErrors);
            }

            $insert = $this->db->prepare(
                'INSERT INTO reservations (user_id, start_datetime, end_datetime, status, created_at, updated_at)
                 VALUES (:user_id, :start_datetime, :end_datetime, :status, NOW(), NOW())'
            );
            $insert->execute([
                'user_id' => $user['id'],
                'start_datetime' => $start->format('Y-m-d H:i:s'),
                'end_datetime' => $end->format('Y-m-d H:i:s'),
                'status' => 'active',
            ]);

            $reservationId = (string) $this->db->lastInsertId();
            $this->audit->log((int) $user['id'], 'reservation.created', 'reservation', $reservationId, [
                'start' => $start->format(DATE_ATOM),
                'end' => $end->format(DATE_ATOM),
            ]);

            $this->db->commit();
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        } finally {
            $releaseStmt = $this->db->prepare('SELECT RELEASE_LOCK(:lock_key)');
            $releaseStmt->execute(['lock_key' => $lockKey]);
        }
    }

    public function cancel(int $reservationId, array $actor, bool $admin = false): void
    {
        $reservation = $this->findById($reservationId);
        if (!$reservation) {
            return;
        }
        if (!$admin && (int) $reservation['user_id'] !== (int) $actor['id']) {
            return;
        }

        $stmt = $this->db->prepare(
            'UPDATE reservations
             SET status = :status, cancelled_at = NOW(), cancelled_by_user_id = :cancelled_by_user_id, updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'status' => 'cancelled',
            'cancelled_by_user_id' => $actor['id'],
            'id' => $reservationId,
        ]);

        $this->audit->log((int) $actor['id'], 'reservation.cancelled', 'reservation', (string) $reservationId);
    }

    /**
     * Return active reservations for a given month for the public calendar view.
     *
     * Templates must display only first name + last initial for privacy.
     */
    public function calendarMonth(\DateTimeImmutable $month): array
    {
        $start = $month->modify('first day of this month')->setTime(0, 0);
        $end = $start->modify('first day of next month');

        $stmt = $this->db->prepare(
            'SELECT r.*, u.first_name, u.last_name
             FROM reservations r
             INNER JOIN users u ON u.id = r.user_id
             WHERE r.status = :status
               AND r.start_datetime >= :start_datetime
               AND r.start_datetime < :end_datetime
             ORDER BY r.start_datetime ASC'
        );
        $stmt->execute([
            'status' => 'active',
            'start_datetime' => $start->format('Y-m-d H:i:s'),
            'end_datetime' => $end->format('Y-m-d H:i:s'),
        ]);

        return $stmt->fetchAll();
    }

    public function paginatedAll(int $page, int $perPage): array
    {
        $offset = ($page - 1) * $perPage;
        $total = (int) $this->db->query('SELECT COUNT(*) FROM reservations')->fetchColumn();
        $stmt = $this->db->prepare(
            "SELECT r.*, u.first_name, u.last_name, u.email, u.apartment_number
             FROM reservations r
             INNER JOIN users u ON u.id = r.user_id
             ORDER BY r.start_datetime DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute();

        return ['items' => $stmt->fetchAll(), 'total' => $total];
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM reservations WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function userUpcoming(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM reservations
             WHERE user_id = :user_id AND status = :status AND start_datetime >= NOW()
             ORDER BY start_datetime ASC LIMIT 5'
        );
        $stmt->execute([
            'user_id' => $userId,
            'status' => 'active',
        ]);

        return $stmt->fetchAll();
    }

    private function validate(array $user, \DateTimeImmutable $start, \DateTimeImmutable $end, array $rules): array
    {
        $errors = [];
        $now = new \DateTimeImmutable('now', new \DateTimeZone($rules['timezone']));

        if ($start <= $now) {
            $errors['start_datetime'] = 'validation.reservation_future';
        }
        if ($end <= $start) {
            $errors['end_datetime'] = 'validation.reservation_duration_positive';
        }
        // Same-day reservations keep the allowed-hours rule simple and prevent
        // bookings from spanning midnight into otherwise forbidden hours.
        if ($start->format('Y-m-d') !== $end->format('Y-m-d')) {
            $errors['end_datetime'] = 'validation.reservation_same_day';
        }
        // Allowed hours are configured by admins. Start may not begin before
        // the opening hour, and end may not go beyond the closing boundary.
        if ((int) $start->format('H') < $rules['start_hour'] || ((int) $start->format('H') === $rules['end_hour'] && (int) $start->format('i') > 0)) {
            $errors['start_datetime'] = 'validation.reservation_hours';
        }
        if ((int) $end->format('H') > $rules['end_hour'] || ((int) $end->format('H') === $rules['end_hour'] && (int) $end->format('i') > 0)) {
            $errors['end_datetime'] = 'validation.reservation_hours';
        }
        if ((int) $start->format('H') < $rules['start_hour']) {
            $errors['start_datetime'] = 'validation.reservation_hours';
        }

        return $errors;
    }

    private function limitErrors(int $userId, \DateTimeImmutable $start, \DateTimeImmutable $end, array $rules): array
    {
        $durationHours = ($end->getTimestamp() - $start->getTimestamp()) / 3600;
        // Weekly quotas use ISO week boundaries, monthly quotas use calendar
        // month boundaries as documented in the README and requirements.
        $weekStart = $start->setISODate((int) $start->format('o'), (int) $start->format('W'))->setTime(0, 0);
        $weekEnd = $weekStart->modify('+1 week');
        $monthStart = $start->modify('first day of this month')->setTime(0, 0);
        $monthEnd = $monthStart->modify('first day of next month');

        $weekHours = $this->sumHours($userId, $weekStart, $weekEnd);
        $monthHours = $this->sumHours($userId, $monthStart, $monthEnd);

        $errors = [];
        if (($weekHours + $durationHours) > $rules['max_week_hours']) {
            $errors['start_datetime'] = 'validation.weekly_limit';
        }
        if (($monthHours + $durationHours) > $rules['max_month_hours']) {
            $errors['start_datetime'] = 'validation.monthly_limit';
        }

        return $errors;
    }

    private function sumHours(int $userId, \DateTimeImmutable $rangeStart, \DateTimeImmutable $rangeEnd): float
    {
        $stmt = $this->db->prepare(
            'SELECT COALESCE(SUM(TIMESTAMPDIFF(MINUTE, start_datetime, end_datetime)), 0) AS total_minutes
             FROM reservations
             WHERE user_id = :user_id
               AND status = :status
               AND start_datetime >= :range_start
               AND start_datetime < :range_end'
        );
        $stmt->execute([
            'user_id' => $userId,
            'status' => 'active',
            'range_start' => $rangeStart->format('Y-m-d H:i:s'),
            'range_end' => $rangeEnd->format('Y-m-d H:i:s'),
        ]);

        return ((int) $stmt->fetchColumn()) / 60;
    }
}
