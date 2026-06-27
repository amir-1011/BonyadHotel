<?php
/**
 * ایجاد لینک symbolic برای storage
 * برای امنیت بیشتر این فایل را بعد از استفاده پاک یا نام آن را تغییر دهید
 */

// ── کلید امنیتی ── قبل از استفاده عوض کنید ───────────────────────────────
define('SECRET_KEY', 'storage1234');

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

// ── اجرای storage:link ────────────────────────────────────────────────────
$ok = false;
$output = '';
$error = null;

try {
    Artisan::call('storage:link');
    $output = trim(Artisan::output());
    $ok = true;
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$linkPath = __DIR__ . '/storage';
$linkExists = is_link($linkPath) || is_dir($linkPath);
$elapsed = round((microtime(true) - LARAVEL_START) * 1000);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<title>لینک Storage</title>
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
  .output { margin-top: 16px; padding: 12px; background: #f9fafb; border-radius: 8px; font-size: 12px; color: #4b5563; direction: ltr; text-align: left; white-space: pre-wrap; word-break: break-all; }
  .footer { margin-top: 20px; font-size: 12px; color: #9ca3af; text-align: center; }
</style>
</head>
<body>
<div class="card">
  <h1>🔗 لینک Storage</h1>
  <div class="row">
    <span class="icon"><?= $ok ? '✅' : '❌' ?></span>
    <div style="flex:1">
      <div class="label">ایجاد symbolic link</div>
      <div class="cmd">php artisan storage:link</div>
      <?php if ($error): ?>
        <div style="font-size:11px;color:#dc2626;margin-top:2px;"><?= htmlspecialchars($error) ?></div>
      <?php endif ?>
    </div>
    <span class="<?= $ok ? 'badge-ok' : 'badge-err' ?>"><?= $ok ? 'انجام شد' : 'خطا' ?></span>
  </div>
  <?php if ($output): ?>
    <div class="output"><?= htmlspecialchars($output) ?></div>
  <?php endif ?>
  <div class="row" style="margin-top:12px;border-bottom:none;">
    <span class="icon"><?= $linkExists ? '✅' : '⚠️' ?></span>
    <div style="flex:1">
      <div class="label">مسیر public/storage</div>
      <div class="cmd"><?= htmlspecialchars($linkPath) ?></div>
    </div>
    <span class="<?= $linkExists ? 'badge-ok' : 'badge-err' ?>"><?= $linkExists ? 'موجود' : 'ناموجود' ?></span>
  </div>
  <div class="footer">زمان اجرا: <?= $elapsed ?> میلی‌ثانیه</div>
</div>
</body>
</html>
