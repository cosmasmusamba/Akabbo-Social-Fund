<?php
namespace App\Services\Channels;
use Database;

class EmailChannel implements NotificationChannelInterface
{
    public function send(int $userId, string $title, string $message): bool
    {
        $db = Database::getInstance();
        $user = $db->fetchOne("SELECT email, first_name FROM users WHERE id = ? AND status='active'", [$userId]);
        
        if (!$user || empty($user['email'])) {
            return false; // User has no email
        }

        // TODO: Replace with PHPMailer / SMTP logic when ready
        // mail($user['email'], $title, $message, "From: noreply@akabbofund.org");
        
        error_log("[AKABBO EMAIL] To: {$user['email']} | Subject: {$title} | Body: {$message}");
        return true;
    }
}