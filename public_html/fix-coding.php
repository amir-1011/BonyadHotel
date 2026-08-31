<?php
/**
 * بازتولید کدینگ حسابداری کاربران بر اساس استان اقامتگاه
 *
 * کاربرد: پس از تغییر کدهای حسابداری استان‌ها، کدهای پرسنلی / کارفرما / ذینفع
 * از نو بر اساس استان واقعی اقامتگاه تولید و اختصاص داده می‌شوند.
 *
 * پیش‌نمایش:  /fix-coding.php?key=YOUR_SECRET
 * اجرا:        /fix-coding.php?key=YOUR_SECRET&run=1
 *
 * ⚠️ بعد از استفاده این فایل را حذف یا نام آن را تغییر دهید.
 */

define('SECRET_KEY', 'fixcoding2026');

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

use App\Models\ProgramBeneficiary;
use App\Models\ProgramEmployer;
use App\Models\Province;
use App\Models\User;
use App\Services\HostPersonnelCodeProvisioner;
use App\Services\ProvinceAccountingCodeService;
use App\Support\ProvinceAccountingIndicators;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

$dryRun = !isset($_GET['run']);
$hostProvisioner = app(HostPersonnelCodeProvisioner::class);
$codeService = app(ProvinceAccountingCodeService::class);

/** @return array<int, array<string, mixed>> */
function buildRegenerationPlan(
    HostPersonnelCodeProvisioner $hostProvisioner,
    ProvinceAccountingCodeService $codeService,
): array {
    $plan = [];

    $hostRoleId = Role::query()->where('name', 'host')->value('id');

    $hosts = User::query()
        ->when($hostRoleId, fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('roles.id', $hostRoleId)))
        ->with(['accommodations.city.province', 'accommodations.county.province', 'province'])
        ->orderBy('id')
        ->get();

    foreach ($hosts as $host) {
        $province = $hostProvisioner->resolveProvinceFromAccommodations($host);

        if (!$province) {
            $plan[] = [
                'type'       => 'host',
                'id'         => $host->id,
                'name'       => $host->name,
                'old_code'   => $host->personnel_code,
                'old_province' => $host->province?->displayLabel(),
                'new_province' => null,
                'new_code'   => null,
                'status'     => 'skipped',
                'message'    => 'استان از اقامتگاه قابل تشخیص نیست',
            ];
            continue;
        }

        try {
            $province = $codeService->ensureProvinceHasCode($province);
        } catch (Throwable $e) {
            $plan[] = [
                'type'       => 'host',
                'id'         => $host->id,
                'name'       => $host->name,
                'old_code'   => $host->personnel_code,
                'old_province' => $host->province?->displayLabel(),
                'new_province' => $province->displayLabel(),
                'new_code'   => null,
                'status'     => 'error',
                'message'    => $e->getMessage(),
            ];
            continue;
        }

        $plan[] = [
            'type'         => 'host',
            'id'           => $host->id,
            'entity_id'    => $host->id,
            'name'         => $host->name,
            'indicator'    => ProvinceAccountingIndicators::PERSONNEL,
            'province_id'  => $province->id,
            'old_code'     => $host->personnel_code,
            'old_province' => $host->province?->displayLabel(),
            'new_province' => $province->displayLabel(),
            'new_code'     => null,
            'status'       => 'pending',
            'message'      => '',
        ];
    }

    $employers = ProgramEmployer::query()
        ->with(['province', 'user'])
        ->orderBy('id')
        ->get();

    foreach ($employers as $employer) {
        $province = resolveEmployerProvince($employer, $hostProvisioner);

        if (!$province) {
            $plan[] = [
                'type'       => 'employer',
                'id'         => $employer->id,
                'name'       => $employer->name,
                'old_code'   => $employer->employer_code,
                'old_province' => $employer->province?->displayLabel(),
                'new_province' => null,
                'new_code'   => null,
                'status'     => 'skipped',
                'message'    => 'استان حسابداری مشخص نیست',
            ];
            continue;
        }

        try {
            $province = $codeService->ensureProvinceHasCode($province);
        } catch (Throwable $e) {
            $plan[] = [
                'type'       => 'employer',
                'id'         => $employer->id,
                'name'       => $employer->name,
                'old_code'   => $employer->employer_code,
                'old_province' => $employer->province?->displayLabel(),
                'new_province' => $province->displayLabel(),
                'new_code'   => null,
                'status'     => 'error',
                'message'    => $e->getMessage(),
            ];
            continue;
        }

        $plan[] = [
            'type'         => 'employer',
            'id'           => $employer->id,
            'entity_id'    => $employer->id,
            'name'         => $employer->name,
            'indicator'    => ProvinceAccountingIndicators::ORGANIZATION,
            'province_id'  => $province->id,
            'old_code'     => $employer->employer_code,
            'old_province' => $employer->province?->displayLabel(),
            'new_province' => $province->displayLabel(),
            'new_code'     => null,
            'status'       => 'pending',
            'message'      => '',
        ];
    }

    $beneficiaries = ProgramBeneficiary::query()
        ->with(['province', 'accommodation.city.province', 'accommodation.county.province', 'user'])
        ->orderBy('id')
        ->get();

    foreach ($beneficiaries as $beneficiary) {
        $province = resolveBeneficiaryProvince($beneficiary);

        if (!$province) {
            $plan[] = [
                'type'       => 'beneficiary',
                'id'         => $beneficiary->id,
                'name'       => $beneficiary->name,
                'old_code'   => $beneficiary->beneficiary_code,
                'old_province' => $beneficiary->province?->displayLabel(),
                'new_province' => null,
                'new_code'   => null,
                'status'     => 'skipped',
                'message'    => 'استان از اقامتگاه یا رکورد ذینفع قابل تشخیص نیست',
            ];
            continue;
        }

        try {
            $province = $codeService->ensureProvinceHasCode($province);
        } catch (Throwable $e) {
            $plan[] = [
                'type'       => 'beneficiary',
                'id'         => $beneficiary->id,
                'name'       => $beneficiary->name,
                'old_code'   => $beneficiary->beneficiary_code,
                'old_province' => $beneficiary->province?->displayLabel(),
                'new_province' => $province->displayLabel(),
                'new_code'   => null,
                'status'     => 'error',
                'message'    => $e->getMessage(),
            ];
            continue;
        }

        $plan[] = [
            'type'         => 'beneficiary',
            'id'           => $beneficiary->id,
            'entity_id'    => $beneficiary->id,
            'name'         => $beneficiary->name,
            'indicator'    => ProvinceAccountingIndicators::BENEFICIARY,
            'province_id'  => $province->id,
            'old_code'     => $beneficiary->beneficiary_code,
            'old_province' => $beneficiary->province?->displayLabel(),
            'new_province' => $province->displayLabel(),
            'new_code'     => null,
            'status'       => 'pending',
            'message'      => '',
        ];
    }

    assignPreviewCodes($plan, $codeService);

    return $plan;
}

function resolveEmployerProvince(ProgramEmployer $employer, HostPersonnelCodeProvisioner $hostProvisioner): ?Province
{
    if ($employer->user) {
        $fromAccommodation = $hostProvisioner->resolveProvinceFromAccommodations($employer->user);

        if ($fromAccommodation) {
            return $fromAccommodation;
        }
    }

    if ($employer->province_id) {
        return Province::query()->find($employer->province_id);
    }

    return null;
}

function resolveBeneficiaryProvince(ProgramBeneficiary $beneficiary): ?Province
{
    if ($beneficiary->accommodation) {
        $beneficiary->accommodation->loadMissing(['city.province', 'county.province']);
        $fromAccommodation = $beneficiary->accommodation->resolvedProvince();

        if ($fromAccommodation) {
            return $fromAccommodation;
        }
    }

    if ($beneficiary->province_id) {
        return Province::query()->find($beneficiary->province_id);
    }

    return null;
}

/** @param array<int, array<string, mixed>> $plan */
function assignPreviewCodes(array &$plan, ProvinceAccountingCodeService $codeService): void
{
    unset($codeService);

    $simulatedMax = [];

    foreach ($plan as &$row) {
        if (($row['status'] ?? '') !== 'pending') {
            continue;
        }

        $province = Province::query()->findOrFail($row['province_id']);
        $prefix = (string) $province->accounting_code . (string) $row['indicator'];
        $simulatedMax[$prefix] = ($simulatedMax[$prefix] ?? 0) + 1;
        $counter = $simulatedMax[$prefix];
        $width = max(2, strlen((string) $counter));
        $row['new_code'] = $prefix . str_pad((string) $counter, $width, '0', STR_PAD_LEFT);
    }

    unset($row);
}

/**
 * employer_code و beneficiary_code در دیتابیس NOT NULL هستند؛
 * برای آزاد کردن unique constraint ابتدا placeholder یکتا می‌گذاریم.
 */
function releaseAccountingCodeSlots(): void
{
    User::query()->whereNotNull('personnel_code')->update(['personnel_code' => null]);

    ProgramEmployer::query()
        ->orderBy('id')
        ->each(fn (ProgramEmployer $employer) => $employer->update([
            'employer_code' => sprintf('TMP%07d', $employer->id),
        ]));

    ProgramBeneficiary::query()
        ->orderBy('id')
        ->each(fn (ProgramBeneficiary $beneficiary) => $beneficiary->update([
            'beneficiary_code' => sprintf('TMPB%06d', $beneficiary->id),
        ]));
}

/** @param array<int, array<string, mixed>> $plan */
function applyRegenerationPlan(array $plan, ProvinceAccountingCodeService $codeService): array
{
    $results = [];

    DB::transaction(function () use ($plan, $codeService, &$results) {
        releaseAccountingCodeSlots();

        foreach ($plan as $row) {
            if (($row['status'] ?? '') !== 'pending') {
                $results[] = $row;
                continue;
            }

            $province = Province::query()->findOrFail($row['province_id']);
            $codeService->ensureProvinceHasCode($province);
            $newCode = (string) ($row['new_code'] ?? '');

            if ($newCode === '') {
                throw new RuntimeException("کد جدید برای {$row['type']} #{$row['entity_id']} محاسبه نشده است.");
            }

            match ($row['type']) {
                'host' => User::query()->whereKey($row['entity_id'])->update([
                    'province_id'    => $province->id,
                    'personnel_code' => $newCode,
                ]),
                'employer' => ProgramEmployer::query()->whereKey($row['entity_id'])->update([
                    'province_id'   => $province->id,
                    'employer_code' => $newCode,
                ]),
                'beneficiary' => ProgramBeneficiary::query()->whereKey($row['entity_id'])->update([
                    'province_id'      => $province->id,
                    'beneficiary_code' => $newCode,
                ]),
                default => null,
            };

            $row['new_code'] = $newCode;
            $row['status'] = 'done';
            $results[] = $row;
        }
    });

    return $results;
}

$plan = buildRegenerationPlan($hostProvisioner, $codeService);
$executed = false;
$errorMessage = null;

if (!$dryRun) {
    try {
        $plan = applyRegenerationPlan($plan, $codeService);
        $executed = true;
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

$counts = [
    'pending'  => 0,
    'done'     => 0,
    'skipped'  => 0,
    'error'    => 0,
    'changed'  => 0,
    'unchanged'=> 0,
];

foreach ($plan as $row) {
    $status = $row['status'] ?? 'pending';
    $counts[$status] = ($counts[$status] ?? 0) + 1;

    if ($status === 'pending' || $status === 'done') {
        if (($row['old_code'] ?? null) !== ($row['new_code'] ?? null)
            || ($row['old_province'] ?? null) !== ($row['new_province'] ?? null)) {
            $counts['changed']++;
        } else {
            $counts['unchanged']++;
        }
    }
}

$elapsed = round((microtime(true) - LARAVEL_START) * 1000);
$modeLabel = $dryRun ? 'پیش‌نمایش (بدون تغییر در دیتابیس)' : ($executed ? 'اجرا شد' : 'خطا در اجرا');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<title>بازتولید کدینگ حسابداری</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: Tahoma, sans-serif; background: #f3f4f6; padding: 24px; color: #111827; }
  .wrap { max-width: 1200px; margin: 0 auto; }
  .card { background: #fff; border-radius: 12px; padding: 28px 32px; box-shadow: 0 4px 24px rgba(0,0,0,.08); margin-bottom: 20px; }
  h1 { font-size: 22px; margin-bottom: 8px; }
  .subtitle { color: #6b7280; font-size: 13px; line-height: 1.8; margin-bottom: 18px; }
  .stats { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 18px; }
  .stat { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px 14px; font-size: 13px; }
  .stat strong { display: block; font-size: 18px; margin-top: 4px; }
  .actions { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 18px; }
  .btn { display: inline-block; padding: 10px 16px; border-radius: 8px; text-decoration: none; font-size: 13px; font-weight: 600; }
  .btn-primary { background: #2563eb; color: #fff; }
  .btn-secondary { background: #e5e7eb; color: #111827; }
  .btn-danger { background: #dc2626; color: #fff; }
  .note { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 12px 14px; font-size: 12px; color: #1e40af; line-height: 1.9; margin-bottom: 18px; }
  .alert-error { background: #fee2e2; border: 1px solid #fecaca; color: #991b1b; border-radius: 8px; padding: 12px 14px; margin-bottom: 18px; font-size: 13px; }
  table { width: 100%; border-collapse: collapse; font-size: 12px; }
  th, td { padding: 8px 10px; border-bottom: 1px solid #f0f0f0; text-align: right; vertical-align: top; }
  th { background: #f9fafb; font-size: 12px; color: #374151; position: sticky; top: 0; }
  .mono { direction: ltr; font-family: Consolas, monospace; }
  .badge { display: inline-block; border-radius: 999px; padding: 2px 8px; font-size: 11px; }
  .badge-pending { background: #fef3c7; color: #92400e; }
  .badge-done { background: #d1fae5; color: #065f46; }
  .badge-skipped { background: #f3f4f6; color: #4b5563; }
  .badge-error { background: #fee2e2; color: #991b1b; }
  .table-wrap { max-height: 520px; overflow: auto; border: 1px solid #e5e7eb; border-radius: 8px; }
  .footer { text-align: center; color: #9ca3af; font-size: 12px; margin-top: 8px; }
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <h1>🔢 بازتولید کدینگ حسابداری</h1>
    <p class="subtitle">
      حالت: <strong><?= htmlspecialchars($modeLabel) ?></strong><br>
      استان حسابداری از اقامتگاه (اولین اقامتگاه برای میزبان / اقامتگاه ذینفع / اقامتگاه کاربر کارفرما) تشخیص داده می‌شود
      و کدها از نو به‌صورت متوالی تولید می‌شوند.
    </p>

    <?php if ($errorMessage): ?>
      <div class="alert-error">❌ <?= htmlspecialchars($errorMessage) ?></div>
    <?php endif; ?>

    <div class="stats">
      <div class="stat">کل ردیف‌ها<strong><?= count($plan) ?></strong></div>
      <div class="stat">تغییر می‌کند<strong><?= (int) $counts['changed'] ?></strong></div>
      <div class="stat">بدون تغییر<strong><?= (int) $counts['unchanged'] ?></strong></div>
      <div class="stat">رد شده<strong><?= (int) ($counts['skipped'] ?? 0) ?></strong></div>
      <div class="stat">خطا<strong><?= (int) ($counts['error'] ?? 0) ?></strong></div>
    </div>

    <div class="note">
      <strong>قبل از اجرا:</strong> مطمئن شوید کدهای حسابداری استان‌ها در پنل مدیریت به‌روز شده‌اند.
      اجرای واقعی ابتدا همه کدهای قبلی را پاک می‌کند، سپس کدهای جدید را اختصاص می‌دهد.
      حتماً از دیتابیس بکاپ بگیرید.
    </div>

    <div class="actions">
      <a class="btn btn-secondary" href="?key=<?= urlencode(SECRET_KEY) ?>">پیش‌نمایش</a>
      <?php if ($dryRun): ?>
        <a class="btn btn-danger" href="?key=<?= urlencode(SECRET_KEY) ?>&run=1"
           onclick="return confirm('همه کدهای حسابداری پاک و دوباره تولید می‌شوند. ادامه می‌دهید؟');">
          اجرای واقعی
        </a>
      <?php endif; ?>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>نوع</th>
            <th>شناسه</th>
            <th>نام</th>
            <th>استان قبلی</th>
            <th>کد قبلی</th>
            <th>استان جدید</th>
            <th>کد جدید</th>
            <th>وضعیت</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($plan as $row): ?>
            <?php
              $status = $row['status'] ?? 'pending';
              $typeLabels = [
                  'host' => 'میزبان',
                  'employer' => 'کارفرما',
                  'beneficiary' => 'ذینفع',
              ];
            ?>
            <tr>
              <td><?= htmlspecialchars($typeLabels[$row['type']] ?? $row['type']) ?></td>
              <td><?= (int) $row['id'] ?></td>
              <td><?= htmlspecialchars((string) $row['name']) ?></td>
              <td><?= htmlspecialchars((string) ($row['old_province'] ?? '—')) ?></td>
              <td class="mono"><?= htmlspecialchars((string) ($row['old_code'] ?? '—')) ?></td>
              <td><?= htmlspecialchars((string) ($row['new_province'] ?? '—')) ?></td>
              <td class="mono"><?= htmlspecialchars((string) ($row['new_code'] ?? '—')) ?></td>
              <td>
                <span class="badge badge-<?= htmlspecialchars($status) ?>">
                  <?= htmlspecialchars(match ($status) {
                      'done' => 'انجام شد',
                      'skipped' => 'رد شد',
                      'error' => 'خطا',
                      default => 'در انتظار',
                  }) ?>
                </span>
                <?php if (!empty($row['message'])): ?>
                  <div style="color:#6b7280;margin-top:4px;"><?= htmlspecialchars((string) $row['message']) ?></div>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="footer">زمان اجرا: <?= $elapsed ?> میلی‌ثانیه</div>
  </div>
</div>
</body>
</html>
