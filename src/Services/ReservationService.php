<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Container;
use App\Core\ValidationException;
use App\Security\Permissions;
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
            $timeRange = date('d-m-Y H:i', strtotime($reservation['start_datetime'])) . ' - ' . date('H:i', strtotime($reservation['end_datetime']));
            $body = $locale === 'nl'
                ? "Een beheerder of manager heeft je reservering voor {$timeRange} geannuleerd."
                : "A manager or administrator cancelled your reservation for {$timeRange}.";
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
                $newRange = date('d-m-Y H:i', $start->getTimestamp()) . ' - ' . date('H:i', $end->getTimestamp());
                $oldRange = date('d-m-Y H:i', strtotime($previous['start_datetime'])) . ' - ' . date('H:i', strtotime($previous['end_datetime']));
                $body = $locale === 'nl'
                    ? "Een beheerder of manager heeft je reservering aangepast van {$oldRange} naar {$newRange}."
                    : "A manager or administrator changed your reservation from {$oldRange} to {$newRange}.";
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

    /**
     * Calendar-feed payload for authenticated users.
     *
     * The feed stays privacy-safe for normal residents while still giving them
     * enough context to understand availability. Their own events are marked so
     * the UI can offer cancellation actions without exposing more identity data
     * for other residents.
     */
    public function calendarFeed(array $viewer, \DateTimeImmutable $rangeStart, \DateTimeImmutable $rangeEnd): array
    {
        $translator = Container::get('translator');
        $canViewFullDetails = in_array(Permissions::RESERVATIONS_VIEW_ALL, (array) ($viewer['permission_codes'] ?? []), true)
            || (int) ($viewer['is_super_admin'] ?? 0) === 1;
        $stmt = $this->db->prepare(
            'SELECT r.id, r.user_id, r.start_datetime, r.end_datetime, r.status, u.first_name, u.last_name
             FROM reservations r
             INNER JOIN users u ON u.id = r.user_id
             WHERE r.status = :status
               AND r.start_datetime < :range_end
               AND r.end_datetime > :range_start
             ORDER BY r.start_datetime ASC'
        );
        $stmt->execute([
            'status' => 'active',
            'range_start' => $rangeStart->format('Y-m-d H:i:s'),
            'range_end' => $rangeEnd->format('Y-m-d H:i:s'),
        ]);

        return array_map(function (array $row) use ($viewer, $translator, $canViewFullDetails): array {
            $isOwn = (int) $row['user_id'] === (int) $viewer['id'];
            if ($isOwn) {
                $title = $translator->get('reservation.you');
            } elseif ($canViewFullDetails) {
                $title = trim($row['first_name'] . ' ' . $row['last_name']);
            } else {
                $title = $row['first_name'] . ' ' . strtoupper(substr((string) $row['last_name'], 0, 1)) . '.';
            }

            return [
                'id' => (string) $row['id'],
                'title' => $title,
                'start' => date(DATE_ATOM, strtotime($row['start_datetime'])),
                'end' => date(DATE_ATOM, strtotime($row['end_datetime'])),
                'classNames' => [$isOwn ? 'calendar-event-own' : 'calendar-event-reserved'],
                'extendedProps' => [
                    'canCancel' => $isOwn,
                    'reservationId' => (int) $row['id'],
                ],
            ];
        }, $stmt->fetchAll());
    }

    /**
     * Public availability feed with no user identity fields.
     */
    public function publicFeed(\DateTimeImmutable $rangeStart, \DateTimeImmutable $rangeEnd): array
    {
        $translator = Container::get('translator');
        $stmt = $this->db->prepare(
            'SELECT id, start_datetime, end_datetime
             FROM reservations
             WHERE status = :status
               AND start_datetime < :range_end
               AND end_datetime > :range_start
             ORDER BY start_datetime ASC'
        );
        $stmt->execute([
            'status' => 'active',
            'range_start' => $rangeStart->format('Y-m-d H:i:s'),
            'range_end' => $rangeEnd->format('Y-m-d H:i:s'),
        ]);

        return array_map(static function (array $row) use ($translator): array {
            return [
                'id' => (string) $row['id'],
                'title' => $translator->get('reservation.reserved'),
                'start' => date(DATE_ATOM, strtotime($row['start_datetime'])),
                'end' => date(DATE_ATOM, strtotime($row['end_datetime'])),
                'classNames' => ['calendar-event-reserved'],
            ];
        }, $stmt->fetchAll());
    }

    public function rules(): array
    {
        return $this->settings->bookingRules();
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
