<?php

namespace Database\Seeders;

use App\Models\Accommodation;
use App\Models\User;
use Illuminate\Database\Seeder;

class AssignHostsSeeder extends Seeder
{
    public function run(): void
    {
        $hosts = User::role('host')->get();
        if ($hosts->isEmpty()) {
            $this->command->warn('No hosts found.');
            return;
        }

        $accommodations = Accommodation::all();
        foreach ($accommodations as $i => $acc) {
            $acc->update(['host_id' => $hosts[$i % $hosts->count()]->id]);
        }

        $this->command->info('Hosts assigned to accommodations.');
    }
}
