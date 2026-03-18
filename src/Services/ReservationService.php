<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Container;
use App\Core\ValidationException;
use PDO;

/**
 * Reservation business rules and persistence.
 *
 * This service contains the shared-room constraints for both resident booking
 * and staff oversight:
 * - only one active reservation may occupy a time slot
 * - bookings must be in the future and within allowed daily hours
 * - weekly/monthly user quotas are enforced using ISO weeks and calendar months
 * - staff edits still pass through overlap and quota validation
 */
final class ReservationService
{
    private PDO $db;
    private SettingsService $settings;
    private AuditService $audit;
    private NotificationService $notifications;
    private MailService $mail;
    private UserService $users;

    public function __construct()
    {
        $this->db = Container::get('db');
        $this->settings = new SettingsService();
        $this->audit = new AuditService();
        $this->notifications = new NotificationService();
        $this->mail = new MailService();
        $this->users = new UserService();
    }

    /**
     * Create a reservation inside a transaction and a MariaDB advisory lock.
     */
    public function create(array $user, string $startInput, string $endInput): void
    {
        [$start, $end, $rules] = $this->parseAndValidateDatetimes($user, $startInput, $endInput);

        $this->withReservationLock(function () use ($user, $start, $end, $rules): void {
            $this->assertNoOverlap($start, $end);
            $limitErrors = $this->limitErrors((int) $user['id'], $start, $end, $rules);
            if ($limitErrors !== []) {
                throw new ValidationException($limitErrors);
            }

            $insert = $this->db->prepare(
                'INSERT INTO reservations (
                    user_id, start_datetime, end_datetime, status, last_modified_by_user_id, created_at, updated_at
                 ) VALUES (
                    :user_id, :start_datetime, :end_datetime, :status, :last_modified_by_user_id, NOW(), NOW()
                 )'
            );
            $insert->execute([
                'user_id' => $user['id'],
                'start_datetime' => $start->format('Y-m-d H:i:s'),
                'end_datetime' => $end->format('Y-m-d H:i:s'),
                'status' => 'active',
                'last_modified_by_user_id' => $user['id'],
            ]);

            $reservationId = (string) $this->db->lastInsertId();
            $this->audit->log((int) $user['id'], 'reservation.created', 'reservation', $reservationId, [
                'start' => $start->format(DATE_ATOM),
                'end' => $end->format(DATE_ATOM),
            ]);
        });
    }

    public function cancel(int $reservationId, array $actor): void
    {
        $reservation = $this->findById($reservationId);
        if ($reservation === null || (int) $reservation['user_id'] !== (int) $actor['id']) {
            return;
        }

        $stmt = $this->db->prepare(
            'UPDATE reservations
             SET status = :status,
                 cancelled_at = NOW(),
                 cancelled_by_user_id = :cancelled_by_user_id,
                 last_modified_by_user_id = :last_modified_by_user_id,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'status' => 'cancelled',
            'cancelled_by_user_id' => $actor['id'],
            'last_modified_by_user_id' => $actor['id'],
            'id' => $reservationId,
        ]);

        $this->audit->log((int) $actor['id'], 'reservation.cancelled', 'reservation', (string) $reservationId);
    }

    /**
     * Staff cancellation triggers in-app and email notifications for the
     * affected resident.
     */
    public function cancelByStaff(int $reservationId, array $actor, string $locale): void
    {
        $reservation = $this->findDetailedById($reservationId);
        if ($reservation === null || $reservation['status'] !== 'active') {
            return;
        }

        $stmt = $this->db->prepare(
            'UPDATE reservations
             SET status = :status,
                 cancelled_at = NOW(),
                 cancelled_by_user_id = :cancelled_by_user_id,
                 last_modified_by_user_id = :last_modified_by_user_id,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'status' => 'cancelled',
            'cancelled_by_user_id' => $actor['id'],
            'last_modified_by_user_id' => $actor['id'],
            'id' => $reservationId,
        ]);

        $this->audit->log((int) $actor['id'], 'staff.reservation_cancelled', 'reservation', (string) $reservationId, [
            'user_id' => (int) $reservation['user_id'],
        ]);

        $recipient = $this->users->findById((int) $reservation['user_id']);
        if ($recipient !== null) {
            $title = $locale === 'nl' ? 'Reservering geannuleerd' : 'Reservation cancelled';
            $body = $locale === 'nl'
                ? 'Een beheerder of manager heeft je reservering geannuleerd.'
                : 'A manager or administrator cancelled your reservation.';
            $this->notifications->create((int) $recipient['id'], 'reservation_cancelled', $title, $body, '/reservations');
            $this->mail->notifyReservationCancelled($recipient, $actor, $reservation, $locale);
        }
    }

    /**
     * Update reservation times from the staff side while preserving all
     * collision and quota checks.
     */
    public function updateByStaff(int $reservationId, array $actor, string $startInput, string $endInput, string $locale): void
    {
        $reservation = $this->findById($reservationId);
        if ($reservation === null) {
            throw new \RuntimeException('Reservation not found.');
        }

        $owner = $this->users->findById((int) $reservation['user_id']);
        if ($owner === null) {
            throw new \RuntimeException('Reservation owner not found.');
        }

        [$start, $end, $rules] = $this->parseAndValidateDatetimes($owner, $startInput, $endInput);
        $previous = [
            'start_datetime' => $reservation['start_datetime'],
            'end_datetime' => $reservation['end_datetime'],
        ];

        $this->withReservationLock(function () use ($reservationId, $reservation, $owner, $actor, $start, $end, $rules, $locale, $previous): void {
            $this->assertNoOverlap($start, $end, $reservationId);
            $limitErrors = $this->limitErrors((int) $owner['id'], $start, $end, $rules, $reservationId);
            if ($limitErrors !== []) {
                throw new ValidationException($limitErrors);
            }

            $update = $this->db->prepare(
                'UPDATE reservations
                 SET start_datetime = :start_datetime,
                     end_datetime = :end_datetime,
                     last_modified_by_user_id = :last_modified_by_user_id,
                     updated_at = NOW()
                 WHERE id = :id'
            );
            $update->execute([
                'start_datetime' => $start->format('Y-m-d H:i:s'),
                'end_datetime' => $end->format('Y-m-d H:i:s'),
                'last_modified_by_user_id' => $actor['id'],
                'id' => $reservationId,
            ]);

            $this->audit->log((int) $actor['id'], 'staff.reservation_updated', 'reservation', (string) $reservationId, [
                'previous' => $previous,
                'new' => [
                    'start_datetime' => $start->format('Y-m-d H:i:s'),
                    'end_datetime' => $end->format('Y-m-d H:i:s'),
                ],
            ]);

            $recipient = $this->users->findById((int) $reservation['user_id']);
            if ($recipient !== null) {
                $title = $locale === 'nl' ? 'Reservering gewijzigd' : 'Reservation changed';
                $body = $locale === 'nl'
                    ? 'Een beheerder of manager heeft je reservering aangepast.'
                    : 'A manager or administrator updated your reservation.';
                $current = [
                    'start_datetime' => $start->format('Y-m-d H:i:s'),
                    'end_datetime' => $end->format('Y-m-d H:i:s'),
                ];
                $this->notifications->create((int) $recipient['id'], 'reservation_updated', $title, $body, '/reservations');
                $this->mail->notifyReservationChanged($recipient, $actor, $current, $previous, $locale);
            }
        });
    }

    /**
     * Return active reservations for the resident/public calendar view.
     *
     * Templates must display only first name + last initial for logged-in
     * residents. Guests should use publicCalendarMonth() instead.
     */
    public function calendarMonth(\DateTimeImmutable $month): array
    {
        $range = $this->monthRange($month);
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
            'start_datetime' => $range['start']->format('Y-m-d H:i:s'),
            'end_datetime' => $range['end']->format('Y-m-d H:i:s'),
        ]);

        return $stmt->fetchAll();
    }

    /**
     * Public availability feed with no resident identity fields.
     */
    public function publicCalendarMonth(\DateTimeImmutable $month): array
    {
        $range = $this->monthRange($month);
        $stmt = $this->db->prepare(
            'SELECT id, start_datetime, end_datetime, status
             FROM reservations
             WHERE status = :status
               AND start_datetime >= :start_datetime
               AND start_datetime < :end_datetime
             ORDER BY start_datetime ASC'
        );
        $stmt->execute([
            'status' => 'active',
            'start_datetime' => $range['start']->format('Y-m-d H:i:s'),
            'end_datetime' => $range['end']->format('Y-m-d H:i:s'),
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

    public function findDetailedById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT r.*, u.first_name, u.last_name, u.email, u.apartment_number
             FROM reservations r
             INNER JOIN users u ON u.id = r.user_id
             WHERE r.id = :id
             LIMIT 1'
        );
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

    private function parseAndValidateDatetimes(array $user, string $startInput, string $endInput): array
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

        return [$start, $end, $rules];
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
        if ($start->format('Y-m-d') !== $end->format('Y-m-d')) {
            $errors['end_datetime'] = 'validation.reservation_same_day';
        }
        if ((int) $start->format('H') < $rules['start_hour']) {
            $errors['start_datetime'] = 'validation.reservation_hours';
        }
        if ((int) $end->format('H') > $rules['end_hour'] || ((int) $end->format('H') === $rules['end_hour'] && (int) $end->format('i') > 0)) {
            $errors['end_datetime'] = 'validation.reservation_hours';
        }

        return $errors;
    }

    private function limitErrors(int $userId, \DateTimeImmutable $start, \DateTimeImmutable $end, array $rules, ?int $excludeReservationId = null): array
    {
        $durationHours = ($end->getTimestamp() - $start->getTimestamp()) / 3600;
        $weekStart = $start->setISODate((int) $start->format('o'), (int) $start->format('W'))->setTime(0, 0);
        $weekEnd = $weekStart->modify('+1 week');
        $monthStart = $start->modify('first day of this month')->setTime(0, 0);
        $monthEnd = $monthStart->modify('first day of next month');

        $weekHours = $this->sumHours($userId, $weekStart, $weekEnd, $excludeReservationId);
        $monthHours = $this->sumHours($userId, $monthStart, $monthEnd, $excludeReservationId);

        $errors = [];
        if (($weekHours + $durationHours) > $rules['max_week_hours']) {
            $errors['start_datetime'] = 'validation.weekly_limit';
        }
        if (($monthHours + $durationHours) > $rules['max_month_hours']) {
            $errors['start_datetime'] = 'validation.monthly_limit';
        }

        return $errors;
    }

    private function sumHours(int $userId, \DateTimeImmutable $rangeStart, \DateTimeImmutable $rangeEnd, ?int $excludeReservationId = null): float
    {
        $excludeSql = $excludeReservationId !== null ? 'AND id <> :exclude_id' : '';
        $stmt = $this->db->prepare(
            'SELECT COALESCE(SUM(TIMESTAMPDIFF(MINUTE, start_datetime, end_datetime)), 0) AS total_minutes
             FROM reservations
             WHERE user_id = :user_id
               AND status = :status
               AND start_datetime >= :range_start
               AND start_datetime < :range_end
               ' . $excludeSql
        );

        $params = [
            'user_id' => $userId,
            'status' => 'active',
            'range_start' => $rangeStart->format('Y-m-d H:i:s'),
            'range_end' => $rangeEnd->format('Y-m-d H:i:s'),
        ];
        if ($excludeReservationId !== null) {
            $params['exclude_id'] = $excludeReservationId;
        }
        $stmt->execute($params);

        return ((int) $stmt->fetchColumn()) / 60;
    }

    private function assertNoOverlap(\DateTimeImmutable $start, \DateTimeImmutable $end, ?int $excludeReservationId = null): void
    {
        $excludeSql = $excludeReservationId !== null ? 'AND id <> :exclude_id' : '';
        $overlapStmt = $this->db->prepare(
            'SELECT id FROM reservations
             WHERE status = :status
               AND start_datetime < :end_datetime
               AND end_datetime > :start_datetime
               ' . $excludeSql . '
             LIMIT 1
             FOR UPDATE'
        );

        $params = [
            'status' => 'active',
            'start_datetime' => $start->format('Y-m-d H:i:s'),
            'end_datetime' => $end->format('Y-m-d H:i:s'),
        ];
        if ($excludeReservationId !== null) {
            $params['exclude_id'] = $excludeReservationId;
        }
        $overlapStmt->execute($params);

        if ($overlapStmt->fetch()) {
            throw new ValidationException(['start_datetime' => 'validation.reservation_overlap']);
        }
    }

    private function withReservationLock(callable $callback): void
    {
        $lockKey = 'shared_living_room_reservation';
        $this->db->beginTransaction();

        try {
            $lockStmt = $this->db->prepare('SELECT GET_LOCK(:lock_key, 10)');
            $lockStmt->execute(['lock_key' => $lockKey]);
            if ((int) $lockStmt->fetchColumn() !== 1) {
                throw new ValidationException(['general' => 'validation.reservation_busy']);
            }

            $callback();
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

    private function monthRange(\DateTimeImmutable $month): array
    {
        $start = $month->modify('first day of this month')->setTime(0, 0);
        $end = $start->modify('first day of next month');

        return ['start' => $start, 'end' => $end];
    }
}
