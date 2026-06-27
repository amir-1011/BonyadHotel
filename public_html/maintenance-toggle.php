<?php
/**
 * روشن/خاموش کردن حالت بروزرسانی سایت
 * وضعیت در storage/framework/site_maintenance.json ذخیره می‌شود (بدون تغییر .env)
 *
 * استفاده: /maintenance-toggle.php?key=YOUR_SECRET
 */

// ── کلید امنیتی ── قبل از استفاده عوض کنید ───────────────────────────────
define('SECRET_KEY', 'maintenance1234');

if (($_GET['key'] ?? '') !== SECRET_KEY) {
    http_response_code(403);
    exit('<div style="font-family:sans-serif;direction:rtl;padding:40px;color:#c00">
            ❌ دسترسی غیرمجاز — کلید امنیتی اشتباه است
          </div>');
}

$startedAt = microtime(true);
$flagPath = realpath(__DIR__ . '/../storage/framework/site_maintenance.json')
    ?: __DIR__ . '/../storage/framework/site_maintenance.json';
$error = null;
$oldValue = null;
$newValue = null;

function maintenance_read_state(string $flagPath): bool
{
    if (is_readable($flagPath)) {
        $data = json_decode((string) file_get_contents($flagPath), true);
        if (is_array($data) && array_key_exists('enabled', $data)) {
            return (bool) $data['enabled'];
        }
    }

    $envPath = realpath(__DIR__ . '/../.env');
    if (! $envPath || ! is_readable($envPath)) {
        return false;
    }

    foreach (file($envPath, FILE_IGNORE_NEW_LINES) as $line) {
        if (preg_match('/^UNDER_MAINTENANCE\s*=\s*(.*)$/i', $line, $matches)) {
            return filter_var(trim($matches[1], " \t\"'"), FILTER_VALIDATE_BOOLEAN);
        }
    }

    return false;
}

try {
    $oldValue = maintenance_read_state($flagPath);
    $newValue = ! $oldValue;

    $dir = dirname($flagPath);
    if (! is_dir($dir) && ! mkdir($dir, 0755, true)) {
        throw new RuntimeException('پوشه storage/framework قابل ایجاد نیست.');
    }

    $written = file_put_contents(
        $flagPath,
        json_encode(['enabled' => $newValue], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL,
        LOCK_EX
    );

    if ($written === false) {
        throw new RuntimeException('نوشتن فایل وضعیت بروزرسانی با خطا مواجه شد.');
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$elapsed = round((microtime(true) - $startedAt) * 1000);
$toggleUrl = 'maintenance-toggle.php?key=' . urlencode($_GET['key']);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>حالت بروزرسانی سایت</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: Tahoma, sans-serif; background: #f3f4f6; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 16px; }
  .card { background: #fff; border-radius: 12px; padding: 32px 40px; box-shadow: 0 4px 24px rgba(0,0,0,.10); min-width: 360px; max-width: 520px; width: 100%; }
  h1 { font-size: 20px; margin-bottom: 24px; color: #111; border-bottom: 1px solid #e5e7eb; padding-bottom: 12px; }
  .row { display: flex; align-items: center; gap: 12px; padding: 10px 0; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
  .row:last-child { border-bottom: none; }
  .icon { font-size: 18px; flex-shrink: 0; }
  .label { flex: 1; color: #374151; }
  .cmd { font-size: 11px; color: #9ca3af; direction: ltr; text-align: left; word-break: break-all; }
  .badge-on  { background: #fef3c7; color: #92400e; border-radius: 6px; padding: 2px 10px; font-size: 12px; }
  .badge-off { background: #d1fae5; color: #065f46; border-radius: 6px; padding: 2px 10px; font-size: 12px; }
  .badge-err { background: #fee2e2; color: #991b1b; border-radius: 6px; padding: 2px 10px; font-size: 12px; }
  .alert { margin-top: 16px; padding: 12px 14px; border-radius: 8px; font-size: 13px; line-height: 1.8; }
  .alert-err { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
  .alert-ok { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }
  .btn { display: inline-block; margin-top: 20px; padding: 12px 20px; background: #1e40af; color: #fff; text-decoration: none; border-radius: 8px; font-size: 14px; font-weight: 600; }
  .btn:hover { background: #1e3a8a; }
  .footer { margin-top: 20px; font-size: 12px; color: #9ca3af; text-align: center; }
</style>
</head>
<body>
<div class="card">
  <h1>🛠 حالت بروزرسانی سایت</h1>

  <?php if ($error): ?>
    <div class="alert alert-err"><?= htmlspecialchars($error) ?></div>
  <?php else: ?>
    <div class="alert alert-ok">
      <?php if ($oldValue): ?>
        حالت بروزرسانی <strong>خاموش</strong> شد. سایت اکنون در دسترس است.
      <?php else: ?>
        حالت بروزرسانی <strong>روشن</strong> شد. بازدیدکنندگان صفحه «در حال بروزرسانی» را می‌بینند.
      <?php endif; ?>
    </div>

    <div class="row">
      <span class="icon">📝</span>
      <div style="flex:1">
        <div class="label">مقدار قبلی</div>
        <div class="cmd">enabled=<?= $oldValue ? 'true' : 'false' ?></div>
      </div>
      <span class="<?= $oldValue ? 'badge-on' : 'badge-off' ?>"><?= $oldValue ? 'فعال' : 'غیرفعال' ?></span>
    </div>

    <div class="row">
      <span class="icon">✅</span>
      <div style="flex:1">
        <div class="label">مقدار جدید</div>
        <div class="cmd">enabled=<?= $newValue ? 'true' : 'false' ?></div>
      </div>
      <span class="<?= $newValue ? 'badge-on' : 'badge-off' ?>"><?= $newValue ? 'فعال' : 'غیرفعال' ?></span>
    </div>

    <div class="row">
      <span class="icon">💾</span>
      <div style="flex:1">
        <div class="label">فایل وضعیت</div>
        <div class="cmd">storage/framework/site_maintenance.json</div>
      </div>
      <span class="badge-off">ذخیره شد</span>
    </div>

    <a class="btn" href="<?= htmlspecialchars($toggleUrl) ?>">دوباره toggle کن</a>
  <?php endif; ?>

  <div class="footer">زمان اجرا: <?= $elapsed ?> میلی‌ثانیه</div>
</div>
</body>
</html>
