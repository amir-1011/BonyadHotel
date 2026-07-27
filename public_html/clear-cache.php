<?php
/**
 * پاک‌کننده دستی کش سرور + به‌روزرسانی نسخه فایل‌های فرانت‌اند
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

use App\Support\AssetVersion;

// ── اجرای دستورات پاک‌سازی ────────────────────────────────────────────────
$results = [];

$commands = [
    'optimize:clear' => 'همه کش‌های لاراول (view, config, route, event, ...)',
];

foreach ($commands as $cmd => $label) {
    try {
        Artisan::call($cmd);
        $results[] = ['ok' => true, 'cmd' => $cmd, 'label' => $label];
    } catch (Throwable $e) {
        $results[] = ['ok' => false, 'cmd' => $cmd, 'label' => $label, 'err' => $e->getMessage()];
    }
}

// ── به‌روزرسانی نسخه فایل‌های استاتیک (cache bust) ─────────────────────────
$previousVersion = AssetVersion::current();

try {
    $newVersion = AssetVersion::bump();
    $results[] = [
        'ok' => true,
        'cmd' => 'asset_version',
        'label' => 'نسخه فایل‌های CSS/JS استاتیک (vendor, logo, images)',
        'detail' => $previousVersion.' → '.$newVersion,
    ];
} catch (Throwable $e) {
    $results[] = [
        'ok' => false,
        'cmd' => 'asset_version',
        'label' => 'نسخه فایل‌های CSS/JS استاتیک',
        'err' => $e->getMessage(),
    ];
}

// ── پاک‌سازی OPcache (در صورت فعال بودن روی هاست) ─────────────────────────
if (function_exists('opcache_reset')) {
    $opcacheOk = @opcache_reset();
    $results[] = [
        'ok' => (bool) $opcacheOk,
        'cmd' => 'opcache_reset',
        'label' => 'کش PHP (OPcache)',
        'detail' => $opcacheOk ? 'پاک شد' : 'غیرفعال یا بدون دسترسی',
    ];
}

$elapsed = round((microtime(true) - LARAVEL_START) * 1000);
$assetVersion = AssetVersion::current();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<title>پاک‌سازی کش</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: Tahoma, sans-serif; background: #f3f4f6; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px; }
  .card { background: #fff; border-radius: 12px; padding: 32px 40px; box-shadow: 0 4px 24px rgba(0,0,0,.10); min-width: 360px; max-width: 560px; width: 100%; }
  h1 { font-size: 20px; margin-bottom: 8px; color: #111; }
  .subtitle { font-size: 13px; color: #6b7280; margin-bottom: 20px; line-height: 1.7; }
  .row { display: flex; align-items: flex-start; gap: 12px; padding: 10px 0; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
  .row:last-child { border-bottom: none; }
  .icon { font-size: 18px; flex-shrink: 0; margin-top: 1px; }
  .label { flex: 1; color: #374151; }
  .cmd { font-size: 11px; color: #9ca3af; direction: ltr; margin-top: 2px; }
  .detail { font-size: 12px; color: #059669; margin-top: 3px; }
  .badge-ok  { background: #d1fae5; color: #065f46; border-radius: 6px; padding: 2px 10px; font-size: 12px; white-space: nowrap; }
  .badge-err { background: #fee2e2; color: #991b1b; border-radius: 6px; padding: 2px 10px; font-size: 12px; white-space: nowrap; }
  .footer { margin-top: 20px; font-size: 12px; color: #9ca3af; text-align: center; }
  .note { margin-top: 16px; padding: 12px 14px; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; font-size: 12px; color: #1e40af; line-height: 1.8; }
  .version-box { display: inline-block; background: #111827; color: #f9fafb; padding: 2px 8px; border-radius: 4px; font-family: monospace; direction: ltr; }
</style>
</head>
<body>
<div class="card">
  <h1>🧹 پاک‌سازی کش سایت</h1>
  <p class="subtitle">
    کش سرور پاک شد و نسخه فایل‌های فرانت‌اند به
    <span class="version-box"><?= htmlspecialchars($assetVersion) ?></span>
    تغییر کرد. کاربران در بازدید بعدی CSS/JS جدید دریافت می‌کنند.
  </p>
  <?php foreach ($results as $r): ?>
  <div class="row">
    <span class="icon"><?= $r['ok'] ? '✅' : '❌' ?></span>
    <div style="flex:1">
      <div class="label"><?= htmlspecialchars($r['label']) ?></div>
      <?php if (!empty($r['cmd']) && $r['cmd'] !== 'asset_version'): ?>
        <div class="cmd">php artisan <?= htmlspecialchars($r['cmd']) ?></div>
      <?php endif ?>
      <?php if (!empty($r['detail'])): ?>
        <div class="detail"><?= htmlspecialchars($r['detail']) ?></div>
      <?php endif ?>
      <?php if (!$r['ok'] && !empty($r['err'])): ?>
        <div style="font-size:11px;color:#dc2626;margin-top:2px;"><?= htmlspecialchars($r['err']) ?></div>
      <?php endif ?>
    </div>
    <span class="<?= $r['ok'] ? 'badge-ok' : 'badge-err' ?>"><?= $r['ok'] ? 'انجام شد' : 'خطا' ?></span>
  </div>
  <?php endforeach ?>
  <div class="note">
    <strong>نکته:</strong> فایل‌های Vite (پوشه <code>build/assets</code>) با هر بار <code>npm run build</code> خودکار نسخه جدید می‌گیرند.
    اگر تغییرات JS/CSS دیده نمی‌شود، ابتدا build جدید را آپلود کنید، سپس این صفحه را اجرا کنید.
    اگر CDN (مثل Cloudflare) دارید، کش آن را هم از پنل هاست پاک کنید.
  </div>
  <div class="footer">زمان اجرا: <?= $elapsed ?> میلی‌ثانیه</div>
</div>
</body>
</html>
