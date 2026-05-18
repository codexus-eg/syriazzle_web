<?php
// ========================================================================
// Syriazzle - Firebase Notification Manager (Final Precision Version 5.2)
// ========================================================================

$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
} else {
    error_log("NotificationManager Error: Composer autoload not found.");
}

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\WebPushConfig;

class NotificationManager {

    private static $messaging = null;
    private const DEFAULT_ICON = 'https://syriazzle.sy/image/logo1.png';
    private const BASE_URL = 'https://syriazzle.sy/';

    private static function init(): bool {
        if (!class_exists('Kreait\Firebase\Factory')) return false;

        if (self::$messaging === null) {
            $credentialsPath = __DIR__ . '/firebase_credentials.json';
            if (!file_exists($credentialsPath)) return false;

            try {
                $factory = (new Factory)->withServiceAccount($credentialsPath);
                self::$messaging = $factory->createMessaging();
            } catch (\Exception $e) { return false; }
        }
        return true;
    }

    public static function sendNotification(int $targetId, string $targetType, string $title, string $body, ?string $link = null) {
        global $pdo;

        // 1. حفظ الإشعار في قاعدة البيانات (لجرس الموقع الداخلي)
        try {
            $stmt_save = $pdo->prepare("
                INSERT INTO site_notifications (user_id, user_type, title, message, link, is_read, created_at) 
                VALUES (?, ?, ?, ?, ?, 0, NOW())
            ");
            $stmt_save->execute([$targetId, $targetType, $title, $body, $link]);
        } catch (\Exception $e) { error_log("DB Notification Save Error: " . $e->getMessage()); }

        // 2. إرسال إشعار الهاتف (Push Notification)
        if (!self::init()) return false;

        try {
            $table = ($targetType === 'driver') ? 'drivers' : (($targetType === 'business') ? 'businesses' : 'users');
            $stmt = $pdo->prepare("SELECT fcm_token FROM {$table} WHERE id = ? AND fcm_token IS NOT NULL LIMIT 1");
            $stmt->execute([$targetId]);
            $token = $stmt->fetchColumn();

            if (!$token) return true;

            $notification = Notification::create($title, $body, self::DEFAULT_ICON);

            // --- تعديلات المهندس لضمان الصوت والشعار بدقة ---
            $androidConfig = AndroidConfig::fromArray([
                'ttl' => '3600s',
                'priority' => 'high',
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                    'icon' => 'logo1', // الشعار الموجود في drawable
                    'channel_id' => 'syriazzle_notifications', // القناة المبرمجة في MainActivity
                    'color' => '#e60000',
                    'sound' => 'notification_sound', // اسم ملف الصوت بدون امتداد (الموجود في raw)
                    'click_action' => 'OPEN_ACTIVITY_1'
                ],
            ]);

            $full_link = $link ? self::BASE_URL . ltrim($link, '/') : self::BASE_URL;
            
            // إعدادات الويب
            $webPushConfig = WebPushConfig::fromArray([
                'notification' => ['icon' => self::DEFAULT_ICON],
                'fcm_options' => ['link' => $full_link]
            ]);

            $message = CloudMessage::withTarget('token', $token)
                ->withNotification($notification)
                ->withAndroidConfig($androidConfig)
                ->withWebPushConfig($webPushConfig);

            // إرسال البيانات (Data Payload) مهمة لتعرف صفحة الويب ما تفتحه
            $message = $message->withData([
                'title' => $title,
                'body' => $body,
                'url' => $full_link
            ]);

            self::$messaging->send($message);
            return true;

        } catch (\Exception $e) {
            error_log("FCM Send Error: " . $e->getMessage());
            return false;
        }
    }
}