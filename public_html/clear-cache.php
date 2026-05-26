<?php
/**
 * پاک‌کننده دستی کش
 * برای امنیت بیشتر این فایل را بعد از استفاده پاک یا نام آن را تغییر دهید
 */

// ── کلید امنیتی ── قبل از استفاده عوض کنید ───────────────────────────────
define('SECRET_KEY', 'clear1234');

if (($_GET['key'] ?? '') !== SECRET_KEY) {
    http_response_code(403);
    exit('<div style="font-family:sans-serif;direction:rtl;padding:40px;color:#c00">
            ❌ دسترسی غیرمجاز — کلید امنیتی اشتباه است
          </div>');
}

// ── Bootstrap Laravel ─────────────────────────────────────────────────────
define('LARAVEL_START', microtime(true));
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// ── اجرای دستورات پاک‌سازی ────────────────────────────────────────────────
$results = [];

$commands = [
    'view:clear'   => 'کمپایل‌های Blade',
    'cache:clear'  => 'کش اپلیکیشن (شامل nav_locations و ...)',
    'config:clear' => 'کش تنظیمات',
    'route:clear'  => 'کش مسیرها',
    'event:clear'  => 'کش رویدادها',
];

foreach ($commands as $cmd => $label) {
    try {
        Artisan::call($cmd);
        $results[] = ['ok' => true, 'cmd' => $cmd, 'label' => $label];
    } catch (Throwable $e) {
        $results[] = ['ok' => false, 'cmd' => $cmd, 'label' => $label, 'err' => $e->getMessage()];
    }
}

$elapsed = round((microtime(true) - LARAVEL_START) * 1000);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<title>پاک‌سازی کش</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: Tahoma, sans-serif; background: #f3f4f6; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
  .card { background: #fff; border-radius: 12px; padding: 32px 40px; box-shadow: 0 4px 24px rgba(0,0,0,.10); min-width: 360px; max-width: 500px; width: 100%; }
  h1 { font-size: 20px; margin-bottom: 24px; color: #111; border-bottom: 1px solid #e5e7eb; padding-bottom: 12px; }
  .row { display: flex; align-items: center; gap: 12px; padding: 10px 0; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
  .row:last-child { border-bottom: none; }
  .icon { font-size: 18px; flex-shrink: 0; }
  .label { flex: 1; color: #374151; }
  .cmd { font-size: 11px; color: #9ca3af; direction: ltr; }
  .badge-ok  { background: #d1fae5; color: #065f46; border-radius: 6px; padding: 2px 10px; font-size: 12px; }
  .badge-err { background: #fee2e2; color: #991b1b; border-radius: 6px; padding: 2px 10px; font-size: 12px; }
  .footer { margin-top: 20px; font-size: 12px; color: #9ca3af; text-align: center; }
</style>
</head>
<body>
<div class="card">
  <h1>🧹 پاک‌سازی کش سایت</h1>
  <?php foreach ($results as $r): ?>
  <div class="row">
    <span class="icon"><?= $r['ok'] ? '✅' : '❌' ?></span>
    <div style="flex:1">
      <div class="label"><?= htmlspecialchars($r['label']) ?></div>
      <div class="cmd">php artisan <?= htmlspecialchars($r['cmd']) ?></div>
      <?php if (!$r['ok']): ?>
        <div style="font-size:11px;color:#dc2626;margin-top:2px;"><?= htmlspecialchars($r['err']) ?></div>
      <?php endif ?>
    </div>
    <span class="<?= $r['ok'] ? 'badge-ok' : 'badge-err' ?>"><?= $r['ok'] ? 'پاک شد' : 'خطا' ?></span>
  </div>
  <?php endforeach ?>
  <div class="footer">زمان اجرا: <?= $elapsed ?> میلی‌ثانیه</div>
</div>
</body>
</html>
