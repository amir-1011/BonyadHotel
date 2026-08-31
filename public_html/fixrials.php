<?php
/**
 * تبدیل مبالغ دیتابیس از تومان به ریال (×۱۰)
 *
 * پیش‌نمایش:  /fixrials.php?key=YOUR_SECRET
 * اجرا:        /fixrials.php?key=YOUR_SECRET&run=1
 *
 * ⚠️ فقط یک‌بار اجرا کنید — اجرای مجدد دوباره ×۱۰ می‌کند.
 * ⚠️ قبل از اجرا از دیتابیس بکاپ بگیرید.
 * ⚠️ مقدار PLATFORM_COMMISSION_FIXED_AMOUNT در .env را هم ×۱۰ کنید.
 * ⚠️ بعد از استفاده این فایل را حذف یا نام آن را تغییر دهید.
 */

define('SECRET_KEY', 'fixrials2026');
define('SCALE_FACTOR', 10);

if (($_GET['key'] ?? '') !== SECRET_KEY) {
    http_response_code(403);
    exit('<div style="font-family:Tahoma,sans-serif;direction:rtl;padding:40px;color:#c00">
            ❌ دسترسی غیرمجاز — کلید امنیتی اشتباه است
          </div>');
}

define('LARAVEL_START', microtime(true));
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$dryRun = !isset($_GET['run']);
$errorMessage = '';
$results = [];
$jsonResults = [];
$widenResults = [];

/** @var array<string, list<string>> */
$moneyColumns = [
    'accommodations'                  => ['price_per_night'],
    'room_types'                      => ['extra_capacity_price'],
    'room_rates'                      => ['price_per_night', 'breakfast_price_per_person'],
    'room_type_daily_overrides'       => ['custom_price'],
    'room_type_weekly_price_rules'    => ['custom_price'],
    'room_rate_daily_price_overrides' => ['custom_price'],
    'room_rate_weekly_price_rules'    => ['custom_price'],
    'bookings'                        => ['extra_guests_price', 'base_price', 'services_subtotal', 'discount_amount', 'total_price', 'employer_debt_amount'],
    'booking_services'                => ['unit_price', 'discount_amount', 'total', 'manual_price_adjustment'],
    'booking_beneficiary_costs'       => ['debt_amount'],
    'booking_payment_records'         => ['amount', 'amount_delta'],
    'programs'                        => ['base_price', 'services_subtotal', 'discount_amount', 'deposit_amount', 'total_amount'],
    'program_beneficiary_costs'       => ['debt_amount'],
    'service_catalogs'                => ['default_price'],
    'service_catalog_variants'        => ['price'],
    'medical_accommodation_tariffs'   => ['nightly_rate', 'companion_nightly_rate'],
    'platform_commission_entries'     => ['transaction_amount', 'commission_cap', 'commission_amount'],
    'cancellation_requests'           => ['refund_amount', 'settled_amount'],
];

/** @var array<string, list<string>> */
$jsonMoneyKeys = [
    'pay_amount',
    'discount_amount',
    'nightly_rate',
    'companion_nightly_rate',
    'patient_total',
    'companion_total',
    'stay_total',
    'total_price',
    'base_price',
    'fixed_commission',
    'services_total',
    'accommodation_amount',
    'platform_commission_amount',
    'subtotal_before_commission',
    'previous_transaction_amount',
    'previous_net_commission',
    'new_transaction_amount',
    'new_target_commission',
    'reversed_net_commission',
    'transaction_amount',
];

/** @var array<string, string> */
$jsonColumns = [
    'veteran_groups'                 => 'accommodation_discount_tiers',
    'veteran_group_service_discounts' => 'discount_tiers',
    'bookings'                       => 'medical_tariff_snapshot',
    'platform_commission_entries'    => 'meta',
];

/** UINT max — مقادیر بزرگ‌تر از این پس از ×۱۰ سرریز می‌کنند */
const UINT32_MAX = 4294967295;

/**
 * @return array{data_type:string, column_type:string, is_nullable:bool, column_default:mixed}|null
 */
function getColumnMeta(string $table, string $column): ?array
{
    if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
        return null;
    }

    $db = DB::getDatabaseName();
    $row = DB::selectOne(
        'SELECT DATA_TYPE, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        [$db, $table, $column],
    );

    if (!$row) {
        return null;
    }

    return [
        'data_type'      => strtolower((string) $row->DATA_TYPE),
        'column_type'    => strtolower((string) $row->COLUMN_TYPE),
        'is_nullable'    => strtoupper((string) $row->IS_NULLABLE) === 'YES',
        'column_default' => $row->COLUMN_DEFAULT,
    ];
}

function columnNeedsWiden(array $meta): bool
{
    return in_array($meta['data_type'], ['tinyint', 'smallint', 'mediumint', 'int'], true);
}

function targetSqlType(array $meta): string
{
    $unsigned = str_contains($meta['column_type'], 'unsigned');

    return $unsigned ? 'BIGINT UNSIGNED' : 'BIGINT';
}

function formatDefaultClause(array $meta): string
{
    $default = $meta['column_default'];

    if ($default === null) {
        return '';
    }

    if (is_numeric($default)) {
        return ' DEFAULT ' . $default;
    }

    $default = (string) $default;

    if (strtoupper($default) === 'NULL') {
        return ' DEFAULT NULL';
    }

    if (str_contains(strtoupper($default), 'CURRENT_TIMESTAMP')) {
        return ' DEFAULT ' . $default;
    }

    return ' DEFAULT ' . DB::getPdo()->quote($default);
}

function widenColumn(string $table, string $column): bool
{
    $meta = getColumnMeta($table, $column);
    if ($meta === null || !columnNeedsWiden($meta)) {
        return false;
    }

    $nullSql = $meta['is_nullable'] ? 'NULL' : 'NOT NULL';

    DB::statement(sprintf(
        'ALTER TABLE `%s` MODIFY `%s` %s %s%s',
        $table,
        $column,
        targetSqlType($meta),
        $nullSql,
        formatDefaultClause($meta),
    ));

    return true;
}

/**
 * @return array{rows:int, max_value:int, overflow_after_scale:int}
 */
function previewOverflowRisk(string $table, string $column): array
{
    if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
        return ['rows' => 0, 'max_value' => 0, 'overflow_after_scale' => 0];
    }

    $meta = getColumnMeta($table, $column);
    if ($meta === null || !columnNeedsWiden($meta)) {
        return ['rows' => 0, 'max_value' => 0, 'overflow_after_scale' => 0];
    }

    $threshold = intdiv(UINT32_MAX, SCALE_FACTOR);
    $row = DB::table($table)
        ->selectRaw('COUNT(*) AS row_count, COALESCE(MAX(CAST(`' . $column . '` AS UNSIGNED)), 0) AS max_value')
        ->where($column, '>', $threshold)
        ->first();

    return [
        'rows'                 => (int) ($row->row_count ?? 0),
        'max_value'            => (int) DB::table($table)->max($column),
        'overflow_after_scale' => (int) ($row->row_count ?? 0),
    ];
}

function scaleNumericMoney(int|float|string|null $value): int
{
    if ($value === null || $value === '') {
        return 0;
    }

    return (int) $value * SCALE_FACTOR;
}

/**
 * @param  mixed  $data
 * @param  list<string>  $moneyKeys
 * @return mixed
 */
function scaleJsonMoney(mixed $data, array $moneyKeys): mixed
{
    if (!is_array($data)) {
        return $data;
    }

    $scaled = [];

    foreach ($data as $key => $value) {
        if (is_string($key) && in_array($key, $moneyKeys, true) && is_numeric($value)) {
            $scaled[$key] = scaleNumericMoney($value);
            continue;
        }

        $scaled[$key] = scaleJsonMoney($value, $moneyKeys);
    }

    return $scaled;
}

/**
 * @return array{rows:int, sum_before:int, sum_after:int, skipped:bool, reason?:string}
 */
function previewColumnUpdate(string $table, string $column): array
{
    if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
        return ['rows' => 0, 'sum_before' => 0, 'sum_after' => 0, 'skipped' => true, 'reason' => 'جدول/ستون وجود ندارد'];
    }

    $row = DB::table($table)
        ->selectRaw('COUNT(*) AS row_count, COALESCE(SUM(CAST(`' . $column . '` AS SIGNED)), 0) AS total_sum')
        ->whereNotNull($column)
        ->where($column, '!=', 0)
        ->first();

    $sumBefore = (int) ($row->total_sum ?? 0);

    return [
        'rows'       => (int) ($row->row_count ?? 0),
        'sum_before' => $sumBefore,
        'sum_after'  => $sumBefore * SCALE_FACTOR,
        'skipped'    => false,
    ];
}

function applyColumnUpdate(string $table, string $column): int
{
    if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
        return 0;
    }

    return DB::table($table)
        ->whereNotNull($column)
        ->where($column, '!=', 0)
        ->update([
            $column => DB::raw('CAST(`' . $column . '` AS SIGNED) * ' . SCALE_FACTOR),
        ]);
}

/**
 * @return array{rows:int, changed:int, skipped:bool, reason?:string}
 */
function previewJsonColumn(string $table, string $column, array $moneyKeys): array
{
    if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
        return ['rows' => 0, 'changed' => 0, 'skipped' => true, 'reason' => 'جدول/ستون وجود ندارد'];
    }

    $rows = DB::table($table)
        ->whereNotNull($column)
        ->where($column, '!=', '')
        ->where($column, '!=', 'null')
        ->get(['id', $column]);

    $changed = 0;

    foreach ($rows as $row) {
        $decoded = json_decode((string) $row->{$column}, true);
        if (!is_array($decoded)) {
            continue;
        }

        $scaled = scaleJsonMoney($decoded, $moneyKeys);
        if ($scaled !== $decoded) {
            $changed++;
        }
    }

    return [
        'rows'    => $rows->count(),
        'changed' => $changed,
        'skipped' => false,
    ];
}

function applyJsonColumn(string $table, string $column, array $moneyKeys): int
{
    if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
        return 0;
    }

    $updated = 0;

    DB::table($table)
        ->whereNotNull($column)
        ->where($column, '!=', '')
        ->orderBy('id')
        ->chunkById(200, function ($rows) use ($table, $column, $moneyKeys, &$updated): void {
            foreach ($rows as $row) {
                $decoded = json_decode((string) $row->{$column}, true);
                if (!is_array($decoded)) {
                    continue;
                }

                $scaled = scaleJsonMoney($decoded, $moneyKeys);
                if ($scaled === $decoded) {
                    continue;
                }

                DB::table($table)
                    ->where('id', $row->id)
                    ->update([$column => json_encode($scaled, JSON_UNESCAPED_UNICODE)]);

                $updated++;
            }
        });

    return $updated;
}

try {
    foreach ($moneyColumns as $table => $columns) {
        foreach ($columns as $column) {
            $meta = getColumnMeta($table, $column);
            $overflow = previewOverflowRisk($table, $column);
            $widenResults[] = [
                'table'                => $table,
                'column'               => $column,
                'current_type'         => $meta['column_type'] ?? '—',
                'needs_widen'          => $meta !== null && columnNeedsWiden($meta),
                'target_type'          => $meta !== null && columnNeedsWiden($meta) ? targetSqlType($meta) : null,
                'overflow_rows'        => $overflow['overflow_after_scale'],
                'max_value'            => $overflow['max_value'],
            ];

            $preview = previewColumnUpdate($table, $column);
            $results[] = [
                'kind'   => 'column',
                'table'  => $table,
                'column' => $column,
                ...$preview,
            ];
        }
    }

    foreach ($jsonColumns as $table => $column) {
        $preview = previewJsonColumn($table, $column, $jsonMoneyKeys);
        $jsonResults[] = [
            'table'  => $table,
            'column' => $column,
            ...$preview,
        ];
    }

    if (!$dryRun) {
        // DDL در MySQL تراکنش را commit می‌کند؛ ابتدا همه ستون‌ها را گسترش می‌دهیم.
        foreach ($widenResults as &$widenRow) {
            if (!$widenRow['needs_widen']) {
                $widenRow['widened'] = false;
                continue;
            }

            $widenRow['widened'] = widenColumn($widenRow['table'], $widenRow['column']);
        }
        unset($widenRow);

        DB::transaction(function () use ($moneyColumns, $jsonColumns, $jsonMoneyKeys, &$results, &$jsonResults): void {
            foreach ($moneyColumns as $table => $columns) {
                foreach ($columns as $column) {
                    $affected = applyColumnUpdate($table, $column);
                    foreach ($results as &$result) {
                        if ($result['table'] === $table && $result['column'] === $column && $result['kind'] === 'column') {
                            $result['affected'] = $affected;
                        }
                    }
                    unset($result);
                }
            }

            foreach ($jsonColumns as $table => $column) {
                $affected = applyJsonColumn($table, $column, $jsonMoneyKeys);
                foreach ($jsonResults as &$jsonResult) {
                    if ($jsonResult['table'] === $table && $jsonResult['column'] === $column) {
                        $jsonResult['affected'] = $affected;
                    }
                }
                unset($jsonResult);
            }
        });
    }
} catch (Throwable $e) {
    $errorMessage = $e->getMessage();
}

$totalWidenNeeded = count(array_filter($widenResults, fn (array $row) => $row['needs_widen']));
$totalOverflowRows = array_sum(array_map(fn (array $row) => $row['overflow_rows'], $widenResults));

$totalColumnRows = array_sum(array_map(
    fn (array $row) => $row['skipped'] ? 0 : $row['rows'],
    $results,
));
$totalJsonChanged = array_sum(array_map(
    fn (array $row) => $row['skipped'] ? 0 : $row['changed'],
    $jsonResults,
));
$modeLabel = $dryRun ? 'پیش‌نمایش (بدون تغییر)' : 'اجرا شد ✓';

function faNum(int|float $n): string
{
    return number_format((int) $n);
}

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>تبدیل تومان به ریال</title>
<style>
  * { box-sizing: border-box; }
  body { font-family: Tahoma, sans-serif; background: #f3f4f6; margin: 0; padding: 24px; color: #111827; }
  .wrap { max-width: 1100px; margin: 0 auto; }
  .card { background: #fff; border-radius: 12px; padding: 28px 32px; box-shadow: 0 4px 24px rgba(0,0,0,.08); margin-bottom: 20px; }
  h1 { font-size: 22px; margin: 0 0 8px; }
  h2 { font-size: 16px; margin: 0 0 12px; }
  .subtitle { color: #6b7280; font-size: 13px; line-height: 1.8; margin-bottom: 18px; }
  .stats { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 18px; }
  .stat { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px 14px; font-size: 13px; }
  .stat strong { display: block; font-size: 18px; margin-top: 4px; }
  .actions { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 18px; }
  .btn { display: inline-block; padding: 10px 16px; border-radius: 8px; text-decoration: none; font-size: 13px; font-weight: 600; }
  .btn-primary { background: #2563eb; color: #fff; }
  .btn-secondary { background: #e5e7eb; color: #111827; }
  .btn-danger { background: #dc2626; color: #fff; }
  .note { background: #fff7ed; border: 1px solid #fed7aa; border-radius: 8px; padding: 12px 14px; font-size: 12px; color: #9a3412; line-height: 1.9; margin-bottom: 18px; }
  .alert-error { background: #fee2e2; border: 1px solid #fecaca; color: #991b1b; border-radius: 8px; padding: 12px 14px; margin-bottom: 18px; font-size: 13px; }
  .alert-success { background: #d1fae5; border: 1px solid #a7f3d0; color: #065f46; border-radius: 8px; padding: 12px 14px; margin-bottom: 18px; font-size: 13px; }
  table { width: 100%; border-collapse: collapse; font-size: 12px; }
  th, td { padding: 8px 10px; border-bottom: 1px solid #f0f0f0; text-align: right; vertical-align: top; }
  th { background: #f9fafb; font-size: 12px; color: #374151; position: sticky; top: 0; }
  .mono { direction: ltr; font-family: Consolas, monospace; text-align: left; }
  .badge { display: inline-block; border-radius: 999px; padding: 2px 8px; font-size: 11px; }
  .badge-muted { background: #f3f4f6; color: #4b5563; }
  .badge-warn { background: #fef3c7; color: #92400e; }
  .table-wrap { max-height: 480px; overflow: auto; border: 1px solid #e5e7eb; border-radius: 8px; }
  .footer { text-align: center; color: #9ca3af; font-size: 12px; margin-top: 8px; }
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <h1>💱 تبدیل مبالغ دیتابیس: تومان → ریال (×<?= SCALE_FACTOR ?>)</h1>
    <p class="subtitle">
      حالت: <strong><?= htmlspecialchars($modeLabel) ?></strong><br>
      همه ستون‌های قیمتی عددی و فیلدهای JSON مرتبط یک صفر اضافه می‌شوند.
    </p>

    <?php if ($errorMessage): ?>
      <div class="alert-error">❌ <?= htmlspecialchars($errorMessage) ?></div>
    <?php elseif (!$dryRun): ?>
      <div class="alert-success">✅ تبدیل با موفقیت انجام شد. کش برنامه را پاک کنید و مقدار کارمزد ثابت در <span class="mono">.env</span> را هم بررسی کنید.</div>
    <?php endif; ?>

    <div class="stats">
      <div class="stat">ستون‌های عددی<strong><?= count($results) ?></strong></div>
      <div class="stat">نیاز به گسترش نوع<strong><?= faNum($totalWidenNeeded) ?></strong></div>
      <div class="stat">ردیف‌های پرریسک<strong><?= faNum($totalOverflowRows) ?></strong></div>
      <div class="stat">ردیف‌های غیرصفر<strong><?= faNum($totalColumnRows) ?></strong></div>
      <div class="stat">رکوردهای JSON<strong><?= faNum($totalJsonChanged) ?></strong></div>
      <div class="stat">ضریب تبدیل<strong>×<?= SCALE_FACTOR ?></strong></div>
    </div>

    <div class="note">
      <strong>قبل از اجرا:</strong> حتماً بکاپ کامل دیتابیس بگیرید.<br>
      <strong>فقط یک‌بار اجرا کنید</strong> — اجرای دوباره همه مبالغ را دوباره ×۱۰ می‌کند.<br>
      <strong>اگر اجرای قبلی نیمه‌کاره ماند:</strong> از بکاپ بازیابی کنید؛ بعضی جداول ممکن است قبلاً تبدیل شده باشند.<br>
      ستون‌های <span class="mono">INT</span> قبل از ضرب به <span class="mono">BIGINT</span> گسترش داده می‌شوند تا خطای سرریز رخ ندهد.<br>
      <strong>بعد از اجرا:</strong> مقدار <span class="mono">PLATFORM_COMMISSION_FIXED_AMOUNT</span> در فایل <span class="mono">.env</span> را هم ×۱۰ کنید (مثلاً ۵۰٬۰۰۰ → ۵۰۰٬۰۰۰).<br>
      <strong>امنیت:</strong> پس از اتمام کار این فایل را حذف کنید.
    </div>

    <div class="actions">
      <a class="btn btn-secondary" href="?key=<?= urlencode(SECRET_KEY) ?>">پیش‌نمایش</a>
      <?php if ($dryRun): ?>
        <a class="btn btn-danger" href="?key=<?= urlencode(SECRET_KEY) ?>&run=1"
           onclick="return confirm('همه مبالغ دیتابیس ×۱۰ می‌شوند. بکاپ گرفته‌اید؟');">
          اجرای واقعی
        </a>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <h2>گسترش نوع ستون (INT → BIGINT)</h2>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>جدول</th>
            <th>ستون</th>
            <th>نوع فعلی</th>
            <th>نوع هدف</th>
            <th>بیشینه فعلی</th>
            <th>ردیف‌های پرریسک</th>
            <th>وضعیت</th>
            <?php if (!$dryRun): ?><th>گسترش یافت</th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($widenResults as $row): ?>
            <tr>
              <td class="mono"><?= htmlspecialchars($row['table']) ?></td>
              <td class="mono"><?= htmlspecialchars($row['column']) ?></td>
              <td class="mono"><?= htmlspecialchars($row['current_type']) ?></td>
              <td class="mono"><?= htmlspecialchars($row['target_type'] ?? '—') ?></td>
              <td class="mono"><?= faNum($row['max_value']) ?></td>
              <td><?= faNum($row['overflow_rows']) ?></td>
              <td>
                <?php if (!$row['needs_widen']): ?>
                  <span class="badge badge-muted">نیازی نیست</span>
                <?php elseif ($row['overflow_rows'] > 0): ?>
                  <span class="badge badge-warn">نیازمند گسترش</span>
                <?php else: ?>
                  <span class="badge badge-muted">گسترش پیشگیرانه</span>
                <?php endif; ?>
              </td>
              <?php if (!$dryRun): ?>
                <td><?= !empty($row['widened']) ? 'بله' : '—' ?></td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <h2>ستون‌های عددی</h2>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>جدول</th>
            <th>ستون</th>
            <th>ردیف‌های غیرصفر</th>
            <th>جمع فعلی</th>
            <th>جمع پس از تبدیل</th>
            <th>وضعیت</th>
            <?php if (!$dryRun): ?><th>به‌روزشده</th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($results as $row): ?>
            <tr>
              <td class="mono"><?= htmlspecialchars($row['table']) ?></td>
              <td class="mono"><?= htmlspecialchars($row['column']) ?></td>
              <td><?= faNum($row['rows']) ?></td>
              <td class="mono"><?= faNum($row['sum_before']) ?></td>
              <td class="mono"><?= faNum($row['sum_after']) ?></td>
              <td>
                <?php if ($row['skipped']): ?>
                  <span class="badge badge-warn"><?= htmlspecialchars($row['reason'] ?? 'رد شد') ?></span>
                <?php elseif ($row['rows'] === 0): ?>
                  <span class="badge badge-muted">بدون داده</span>
                <?php else: ?>
                  <span class="badge badge-muted">آماده</span>
                <?php endif; ?>
              </td>
              <?php if (!$dryRun): ?>
                <td><?= faNum((int) ($row['affected'] ?? 0)) ?></td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <h2>فیلدهای JSON</h2>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>جدول</th>
            <th>ستون</th>
            <th>رکوردهای JSON</th>
            <th>نیازمند تغییر</th>
            <th>وضعیت</th>
            <?php if (!$dryRun): ?><th>به‌روزشده</th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($jsonResults as $row): ?>
            <tr>
              <td class="mono"><?= htmlspecialchars($row['table']) ?></td>
              <td class="mono"><?= htmlspecialchars($row['column']) ?></td>
              <td><?= faNum($row['rows']) ?></td>
              <td><?= faNum($row['changed']) ?></td>
              <td>
                <?php if ($row['skipped']): ?>
                  <span class="badge badge-warn"><?= htmlspecialchars($row['reason'] ?? 'رد شد') ?></span>
                <?php elseif ($row['changed'] === 0): ?>
                  <span class="badge badge-muted">بدون داده</span>
                <?php else: ?>
                  <span class="badge badge-muted">آماده</span>
                <?php endif; ?>
              </td>
              <?php if (!$dryRun): ?>
                <td><?= faNum((int) ($row['affected'] ?? 0)) ?></td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="footer">کلید امنیتی: <span class="mono"><?= htmlspecialchars(SECRET_KEY) ?></span></div>
</div>
</body>
</html>
