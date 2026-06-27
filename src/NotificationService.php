<?php

declare(strict_types=1);

class NotificationService
{
    public static function send(string $to, string $subject, string $body): bool
    {
        $from = env('MAIL_FROM');
        if ($from === null || $from === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/plain; charset=UTF-8',
            'From: ' . $from,
        ];

        return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, implode("\r\n", $headers));
    }

    public static function notifyUser(int $userId, string $subject, string $body): void
    {
        $user = UserService::getById($userId);
        if (!$user || empty($user['email'])) {
            return;
        }
        self::send((string) $user['email'], $subject, $body);
    }

    public static function taskAssigned(int $userId, string $title): void
    {
        self::notifyUser(
            $userId,
            'مهمة جديدة — ' . config('app.name'),
            "تم إسناد مهمة جديدة: {$title}\n" . url('/employee/dashboard') . '#my-tasks'
        );
    }

    public static function taskReply(int $assignerId, string $title, string $message, string $employeeName): void
    {
        self::notifyUser(
            $assignerId,
            'رد على مهمة — ' . config('app.name'),
            "رد من {$employeeName} على المهمة «{$title}»:\n\n{$message}\n\n" . url('/manager/tasks')
        );
    }
}
