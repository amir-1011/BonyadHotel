<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\HostPersonnelCodeProvisioner;
use Illuminate\Console\Command;

class ProvisionHostPersonnelCodesCommand extends Command
{
    protected $signature = 'accounting:provision-host-codes {--dry-run : Only report hosts that need codes}';

    protected $description = 'Assign personnel accounting codes to hosts that have accommodations but no code yet';

    public function handle(HostPersonnelCodeProvisioner $provisioner): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $hosts = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'host'))
            ->whereNull('personnel_code')
            ->whereHas('accommodations')
            ->with(['accommodations.city.province', 'accommodations.county.province'])
            ->orderBy('id')
            ->get();

        if ($hosts->isEmpty()) {
            $this->info('No hosts need personnel codes.');

            return self::SUCCESS;
        }

        $provisioned = 0;
        $skipped = 0;

        foreach ($hosts as $host) {
            $province = $provisioner->resolveProvinceFromAccommodations($host);

            if (!$province) {
                $this->warn("Host #{$host->id} ({$host->name}): could not resolve province from accommodations.");
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $preview = $provisioner->previewNextForAccommodation(
                    $host->accommodations->sortBy(fn ($a) => $a->pivot?->created_at?->timestamp ?? $a->id)->first()
                );
                $this->line("Host #{$host->id} ({$host->name}) → {$preview} ({$province->name})");
                $provisioned++;
                continue;
            }

            $updated = $provisioner->provisionIfNeeded($host->fresh());

            if (filled($updated->personnel_code)) {
                $this->info("Host #{$host->id} ({$host->name}) → {$updated->personnel_code}");
                $provisioned++;
            } else {
                $this->warn("Host #{$host->id} ({$host->name}): provisioning failed.");
                $skipped++;
            }
        }

        $this->newLine();
        $this->info(($dryRun ? 'Would provision' : 'Provisioned') . ": {$provisioned}, skipped: {$skipped}");

        return self::SUCCESS;
    }
}
