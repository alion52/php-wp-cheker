<?php
$config = require __DIR__ . '/config.php';
$botToken = $config['bot_token'] ?? '';
if (empty($botToken)) { die('bot_token не задан в config.php'); }
$webhookUrl = 'https://example.com/telegram_bot.php';

// Удаляем старый вебхук
file_get_contents("https://api.telegram.org/bot{$botToken}/deleteWebhook");
sleep(1);

// Устанавливаем новый
$response = file_get_contents("https://api.telegram.org/bot{$botToken}/setWebhook?url={$webhookUrl}");
$result = json_decode($response, true);

if ($result['ok']) {
    echo "✅ Вебхук установлен!";
} else {
    echo "❌ Ошибка: " . ($result['description'] ?? 'Неизвестная');
}
?>