<?php
/**
 * Telegram Bot для проверки сайтов
 * Полная версия с компактным отчетом
 */

// Включаем отображение ошибок для отладки
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'bot_errors.log');

// Увеличиваем лимиты
ini_set('max_execution_time', 300);
ini_set('memory_limit', '256M');

$config = require __DIR__ . '/config.php';
$botToken = $config['bot_token'] ?? '';
if (empty($botToken)) { die('bot_token не задан в config.php'); }
$checkerUrl = 'https://example.com/checker.php';

// Получаем обновление от Telegram
$update = json_decode(file_get_contents('php://input'), true);

// Логируем входящие запросы
file_put_contents('bot_log.txt', date('Y-m-d H:i:s') . ' - ' . json_encode($update, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);

try {
    if (isset($update['message'])) {
        handleMessage($update['message']);
    } elseif (isset($update['callback_query'])) {
        handleCallbackQuery($update['callback_query']);
    }
} catch (Exception $e) {
    file_put_contents('bot_errors.log', date('Y-m-d H:i:s') . ' - ' . $e->getMessage() . "\n", FILE_APPEND);
}

/**
 * Обработка входящих сообщений
 */
function handleMessage($message) {
    $chatId = $message['chat']['id'];
    $text = $message['text'] ?? '';
    $firstName = $message['from']['first_name'] ?? 'Пользователь';

    // Проверяем, ожидаем ли email от пользователя
    $pendingEmail = checkPendingEmail($chatId);

    if ($pendingEmail) {
        // Пользователь отправил email
        if (filter_var($text, FILTER_VALIDATE_EMAIL)) {
            sendReportToEmail($text, $pendingEmail['url']);
            removePendingEmail($chatId);
            sendMessage($chatId, "✅ Отчет отправлен на {$text}");
        } else {
            sendMessage($chatId, "❌ Некорректный email. Попробуйте еще раз или отправьте /cancel для отмены.");
        }
        return;
    }

    if (strpos($text, '/start') === 0) {
        sendWelcomeMessage($chatId, $firstName);
    } elseif (strpos($text, '/help') === 0 || strpos($text, '/menu') === 0) {
        sendMainMenu($chatId);
    } elseif (strpos($text, '/check') === 0) {
        $urls = trim(str_replace('/check', '', $text));
        if (!empty($urls)) {
            checkSites($chatId, $urls);
        } else {
            sendMessage($chatId, "📝 Укажите URL для проверки:\n\n/check https://example.com\n\nИли просто отправьте URL сайта.");
        }
    } elseif (strpos($text, '/contacts') === 0) {
        sendContacts($chatId);
    } elseif (strpos($text, '/cancel') === 0) {
        removePendingEmail($chatId);
        sendMessage($chatId, "✅ Операция отменена.");
        sendMainMenu($chatId);
    } elseif (preg_match('/^(https?:\/\/|www\.)[^\s]+/i', $text)) {
        // Извлекаем все URL из сообщения
        preg_match_all('/(https?:\/\/[^\s]+|www\.[^\s]+)/i', $text, $matches);
        $urls = implode(' ', $matches[1]);
        checkSites($chatId, $urls);
    } else {
        sendMainMenu($chatId);
    }
}

/**
 * Обработка callback запросов
 */
function handleCallbackQuery($callbackQuery) {
    $callbackData = $callbackQuery['data'];
    $chatId = $callbackQuery['message']['chat']['id'];
    $messageId = $callbackQuery['message']['message_id'];

    answerCallbackQuery($callbackQuery['id']);

    if (strpos($callbackData, 'check_again:') === 0) {
        $url = str_replace('check_again:', '', $callbackData);
        checkSites($chatId, $url);
    } elseif (strpos($callbackData, 'send_email:') === 0) {
        $url = str_replace('send_email:', '', $callbackData);
        // Сохраняем URL и запрашиваем email
        savePendingEmail($chatId, $url);
        deleteMessage($chatId, $messageId);
        sendMessage($chatId, "📧 Введите ваш email для отправки отчета:\n\nДля отмены отправьте /cancel");
    } elseif ($callbackData === 'menu') {
        deleteMessage($chatId, $messageId);
        sendMainMenu($chatId);
    } elseif ($callbackData === 'check_prompt') {
        deleteMessage($chatId, $messageId);
        sendMessage($chatId, "📝 Отправьте URL сайта для проверки.\n\nНапример: https://example.com");
    } elseif ($callbackData === 'contacts') {
        deleteMessage($chatId, $messageId);
        sendContacts($chatId);
    }
}

/**
 * Отправка приветственного сообщения
 */
function sendWelcomeMessage($chatId, $firstName) {
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '🔍 Проверить сайт', 'callback_data' => 'check_prompt'],
                ['text' => '📞 Контакты', 'callback_data' => 'contacts']
            ]
        ]
    ];

    $text = "👋 Здравствуйте, {$firstName}!\n\n";
    $text .= "Я бот для проверки безопасности сайтов.\n\n";
    $text .= "🔍 Проверяю:\n";
    $text .= "• WordPress и PHP версии\n";
    $text .= "• SSL сертификат\n";
    $text .= "• Скорость загрузки\n";
    $text .= "• Cookie-баннер\n";
    $text .= "• Формы и капчу\n";
    $text .= "• Аналитику\n";
    $text .= "• Уязвимые файлы\n";
    $text .= "• Кеширование\n\n";
    $text .= "Просто отправьте URL сайта!";

    sendMessageWithKeyboard($chatId, $text, $keyboard, 'HTML');
}

/**
 * Отправка главного меню
 */
function sendMainMenu($chatId) {
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '🔍 Проверить сайт', 'callback_data' => 'check_prompt'],
                ['text' => '📞 Контакты', 'callback_data' => 'contacts']
            ],
            [
                ['text' => '📱 Позвонить', 'url' => 'tel:+375293116611'],
                ['text' => '💻 Сайт', 'url' => 'https://example.com']
            ]
        ]
    ];

    $text = "📋 Главное меню\n\n";
    $text .= "Отправьте URL сайта для проверки.\n";
    $text .= "Например: https://example.com\n\n";
    $text .= "Или используйте команду:\n";
    $text .= "/check https://example.com";

    sendMessageWithKeyboard($chatId, $text, $keyboard, 'HTML');
}

/**
 * Отправка контактов
 */
function sendContacts($chatId) {
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '📱 Позвонить', 'url' => 'tel:+375293116611'],
                ['text' => '📧 Email', 'url' => 'mailto:info@example.com']
            ],
            [
                ['text' => '💻 Сайт', 'url' => 'https://example.com'],
                ['text' => '📋 Меню', 'callback_data' => 'menu']
            ]
        ]
    ];

    $text = "📞 Контакты\n\n";
    $text .= "📱 +375 (29) 311-66-11\n";
    $text .= "📧 info@example.com\n";
    $text .= "💻 example.com";

    sendMessageWithKeyboard($chatId, $text, $keyboard, 'HTML');
}

/**
 * Проверка сайтов
 */
function checkSites($chatId, $urls) {
    global $checkerUrl;

    $loadingMsg = sendMessage($chatId, "🔍 Проверяю сайт...\nЭто может занять до минуты.");
    $loadingMsgId = $loadingMsg['result']['message_id'] ?? null;

    $urlList = array_filter(array_map('trim', explode(' ', $urls)));

    if (empty($urlList)) {
        if ($loadingMsgId) deleteMessage($chatId, $loadingMsgId);
        sendMessage($chatId, "❌ Не удалось распознать URL.");
        return;
    }

    // Ограничиваем количество URL
    $urlList = array_slice($urlList, 0, 5);

    $ch = curl_init($checkerUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['urls' => $urlList]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 120,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curlError) {
        if ($loadingMsgId) deleteMessage($chatId, $loadingMsgId);
        sendMessage($chatId, "❌ Ошибка соединения: " . $curlError);
        return;
    }

    if ($httpCode !== 200 || !$response) {
        if ($loadingMsgId) deleteMessage($chatId, $loadingMsgId);
        sendMessage($chatId, "❌ Ошибка HTTP {$httpCode}");
        return;
    }

    $data = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        if ($loadingMsgId) deleteMessage($chatId, $loadingMsgId);
        sendMessage($chatId, "❌ Ошибка парсинга ответа: " . json_last_error_msg());
        return;
    }

    if (!$data || !isset($data['success']) || !$data['success']) {
        if ($loadingMsgId) deleteMessage($chatId, $loadingMsgId);
        $errorMsg = $data['error'] ?? 'Неизвестная ошибка';
        sendMessage($chatId, "❌ Ошибка: " . $errorMsg);
        return;
    }

    if ($loadingMsgId) deleteMessage($chatId, $loadingMsgId);

    foreach ($data['results'] as $index => $result) {
        $report = formatReport($result, $index + 1);

        // Разбиваем длинные сообщения
        $chunks = str_split($report, 4000);
        foreach ($chunks as $chunk) {
            sendMessage($chatId, $chunk, 'HTML');
            usleep(100000); // Пауза 0.1 сек между сообщениями
        }

        if ($result['status'] === 'success') {
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '🔄 Проверить еще раз', 'callback_data' => 'check_again:' . $result['url']],
                        ['text' => '📋 Меню', 'callback_data' => 'menu']
                    ],
                    [
                        ['text' => '📧 Отправить на Email', 'callback_data' => 'send_email:' . $result['url']],
                        ['text' => '💻 Открыть сайт', 'url' => $result['url']]
                    ]
                ]
            ];
            sendMessageWithKeyboard($chatId, "Выберите действие:", $keyboard);
        }
    }
}

/**
 * Форматирование отчета (полная версия)
 */
function formatReport($result, $number) {
    $html = "📊 <b>Отчет #{$number}</b>\n";
    $html .= "━━━━━━━━━━━━━━━━━━━━\n\n";
    $html .= "🔗 <b>Сайт:</b> {$result['url']}\n\n";

    if ($result['status'] !== 'success') {
        $html .= "❌ <b>Сайт недоступен</b>\n";
        if (isset($result['error'])) {
            $html .= "Причина: {$result['error']}\n";
        }
        return $html;
    }

    $html .= "✅ <b>Статус:</b> Доступен\n\n";

    // Основные параметры
    $html .= "📋 <b>Основные параметры:</b>\n";

    // WordPress
    $html .= "🔵 <b>WordPress:</b> ";
    if ($result['wordpress_version']) {
        $html .= "<code>{$result['wordpress_version']}</code>";
        $html .= version_compare($result['wordpress_version'], '7.1', '<') ? " ⚠️" : " ✅";
        $html .= "\n";
    } else {
        $html .= $result['is_wordpress'] ? "обнаружен\n" : "не используется\n";
    }

    // PHP
    $html .= "🟣 <b>PHP:</b> ";
    if ($result['php_version']) {
        $html .= "<code>{$result['php_version']}</code>";
        if ($result['php_version'] !== '8.0+' && version_compare($result['php_version'], '8.1', '<')) {
            $html .= " ⚠️";
        } else {
            $html .= " ✅";
        }
        $html .= "\n";
    } else {
        $html .= "не определена\n";
    }

    // Время ответа
    $rt = $result['response_time'];
    $speedEmoji = $rt < 300 ? '🟢' : ($rt < 500 ? '🟡' : ($rt < 1000 ? '🟠' : '🔴'));
    $html .= "⚡ <b>Ответ:</b> {$rt}ms {$speedEmoji}\n";

    // SSL
    $html .= "🔒 <b>SSL:</b> " . ($result['has_ssl'] ? '✅' : '❌') . "\n";

    // Уязвимые файлы (ВСЕ)
    if (!empty($result['vulnerable_files'])) {
        $html .= "\n🔴 <b>Уязвимые файлы (" . count($result['vulnerable_files']) . "):</b>\n";
        $riskIcons = [
            'critical' => '🔴',
            'high' => '🟠',
            'medium' => '🟡',
            'low' => '🟢'
        ];

        // Показываем ВСЕ уязвимые файлы
        foreach ($result['vulnerable_files'] as $file) {
            $icon = $riskIcons[$file['risk']] ?? '⚪';
            $html .= "  {$icon} <code>{$file['path']}</code> ({$file['risk']})\n";
        }
    } else {
        $html .= "\n✅ <b>Уязвимые файлы:</b> не обнаружены\n";
    }

    // Кеширование
    if (!empty($result['caching'])) {
        $cache = $result['caching'];
        $html .= "\n⚡ <b>Кеширование:</b> " . ($cache['has_caching'] ? '✅ Включено' : '❌ Отключено') . "\n";

        if ($cache['has_caching'] && !empty($cache['cache_plugins'])) {
            foreach ($cache['cache_plugins'] as $plugin) {
                $html .= "  • {$plugin['name']} ({$plugin['type']})\n";
            }
        }
    }

    // Аналитика (только установленные)
    if (!empty($result['analytics'])) {
        $installedAnalytics = [];
        $notInstalledAnalytics = [];

        if ($result['analytics']['yandex_metrika']) {
            $installedAnalytics[] = 'Яндекс.Метрика';
        } else {
            $notInstalledAnalytics[] = 'Яндекс.Метрика';
        }

        if ($result['analytics']['google_analytics']) {
            $installedAnalytics[] = 'Google Analytics';
        } else {
            $notInstalledAnalytics[] = 'Google Analytics';
        }

        if ($result['analytics']['google_tag_manager']) {
            $installedAnalytics[] = 'GTM';
        } else {
            $notInstalledAnalytics[] = 'GTM';
        }

        if ($result['analytics']['facebook_pixel']) {
            $installedAnalytics[] = 'Facebook Pixel';
        } else {
            $notInstalledAnalytics[] = 'Facebook Pixel';
        }

        if (!empty($installedAnalytics)) {
            $html .= "\n📈 <b>Аналитика:</b>\n";
            foreach ($installedAnalytics as $item) {
                $html .= "  ✅ {$item}\n";
            }
        }

        if (!empty($notInstalledAnalytics)) {
            foreach ($notInstalledAnalytics as $item) {
                $html .= "  ❌ {$item}\n";
            }
        }
    }

    // Cookie-баннер
    if (!empty($result['cookie_banner'])) {
        $cb = $result['cookie_banner'];
        $html .= "\n🍪 <b>Cookie-баннер:</b>\n";
        $html .= "  • Баннер: " . ($cb['has_cookie_banner'] ? '✅' : '❌') . "\n";
        if ($cb['has_cookie_banner']) {
            $html .= "  • Кнопка \"Принять\": " . ($cb['has_accept_button'] ? '✅' : '❌') . "\n";
            $html .= "  • Кнопка \"Отклонить\": " . ($cb['has_reject_button'] ? '✅' : '❌') . "\n";
        }
    }

    // Формы
    if (!empty($result['forms']) && $result['forms']['total_forms'] > 0) {
        $f = $result['forms'];
        $html .= "\n📝 <b>Формы ({$f['total_forms']}):</b>\n";
        $html .= "  • Капча: {$f['forms_with_captcha']} / {$f['total_forms']}\n";
        $html .= "  • Согласие: {$f['forms_with_consent_checkbox']} / {$f['total_forms']}\n";
    }

    // Капча
    if (!empty($result['recaptcha']) && $result['recaptcha']['has_recaptcha']) {
        $html .= "\n🛡 <b>Обнаруженные капчи:</b>\n";
        foreach ($result['recaptcha']['detected'] as $captcha) {
            $html .= "  • {$captcha}\n";
        }
    }

    // Рекомендации (ВСЕ)
    if (!empty($result['recommendations'])) {
        $html .= "\n💡 <b>Рекомендации:</b>\n";

        // Сортируем по важности
        $severityOrder = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];
        usort($result['recommendations'], function($a, $b) use ($severityOrder) {
            return $severityOrder[$a['severity']] - $severityOrder[$b['severity']];
        });

        // Показываем ВСЕ рекомендации
        foreach ($result['recommendations'] as $rec) {
            $icon = $rec['severity'] === 'critical' ? '🔴' : ($rec['severity'] === 'high' ? '🟠' : ($rec['severity'] === 'medium' ? '🟡' : '🟢'));
            $html .= "  {$icon} {$rec['message']}\n";
        }
    }

    $html .= "\n━━━━━━━━━━━━━━━━━━━━";
    $html .= "\n🔍 Проверено: " . date('d.m.Y H:i') . "\n";
    $html .= "💻 example.com";

    return $html;
}

/**
 * Сохранение ожидающего email
 */
function savePendingEmail($chatId, $url) {
    $data = file_exists('pending_emails.json') ? json_decode(file_get_contents('pending_emails.json'), true) : [];
    $data[$chatId] = [
        'url' => $url,
        'timestamp' => time()
    ];
    file_put_contents('pending_emails.json', json_encode($data, JSON_UNESCAPED_UNICODE));
}

/**
 * Проверка ожидающего email
 */
function checkPendingEmail($chatId) {
    if (!file_exists('pending_emails.json')) return false;
    $data = json_decode(file_get_contents('pending_emails.json'), true);
    return $data[$chatId] ?? false;
}

/**
 * Удаление ожидающего email
 */
function removePendingEmail($chatId) {
    if (!file_exists('pending_emails.json')) return;
    $data = json_decode(file_get_contents('pending_emails.json'), true);
    unset($data[$chatId]);
    file_put_contents('pending_emails.json', json_encode($data, JSON_UNESCAPED_UNICODE));
}

/**
 * Отправка отчета на email
 */
function sendReportToEmail($email, $url) {
    global $checkerUrl;

    // Проверяем сайт
    $ch = curl_init($checkerUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['urls' => [$url]]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 60,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);

    if ($data && $data['success'] && !empty($data['results'])) {
        $result = $data['results'][0];
        $html = generateEmailHtml($result);

        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: WP Checker Bot <noreply@example.com>\r\n";

        return mail($email, "Отчет проверки сайта " . $url, $html, $headers);
    }

    return false;
}

/**
 * Генерация HTML для email
 */
function generateEmailHtml($result) {
    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head>';
    $html .= '<body style="font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px; margin: 0;">';
    $html .= '<div style="max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">';
    $html .= '<h1 style="text-align: center; color: #333; margin-bottom: 5px;">Отчет проверки сайта</h1>';
    $html .= '<p style="text-align: center; color: #666; margin-top: 0;">' . date('d.m.Y H:i') . '</p>';
    $html .= '<hr style="border: none; border-top: 2px solid #eee; margin: 20px 0;">';

    $html .= '<h2 style="color: #4f46e5; margin-bottom: 20px;">' . $result['url'] . '</h2>';

    // Основные параметры
    $html .= '<h3 style="color: #333; border-bottom: 2px solid #4f46e5; padding-bottom: 5px;">Основные параметры</h3>';
    $html .= '<table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">';
    $html .= '<tr style="background: #f9fafb;"><td style="padding: 10px; border: 1px solid #e5e7eb; font-weight: bold;">WordPress:</td><td style="padding: 10px; border: 1px solid #e5e7eb;">' . ($result['wordpress_version'] ?? 'Не определена') . '</td></tr>';
    $html .= '<tr><td style="padding: 10px; border: 1px solid #e5e7eb; font-weight: bold;">PHP:</td><td style="padding: 10px; border: 1px solid #e5e7eb;">' . ($result['php_version'] ?? 'Не определена') . '</td></tr>';
    $html .= '<tr style="background: #f9fafb;"><td style="padding: 10px; border: 1px solid #e5e7eb; font-weight: bold;">Время ответа:</td><td style="padding: 10px; border: 1px solid #e5e7eb;">' . $result['response_time'] . ' ms</td></tr>';
    $html .= '<tr><td style="padding: 10px; border: 1px solid #e5e7eb; font-weight: bold;">SSL:</td><td style="padding: 10px; border: 1px solid #e5e7eb; color: ' . ($result['has_ssl'] ? '#16a34a' : '#dc2626') . ';">' . ($result['has_ssl'] ? '✅ Есть' : '❌ Нет') . '</td></tr>';
    $html .= '</table>';

    // Уязвимые файлы
    if (!empty($result['vulnerable_files'])) {
        $html .= '<h3 style="color: #dc2626; border-bottom: 2px solid #dc2626; padding-bottom: 5px;">Уязвимые файлы</h3>';
        $html .= '<ul style="list-style: none; padding: 0;">';
        foreach ($result['vulnerable_files'] as $file) {
            $html .= '<li style="padding: 8px; margin: 5px 0; background: #fef2f2; border-left: 4px solid #dc2626; border-radius: 4px;">';
            $html .= '<strong>' . $file['path'] . '</strong> - ' . $file['risk'];
            $html .= '</li>';
        }
        $html .= '</ul>';
    }

    // Кеширование
    if (!empty($result['caching'])) {
        $cache = $result['caching'];
        $html .= '<h3 style="color: #333; border-bottom: 2px solid #4f46e5; padding-bottom: 5px;">Кеширование</h3>';
        $html .= '<p>Статус: <strong style="color: ' . ($cache['has_caching'] ? '#16a34a' : '#dc2626') . ';">' . ($cache['has_caching'] ? 'Включено' : 'Отключено') . '</strong></p>';
        if ($cache['has_caching'] && !empty($cache['cache_plugins'])) {
            $html .= '<p>Плагины:</p><ul>';
            foreach ($cache['cache_plugins'] as $plugin) {
                $html .= '<li>' . $plugin['name'] . '</li>';
            }
            $html .= '</ul>';
        }
    }

    // Рекомендации
    if (!empty($result['recommendations'])) {
        $html .= '<h3 style="color: #333; border-bottom: 2px solid #4f46e5; padding-bottom: 5px;">Рекомендации</h3>';
        $html .= '<ul style="list-style: none; padding: 0;">';
        foreach ($result['recommendations'] as $rec) {
            $bgColor = $rec['severity'] === 'critical' ? '#fef2f2' : ($rec['severity'] === 'high' ? '#fffbeb' : '#f0fdf4');
            $borderColor = $rec['severity'] === 'critical' ? '#dc2626' : ($rec['severity'] === 'high' ? '#f59e0b' : '#16a34a');
            $html .= '<li style="padding: 8px; margin: 5px 0; background: ' . $bgColor . '; border-left: 4px solid ' . $borderColor . '; border-radius: 4px;">';
            $html .= $rec['message'];
            $html .= '</li>';
        }
        $html .= '</ul>';
    }

    $html .= '<hr style="border: none; border-top: 2px solid #eee; margin: 20px 0;">';
    $html .= '<p style="text-align: center; color: #666; font-size: 12px;">Проверено ботом WP Checker</p>';
    $html .= '<p style="text-align: center; color: #666; font-size: 12px;">example.com</p>';

    $html .= '</div></body></html>';
    return $html;
}

/**
 * Отправка сообщения
 */
function sendMessage($chatId, $text, $parseMode = null) {
    global $botToken;

    $data = [
        'chat_id' => $chatId,
        'text' => $text
    ];

    if ($parseMode) {
        $data['parse_mode'] = $parseMode;
    }

    return callTelegramApi('sendMessage', $data);
}

/**
 * Отправка сообщения с клавиатурой
 */
function sendMessageWithKeyboard($chatId, $text, $keyboard, $parseMode = null) {
    global $botToken;

    $data = [
        'chat_id' => $chatId,
        'text' => $text,
        'reply_markup' => json_encode($keyboard)
    ];

    if ($parseMode) {
        $data['parse_mode'] = $parseMode;
    }

    return callTelegramApi('sendMessage', $data);
}

/**
 * Удаление сообщения
 */
function deleteMessage($chatId, $messageId) {
    return callTelegramApi('deleteMessage', [
        'chat_id' => $chatId,
        'message_id' => $messageId
    ]);
}

/**
 * Ответ на callback query
 */
function answerCallbackQuery($callbackQueryId, $text = null) {
    $data = [
        'callback_query_id' => $callbackQueryId
    ];

    if ($text) {
        $data['text'] = $text;
        $data['show_alert'] = true;
    }

    return callTelegramApi('answerCallbackQuery', $data);
}

/**
 * Вызов Telegram API
 */
function callTelegramApi($method, $data) {
    global $botToken;

    $ch = curl_init("https://api.telegram.org/bot{$botToken}/{$method}");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        file_put_contents('bot_errors.log', date('Y-m-d H:i:s') . ' - Telegram API Error: ' . $curlError . "\n", FILE_APPEND);
        return null;
    }

    return json_decode($response, true);
}
?>