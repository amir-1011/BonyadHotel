<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\MedicalAccommodationProvisioner;
use Illuminate\Console\Command;

class ProvisionDayInsuranceEmployersCommand extends Command
{
    protected $signature = 'medical:provision-day-insurance';

    protected $description = 'Create a Day Insurance (بیمه دی) employer per province and assign it to medical accommodation settings';

    public function handle(MedicalAccommodationProvisioner $provisioner): int
    {
        $result = $provisioner->syncDayInsuranceEmployers();

        $this->info("Created: {$result['created']}, assigned accommodations: {$result['assigned']}, skipped provinces: {$result['skipped']}");

        return self::SUCCESS;
    }
}
