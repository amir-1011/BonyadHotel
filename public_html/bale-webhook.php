<?php

$TOKEN = '1511215504:v484_y8DU5c8Tp1mq6eUnNik8nsAgny4lSo';
$API = "https://tapi.bale.ai/bot{$TOKEN}/sendMessage";

$secret = $_GET['secret'] ?? '';
if ($secret !== '' && $secret !== 'v484') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Forbidden';
    exit;
}

$raw = file_get_contents('php://input');
$update = json_decode($raw, true);

$chat_id = $update['message']['chat']['id'] ?? null;
$text = $update['message']['text'] ?? '';
$contact = $update['message']['contact'] ?? null;

if (!$chat_id) {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($text === '/start' || str_starts_with($text, '/start ')) {

    $welcome = "به سامانه رزرواسیون هوشمند موسسه فرهنگی،ورزشی و توانبخشی ایثار خوش آمدید 🌷

سامانه‌ای رسمی، امن و ساده برای رزرو آنلاین؛ با هدف ارائه دسترسی سریع‌تر به خدمات اقامتی، پشتیبانی قابل اعتماد و شرایط ویژه برای خانواده‌های معزز بنیاد شهید.
برای دریافت پشتیبانی هوشمند و پاسخگویی سریع، می‌توانید از ربات زیر استفاده کنید:
@bonyadyarbot
لطفاً برای ادامه، وارد سامانه شوید";

    $keyboard = [
        'inline_keyboard' => [
            [
                [
                    'text' => 'ورود به سامانه رزرو',
                    'web_app' => [
                        'url' => 'https://bonyadyar.ir/miniapp/bale',
                    ],
                ],
            ],
        ],
    ];

    $data = [
        'chat_id' => $chat_id,
        'text' => $welcome,
        'reply_markup' => json_encode($keyboard, JSON_UNESCAPED_UNICODE),
    ];

    $ch = curl_init($API);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    curl_close($ch);

} elseif (is_array($contact)) {

    $keyboard = [
        'inline_keyboard' => [
            [
                [
                    'text' => 'ورود به سامانه رزرو',
                    'web_app' => [
                        'url' => 'https://bonyadyar.ir/miniapp/bale',
                    ],
                ],
            ],
        ],
    ];

    $data = [
        'chat_id' => $chat_id,
        'text' => "شماره تلفن شما دریافت شد. اکنون می‌توانید وارد سامانه رزرو شوید.",
        'reply_markup' => json_encode($keyboard, JSON_UNESCAPED_UNICODE),
    ];

    $ch = curl_init($API);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    curl_close($ch);
}

header('Content-Type: application/json; charset=UTF-8');
echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);