<?php
namespace App\Services\Channels;

interface NotificationChannelInterface
{
    public function send(int $userId, string $title, string $message): bool;
}