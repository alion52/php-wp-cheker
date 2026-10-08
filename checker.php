<?php
/**
 * API обработчик для проверки сайтов
 * Полная версия с улучшенной проверкой Cookie и форм
 */

error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

ob_start();

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input && !empty($_POST)) {
        $input = $_POST;
    }

    if (isset($input['urls']) && is_string($input['urls'])) {
        $input['urls'] = array_filter(array_map('trim', explode("\n", $input['urls'])));
    }

    $urls = $input['urls'] ?? [];
    $action = $input['action'] ?? 'check';

    if ($action === 'get_version_info') {
        $latestVersions = getLatestVersions();
        $versionInfo = getVersionInfo($latestVersions);

        $response = [
            'success' => true,
            'latest_versions' => $latestVersions,
            'version_info' => $versionInfo,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        ob_end_clean();
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'send_email') {
        $email = $input['email'] ?? '';
        $html = $input['html'] ?? '';
        $subject = $input['subject'] ?? 'Отчет WP Checker';

        if (empty($email) || empty($html)) {
            echo json_encode(['success' => false, 'error' => 'Email или HTML не указаны']);
            exit;
        }

        $to = $email;
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8" . "\r\n";
        $headers .= "From: WP Checker <noreply@example.com>" . "\r\n";

        $mailSent = mail($to, $subject, $html, $headers);

        if ($mailSent) {
            echo json_encode(['success' => true, 'message' => 'Email отправлен']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Не удалось отправить email']);
        }
        exit;
    }

    if (empty($urls)) {
        $response = ['success' => false, 'error' => 'URL не указаны'];
        ob_end_clean();
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    $results = [];

    foreach ($urls as $url) {
        $result = checkSite($url);
        $results[] = $result;
    }

    $latestVersions = getLatestVersions();

    $response = [
        'success' => true,
        'results' => $results,
        'latest_versions' => $latestVersions,
        'version_info' => getVersionInfo($latestVersions),
        'updated_at' => date('Y-m-d H:i:s')
    ];

    ob_end_clean();
    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

function getLatestVersions() {
    $versions = [
        'wordpress' => '6.7.2',
        'php_stable' => '8.3.10',
        'php_supported' => ['8.1', '8.2', '8.3']
    ];

    $wpApi = @file_get_contents('https://api.wordpress.org/core/version-check/1.7/');
    if ($wpApi) {
        $wpData = json_decode($wpApi, true);
        if (isset($wpData['offers'][0]['current'])) {
            $versions['wordpress'] = $wpData['offers'][0]['current'];
        }
    }

    return $versions;
}

function getVersionInfo($latestVersions = null) {
    if (!$latestVersions) {
        $latestVersions = getLatestVersions();
    }

    return [
        'wordpress' => [
            'current_version' => $latestVersions['wordpress'],
            'release_date' => '2025-01-15',
            'description' => 'WordPress - самая популярная CMS в мире',
            'security_fixes' => [
                'Защита от XSS-атак и SQL-инъекций',
                'Исправление уязвимостей в REST API',
                'Улучшенная защита от CSRF-атак',
                'Безопасная обработка загружаемых файлов',
                'Защита от атак через XML-RPC'
            ],
            'performance' => [
                'Улучшенная производительность за счет кэширования',
                'Оптимизация работы с базой данных',
                'Более быстрая загрузка страниц',
                'Улучшенная работа с изображениями (WebP)',
                'Ленивая загрузка контента'
            ],
            'features' => [
                'Новый редактор Gutenberg',
                'Улучшенная работа с медиафайлами',
                'Поддержка современных PHP версий',
                'Улучшенная доступность',
                'Новые API для разработчиков'
            ]
        ],
        'php' => [
            'current_version' => $latestVersions['php_stable'],
            'release_date' => '2024-11-21',
            'description' => 'PHP - популярный язык программирования',
            'security' => [
                'Исправление уязвимостей в ядре PHP',
                'Улучшенная защита от buffer overflow',
                'Безопасная обработка десериализации',
                'Защита от атак через include/require',
                'Улучшенная работа с OpenSSL'
            ],
            'performance' => [
                'JIT-компиляция (PHP 8.0+)',
                'Улучшенная производительность до 50%',
                'Оптимизация работы с памятью',
                'Более быстрая обработка строк',
                'Улучшенная работа с массивами'
            ],
            'features' => [
                'Типизированные свойства классов',
                'Union types',
                'Match expression',
                'Nullsafe operator (?->)',
                'Атрибуты (Attributes)'
            ]
        ]
    ];
}

function checkSite($url) {
    $result = [
        'url' => $url,
        'wordpress_version' => null,
        'php_version' => null,
        'status' => 'unknown',
        'error' => null,
        'is_wordpress' => false,
        'server' => null,
        'title' => null,
        'response_time' => 0,
        'has_ssl' => false,
        'vulnerable_files' => [],
        'analytics' => [],
        'recaptcha' => [],
        'cookie_banner' => [],
        'forms' => [],
        'empty_pages' => [],
        'recommendations' => []
    ];

    if (!preg_match('/^https?:\/\//', $url)) {
        $url = 'https://' . $url;
        $result['url'] = $url;
    }

    $result['has_ssl'] = strpos($url, 'https://') === 0;
    $startTime = microtime(true);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        CURLOPT_HEADER => true
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $result['response_time'] = round((microtime(true) - $startTime) * 1000, 0);
    curl_close($ch);

    if ($error) {
        $result['status'] = 'error';
        $result['error'] = $error;
        return $result;
    }

    if ($httpCode >= 400) {
        $result['status'] = 'error';
        $result['error'] = "HTTP ошибка: $httpCode";
        return $result;
    }

    $result['status'] = 'success';

    $headers = substr($response, 0, $headerSize);
    $html = substr($response, $headerSize);

    if (preg_match('/X-Powered-By: PHP\/([\d.]+)/i', $headers, $matches)) {
        $result['php_version'] = $matches[1];
    }

    if (preg_match('/Server: (.+)/i', $headers, $matches)) {
        $result['server'] = trim($matches[1]);
    }

    if (preg_match('/wp-content|wp-includes|wp-json/i', $html)) {
        $result['is_wordpress'] = true;
    }

    $patterns = [
        '/<meta\s+name=["\']generator["\']\s+content=["\']WordPress\s+([\d.]+)/i',
        '/WordPress\s+([\d.]+)/i',
        '/wp-includes\/css\/[^"\']*ver=([\d.]+)/i',
        '/wp-includes\/js\/[^"\']*ver=([\d.]+)/i'
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $html, $matches)) {
            $result['wordpress_version'] = $matches[1];
            $result['is_wordpress'] = true;
            break;
        }
    }

    if (preg_match('/<title>(.*?)<\/title>/i', $html, $matches)) {
        $result['title'] = trim($matches[1]);
    }

    $result['vulnerable_files'] = checkVulnerableFiles($url);
    $result['analytics'] = checkAnalytics($html);
    $result['recaptcha'] = checkRecaptcha($html);
    $result['cookie_banner'] = checkCookieBanner($html);
    $result['forms'] = checkForms($html);
    $result['empty_pages'] = checkEmptyPages($html);
    $result['recommendations'] = generateRecommendations($result);

    return $result;
}

function checkVulnerableFiles($url) {
    $vulnerablePaths = [
        '/wp-config.php.bak' => 'critical',
        '/wp-config.php~' => 'critical',
        '/.env' => 'critical',
        '/.git/config' => 'critical',
        '/phpinfo.php' => 'high',
        '/info.php' => 'high',
        '/wp-content/debug.log' => 'high',
        '/xmlrpc.php' => 'high',
        '/readme.html' => 'medium',
        '/license.txt' => 'medium',
        '/wp-admin/install.php' => 'medium',
        '/wp-content/cache/' => 'low',
        '/wp-includes/version.php' => 'low'
    ];

    $foundFiles = [];

    foreach ($vulnerablePaths as $path => $risk) {
        $fullUrl = rtrim($url, '/') . $path;
        $ch = curl_init($fullUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOBODY => true,
            CURLOPT_TIMEOUT => 3,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'WP-Checker/1.0'
        ]);

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode == 200) {
            $foundFiles[] = [
                'path' => $path,
                'url' => $fullUrl,
                'risk' => $risk
            ];
        }
    }

    return $foundFiles;
}

function checkAnalytics($html) {
    return [
        'yandex_metrika' => strpos($html, 'mc.yandex.ru/metrika') !== false || strpos($html, 'ym.js') !== false,
        'google_analytics' => strpos($html, 'google-analytics.com') !== false || strpos($html, 'gtag') !== false,
        'google_tag_manager' => strpos($html, 'googletagmanager.com') !== false,
        'facebook_pixel' => strpos($html, 'connect.facebook.net') !== false || strpos($html, 'fbq(') !== false
    ];
}

function checkRecaptcha($html) {
    $recaptcha = [
        'has_recaptcha' => false,
        'type' => null,
        'forms_without_recaptcha' => 0
    ];

    if (strpos($html, 'g-recaptcha') !== false || strpos($html, 'recaptcha') !== false || strpos($html, 'h-captcha') !== false) {
        $recaptcha['has_recaptcha'] = true;

        if (strpos($html, 'g-recaptcha') !== false) {
            $recaptcha['type'] = 'Google reCAPTCHA v2';
        } elseif (strpos($html, 'recaptcha-v3') !== false) {
            $recaptcha['type'] = 'Google reCAPTCHA v3';
        } elseif (strpos($html, 'h-captcha') !== false) {
            $recaptcha['type'] = 'hCaptcha';
        }
    }

    preg_match_all('/<form[^>]*>/i', $html, $forms);
    $totalForms = count($forms[0] ?? []);
    preg_match_all('/<form[^>]*>[^<]*(?:g-recaptcha|recaptcha|h-captcha)[^<]*<\/form>/i', $html, $formsWithRecaptcha);
    $formsWithRecaptchaCount = count($formsWithRecaptcha[0] ?? []);

    $recaptcha['forms_without_recaptcha'] = max(0, $totalForms - $formsWithRecaptchaCount);

    return $recaptcha;
}

function checkCookieBanner($html) {
    $cookieBanner = [
        'has_cookie_banner' => false,
        'has_accept_button' => false,
        'has_reject_button' => false,
        'has_cookie_policy_link' => false,
        'has_privacy_policy_link' => false,
        'has_personal_data_consent' => false,
        'banner_text' => null,
        'details' => []
    ];

    // Проверяем наличие cookie-баннера
    $cookiePatterns = [
        '/cookie[_\-\s]?(banner|consent|notice|popup|bar|notification|policy)/i',
        '/we use cookies/i',
        '/this (site|website) uses cookies/i',
        '/by (continuing|using).*(cookie|cookies)/i',
        '/accept.*cookies/i',
        '/cookie.*settings/i',
        '/cookie.*preferences/i',
        '/gdpr/i',
        '/ccpa/i',
        '/куки/i',
        '/cookie/i',
        '/файлы cookie/i',
        '/используем cookie/i',
        '/использует cookie/i',
        '/продолжая.*cookie/i',
        '/принимаю.*cookie/i',
        '/соглашаетесь.*cookie/i',
        '/cookie.*настройки/i',
        '/обработку персональных данных/i',
        '/согласие на обработку/i',
        '/политика конфиденциальности/i',
        '/политика cookie/i',
        '/политика куки/i'
    ];

    $foundPattern = null;
    foreach ($cookiePatterns as $pattern) {
        if (preg_match($pattern, $html, $matches)) {
            $cookieBanner['has_cookie_banner'] = true;
            $foundPattern = $matches[0];
            break;
        }
    }

    if ($cookieBanner['has_cookie_banner']) {
        $cookieBanner['details'][] = 'Найден cookie-баннер';
    }

    // Проверяем кнопки ПРИНЯТЬ
    $acceptPatterns = [
        '/accept[_\-\s]?(all|all cookies)?/i',
        '/принять/i',
        '/принимаю/i',
        '/согласен/i',
        '/соглашаюсь/i',
        '/accept cookies/i',
        '/allow cookies/i',
        '/понятно/i',
        '/хорошо/i',
        '/принять все/i'
    ];

    foreach ($acceptPatterns as $pattern) {
        if (preg_match($pattern, $html, $matches)) {
            $cookieBanner['has_accept_button'] = true;
            break;
        }
    }

    // Проверяем кнопки ОТКЛОНИТЬ
    $rejectPatterns = [
        '/reject[_\-\s]?(all|all cookies)?/i',
        '/decline/i',
        '/отклонить/i',
        '/отказаться/i',
        '/не принимать/i',
        '/только необходимые/i',
        '/essential only/i',
        '/necessary only/i',
        '/reject cookies/i',
        '/decline cookies/i',
        '/настройки cookie/i',
        '/cookie settings/i',
        '/cookie preferences/i'
    ];

    foreach ($rejectPatterns as $pattern) {
        if (preg_match($pattern, $html, $matches)) {
            $cookieBanner['has_reject_button'] = true;
            break;
        }
    }

    // Проверяем ссылку на политику cookie
    $policyPatterns = [
        '/href=["\'][^"\']*cookie[^"\']*policy[^"\']*["\']/i',
        '/href=["\'][^"\']*cookie[^"\']*["\']/i',
        '/cookie[_\-\s]?policy/i',
        '/политика cookie/i',
        '/политика куки/i'
    ];

    foreach ($policyPatterns as $pattern) {
        if (preg_match($pattern, $html)) {
            $cookieBanner['has_cookie_policy_link'] = true;
            break;
        }
    }

    // Проверяем ссылку на политику конфиденциальности
    $privacyPatterns = [
        '/href=["\'][^"\']*privacy[^"\']*["\']/i',
        '/privacy[_\-\s]?policy/i',
        '/политика конфиденциальности/i',
        '/конфиденциальность/i'
    ];

    foreach ($privacyPatterns as $pattern) {
        if (preg_match($pattern, $html)) {
            $cookieBanner['has_privacy_policy_link'] = true;
            break;
        }
    }

    // Проверяем согласие на обработку персональных данных
    $consentPatterns = [
        '/согласие на обработку персональных данных/i',
        '/обработку персональных данных/i',
        '/персональные данные/i',
        '/personal data/i',
        '/согласен на обработку/i',
        '/даю согласие/i'
    ];

    foreach ($consentPatterns as $pattern) {
        if (preg_match($pattern, $html)) {
            $cookieBanner['has_personal_data_consent'] = true;
            break;
        }
    }

    // Дополнительная проверка - чекбоксы согласия
    if (preg_match('/type=["\']checkbox["\'][^>]*(consent|agree|соглас|privacy|policy)/i', $html)) {
        $cookieBanner['has_personal_data_consent'] = true;
    }

    return $cookieBanner;
}

function checkForms($html) {
    $forms = [
        'total_forms' => 0,
        'forms_with_captcha' => 0,
        'forms_with_consent_checkbox' => 0,
        'forms_without_captcha' => 0,
        'forms_without_consent' => 0,
        'forms_with_privacy_link' => 0,
        'forms_without_privacy_link' => 0,
        'form_details' => []
    ];

    preg_match_all('/<form[^>]*>/i', $html, $formMatches);
    $forms['total_forms'] = count($formMatches[0] ?? []);

    if (preg_match_all('/<form[^>]*>(.*?)<\/form>/is', $html, $formContents)) {
        foreach ($formContents[1] as $index => $formContent) {
            $formInfo = [
                'index' => $index + 1,
                'has_captcha' => false,
                'has_consent_checkbox' => false,
                'has_privacy_link' => false,
                'has_submit_button' => false,
                'fields_count' => 0
            ];

            // Капча
            if (preg_match('/g-recaptcha|recaptcha|h-captcha|captcha|turnstile/i', $formContent)) {
                $formInfo['has_captcha'] = true;
                $forms['forms_with_captcha']++;
            } else {
                $forms['forms_without_captcha']++;
            }

            // Галочка согласия
            if (preg_match('/type=["\']checkbox["\']/i', $formContent) &&
                preg_match('/(consent|agree|соглас|принимаю|privacy|policy|personal)/i', $formContent)) {
                $formInfo['has_consent_checkbox'] = true;
                $forms['forms_with_consent_checkbox']++;
            } else {
                $forms['forms_without_consent']++;
            }

            // Ссылка на политику
            if (preg_match('/href=["\'][^"\']*(privacy|policy|конфиденц|политик)[^"\']*["\']/i', $formContent)) {
                $formInfo['has_privacy_link'] = true;
                $forms['forms_with_privacy_link']++;
            } else {
                $forms['forms_without_privacy_link']++;
            }

            // Кнопка отправки
            if (preg_match('/type=["\']submit["\']|button[^>]*submit|input[^>]*type=["\']submit/i', $formContent)) {
                $formInfo['has_submit_button'] = true;
            }

            // Количество полей
            preg_match_all('/<input[^>]*>/i', $formContent, $inputs);
            preg_match_all('/<textarea[^>]*>/i', $formContent, $textareas);
            preg_match_all('/<select[^>]*>/i', $formContent, $selects);
            $formInfo['fields_count'] = count($inputs[0]) + count($textareas[0]) + count($selects[0]);

            $forms['form_details'][] = $formInfo;
        }
    }

    return $forms;
}

function checkEmptyPages($html) {
    $emptyPages = [];

    $textContent = strip_tags($html);
    $textContent = trim(preg_replace('/\s+/', ' ', $textContent));

    if (strlen($textContent) < 100) {
        $emptyPages[] = [
            'type' => 'empty_content',
            'message' => 'Страница содержит очень мало контента',
            'content_length' => strlen($textContent)
        ];
    }

    $placeholderPatterns = [
        '/under construction/i' => 'Under construction',
        '/coming soon/i' => 'Coming soon',
        '/lorem ipsum/i' => 'Lorem ipsum',
        '/placeholder/i' => 'Placeholder'
    ];

    foreach ($placeholderPatterns as $pattern => $description) {
        if (preg_match($pattern, $html, $matches)) {
            $emptyPages[] = [
                'type' => 'placeholder',
                'message' => 'Обнаружен технический текст-заглушка',
                'description' => $description,
                'found_text' => $matches[0]
            ];
            break;
        }
    }

    return $emptyPages;
}

function generateRecommendations($result) {
    $recommendations = [];
    $latestVersions = getLatestVersions();
    $versionInfo = getVersionInfo($latestVersions);

    if ($result['wordpress_version']) {
        $version = $result['wordpress_version'];

        if (version_compare($version, $latestVersions['wordpress'], '<')) {
            $recommendations[] = [
                'type' => 'wordpress_update',
                'severity' => 'high',
                'message' => "Обновите WordPress с версии $version до {$latestVersions['wordpress']}",
                'improvements' => $versionInfo['wordpress']
            ];
        }

        if (version_compare($version, '5.0', '<')) {
            $recommendations[] = [
                'type' => 'critical_wordpress_update',
                'severity' => 'critical',
                'message' => "Критически устаревшая версия WordPress ($version)!"
            ];
        }
    }

    if ($result['php_version']) {
        $phpVersion = $result['php_version'];

        if (version_compare($phpVersion, '8.1', '<')) {
            $recommendations[] = [
                'type' => 'php_update',
                'severity' => 'high',
                'message' => "Обновите PHP с версии $phpVersion до 8.1+",
                'improvements' => $versionInfo['php']
            ];
        }
    }

    if (!$result['has_ssl']) {
        $recommendations[] = [
            'type' => 'ssl',
            'severity' => 'medium',
            'message' => "Сайт не использует HTTPS"
        ];
    }

    foreach ($result['vulnerable_files'] as $file) {
        $severity = $file['risk'] === 'critical' ? 'critical' : ($file['risk'] === 'high' ? 'high' : 'medium');
        $recommendations[] = [
            'type' => 'vulnerable_file',
            'severity' => $severity,
            'message' => "Обнаружен уязвимый файл: {$file['path']}",
            'file' => $file
        ];
    }

    if (!$result['analytics']['yandex_metrika'] && !$result['analytics']['google_analytics']) {
        $recommendations[] = [
            'type' => 'analytics',
            'severity' => 'low',
            'message' => "Рекомендуется установить аналитику"
        ];
    }

    if (!$result['recaptcha']['has_recaptcha'] && $result['recaptcha']['forms_without_recaptcha'] > 0) {
        $recommendations[] = [
            'type' => 'recaptcha',
            'severity' => 'medium',
            'message' => "Формы без защиты reCAPTCHA"
        ];
    }

    // Cookie-баннер
    if (isset($result['cookie_banner'])) {
        if (!$result['cookie_banner']['has_cookie_banner']) {
            $recommendations[] = [
                'type' => 'cookie_banner',
                'severity' => 'high',
                'message' => 'Cookie-баннер не обнаружен. Сайт должен уведомлять об использовании cookie по GDPR'
            ];
        } else {
            if (!$result['cookie_banner']['has_accept_button']) {
                $recommendations[] = [
                    'type' => 'cookie_banner',
                    'severity' => 'high',
                    'message' => 'В Cookie-баннере нет кнопки "Принять"'
                ];
            }
            if (!$result['cookie_banner']['has_reject_button']) {
                $recommendations[] = [
                    'type' => 'cookie_banner',
                    'severity' => 'medium',
                    'message' => 'В Cookie-баннере нет кнопки "Отклонить"'
                ];
            }
            if (!$result['cookie_banner']['has_cookie_policy_link']) {
                $recommendations[] = [
                    'type' => 'cookie_banner',
                    'severity' => 'medium',
                    'message' => 'Нет ссылки на Политику cookie'
                ];
            }
            if (!$result['cookie_banner']['has_privacy_policy_link']) {
                $recommendations[] = [
                    'type' => 'cookie_banner',
                    'severity' => 'low',
                    'message' => 'Нет ссылки на Политику конфиденциальности'
                ];
            }
        }
    }

    // Формы
    if (isset($result['forms'])) {
        if ($result['forms']['total_forms'] > 0) {
            if ($result['forms']['forms_without_captcha'] > 0) {
                $recommendations[] = [
                    'type' => 'forms',
                    'severity' => 'high',
                    'message' => "{$result['forms']['forms_without_captcha']} из {$result['forms']['total_forms']} форм не защищены капчей"
                ];
            }
            if ($result['forms']['forms_without_consent'] > 0) {
                $recommendations[] = [
                    'type' => 'forms',
                    'severity' => 'high',
                    'message' => "{$result['forms']['forms_without_consent']} из {$result['forms']['total_forms']} форм без галочки согласия"
                ];
            }
            if ($result['forms']['forms_without_privacy_link'] > 0) {
                $recommendations[] = [
                    'type' => 'forms',
                    'severity' => 'low',
                    'message' => "{$result['forms']['forms_without_privacy_link']} форм без ссылки на политику конфиденциальности"
                ];
            }
        }
    }

    foreach ($result['empty_pages'] as $emptyPage) {
        $recommendations[] = [
            'type' => 'empty_page',
            'severity' => 'medium',
            'message' => $emptyPage['message']
        ];
    }

    return $recommendations;
}
?>