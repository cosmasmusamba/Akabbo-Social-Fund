<?php
namespace App\Services\Channels;
use Database;

class SmsChannel implements NotificationChannelInterface
{
    public function send(int $userId, string $title, string $message): bool
    {
        $db = Database::getInstance();
        // SMS usually goes to the Member's phone, not the User's email
        $member = $db->fetchOne("SELECT m.phone FROM members m JOIN users u ON u.member_id = m.id WHERE u.id = ? AND m.status='active'", [$userId]);
        
        if (!$member || empty($member['phone'])) {
            return false; // Member has no phone
        }

        // TODO: Replace with Twilio API logic when ready
        // $smsGateway->send($member['phone'], $message);
        
        error_log("[AKABBO SMS] To: {$member['phone']} | Message: {$message}");
        return true;
    }
}