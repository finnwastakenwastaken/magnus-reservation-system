<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Container;

/**
 * Outbound email adapter for Mailjet.
 *
 * Mail is configuration-driven and safely degrades to file logging in local
 * development so the rest of the app can function without external credentials.
 */
final class MailService
{
    private string $logFile;

    public function __construct()
    {
        $this->logFile = BASE_PATH . '/storage/logs/app.log';
        if (!is_dir(dirname($this->logFile))) {
            mkdir(dirname($this->logFile), 0775, true);
        }
    }

    public function notifyAdminNewSignup(array $user, string $locale): void
    {
        $subject = $locale === 'nl' ? 'Nieuwe aanmelding bewoner' : 'New resident signup';
        $body = $locale === 'nl'
            ? "Nieuwe gebruiker: {$user['first_name']} {$user['last_name']} ({$user['apartment_number']})"
            : "New user: {$user['first_name']} {$user['last_name']} ({$user['apartment_number']})";

        $this->send(Container::get('config')['app']['admin_email'], $subject, $body);
    }

    public function notifyUserNewMessage(array $recipient, array $sender, string $subjectLine, string $locale): void
    {
        $subject = $locale === 'nl' ? 'Nieuw bericht in de woonkamer-app' : 'New message in the living room app';
        $body = $locale === 'nl'
            ? "Je hebt een nieuw bericht ontvangen van {$sender['first_name']} {$sender['last_name']}.\nOnderwerp: {$subjectLine}"
            : "You received a new message from {$sender['first_name']} {$sender['last_name']}.\nSubject: {$subjectLine}";

        $this->send($recipient['email'], $subject, $body);
    }

    /**
     * Send confirmation for a requested email-address change.
     */
    public function sendEmailChangeConfirmation(array $user, string $newEmail, string $confirmationUrl, string $locale): void
    {
        $subject = $locale === 'nl' ? 'Bevestig je nieuwe e-mailadres' : 'Confirm your new email address';
        $body = $locale === 'nl'
            ? "Hallo {$user['first_name']},\nBevestig je nieuwe e-mailadres via: {$confirmationUrl}"
            : "Hello {$user['first_name']},\nConfirm your new email address here: {$confirmationUrl}";

        $this->send($newEmail, $subject, $body);
    }

    /**
     * Notify the current address that an email change was requested.
     */
    public function sendEmailChangeNotice(array $user, string $newEmail, string $locale): void
    {
        $subject = $locale === 'nl' ? 'Wijziging e-mailadres aangevraagd' : 'Email address change requested';
        $body = $locale === 'nl'
            ? "Er is een verzoek gedaan om het account-e-mailadres te wijzigen naar {$newEmail}."
            : "A request was made to change the account email address to {$newEmail}.";

        $this->send($user['email'], $subject, $body);
    }

    public function notifyReservationChanged(array $recipient, array $actor, array $reservation, ?array $previous, string $locale): void
    {
        $subject = $locale === 'nl' ? 'Je reservering is gewijzigd' : 'Your reservation was changed';
        $oldText = $previous !== null
            ? ($locale === 'nl'
                ? "\nOud: {$previous['start_datetime']} - {$previous['end_datetime']}"
                : "\nPrevious: {$previous['start_datetime']} - {$previous['end_datetime']}")
            : '';
        $body = $locale === 'nl'
            ? "Je reservering is aangepast door {$actor['first_name']}.\nNieuw: {$reservation['start_datetime']} - {$reservation['end_datetime']}{$oldText}"
            : "Your reservation was updated by {$actor['first_name']}.\nNew: {$reservation['start_datetime']} - {$reservation['end_datetime']}{$oldText}";

        $this->send($recipient['email'], $subject, $body);
    }

    public function notifyReservationCancelled(array $recipient, array $actor, array $reservation, string $locale): void
    {
        $subject = $locale === 'nl' ? 'Je reservering is geannuleerd' : 'Your reservation was cancelled';
        $body = $locale === 'nl'
            ? "Je reservering op {$reservation['start_datetime']} - {$reservation['end_datetime']} is geannuleerd door {$actor['first_name']}."
            : "Your reservation on {$reservation['start_datetime']} - {$reservation['end_datetime']} was cancelled by {$actor['first_name']}.";

        $this->send($recipient['email'], $subject, $body);
    }

    private function send(string $toEmail, string $subject, string $text): void
    {
        $config = Container::get('config')['mailjet'];
        if (!$config['enabled']) {
            $this->log("Mail disabled. Would send to {$toEmail}: {$subject}");
            return;
        }

        $payload = json_encode([
            'Messages' => [[
                'From' => [
                    'Email' => $config['from_email'],
                    'Name' => $config['from_name'],
                ],
                'To' => [[
                    'Email' => $toEmail,
                ]],
                'Subject' => $subject,
                'TextPart' => $text,
            ]],
        ], JSON_THROW_ON_ERROR);

        $headers = [
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode($config['api_key'] . ':' . $config['api_secret']),
        ];

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $payload,
                'timeout' => 15,
                'ignore_errors' => true,
            ],
        ]);

        $result = @file_get_contents('https://api.mailjet.com/v3.1/send', false, $context);
        if ($result === false) {
            $this->log("Mail send failed to {$toEmail}: {$subject}");
        }
    }

    private function log(string $message): void
    {
        file_put_contents($this->logFile, '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL, FILE_APPEND);
    }
}
