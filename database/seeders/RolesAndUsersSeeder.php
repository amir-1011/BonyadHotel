<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RolesAndUsersSeeder extends Seeder
{
    public function run(): void
    {
        // ─── Roles ────────────────────────────────────────────────────────────
        $adminRole = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $hostRole  = Role::firstOrCreate(['name' => 'host',        'guard_name' => 'web']);
        $guestRole = Role::firstOrCreate(['name' => 'guest',       'guard_name' => 'web']);

        // ─── Super Admin ───────────────────────────────────────────────────────
        $admin = User::firstOrCreate(
            ['mobile' => '09100000001'],
            [
                'name'                => 'علی احمدی',
                'national_id'         => '0012345678',
                'veteran_type'        => 'veteran_70_plus',
                'discount_percentage' => 70,
                'mobile_verified_at'  => now(),
                'national_id_verified_at' => now(),
            ]
        );
        $admin->syncRoles(['super_admin']);

        // ─── Hosts ─────────────────────────────────────────────────────────────
        $hostsData = [
            ['mobile' => '09110000001', 'name' => 'سارا رضایی',   'veteran_type' => 'martyr_family',  'discount' => 50],
            ['mobile' => '09110000002', 'name' => 'محمد کریمی',   'veteran_type' => 'freed_prisoner_family', 'discount' => 40],
            ['mobile' => '09110000003', 'name' => 'فاطمه موسوی',  'veteran_type' => null,              'discount' => 0],
        ];

        $hosts = [];
        foreach ($hostsData as $hd) {
            $u = User::firstOrCreate(
                ['mobile' => $hd['mobile']],
                [
                    'name'                => $hd['name'],
                    'national_id'         => '00' . rand(10000000, 99999999),
                    'veteran_type'        => $hd['veteran_type'],
                    'discount_percentage' => $hd['discount'],
                    'mobile_verified_at'  => now(),
                    'national_id_verified_at' => now(),
                ]
            );
            $u->syncRoles(['host']);
            $hosts[] = $u;
        }

        // ─── Guests ────────────────────────────────────────────────────────────
        $guestsData = [
            ['mobile' => '09120000001', 'name' => 'رضا صادقی',       'veteran_type' => 'veteran_25_49',   'discount' => 25],
            ['mobile' => '09120000002', 'name' => 'مریم حسینی',      'veteran_type' => 'veteran_50_69',   'discount' => 50],
            ['mobile' => '09120000003', 'name' => 'حسن نوروزی',      'veteran_type' => 'martyr_family',   'discount' => 50],
            ['mobile' => '09120000004', 'name' => 'زهرا قاسمی',      'veteran_type' => null,              'discount' => 0],
            ['mobile' => '09120000005', 'name' => 'امیر تهرانی',     'veteran_type' => 'veteran_70_plus', 'discount' => 70],
            ['mobile' => '09120000006', 'name' => 'نسرین شیرازی',    'veteran_type' => null,              'discount' => 0],
            ['mobile' => '09120000007', 'name' => 'کاوه مشهدی',      'veteran_type' => 'freed_prisoner_family','discount' => 40],
            ['mobile' => '09120000008', 'name' => 'لیلا اصفهانی',    'veteran_type' => null,              'discount' => 0],
        ];

        $guests = [];
        foreach ($guestsData as $gd) {
            $u = User::firstOrCreate(
                ['mobile' => $gd['mobile']],
                [
                    'name'                => $gd['name'],
                    'national_id'         => '00' . rand(10000000, 99999999),
                    'veteran_type'        => $gd['veteran_type'],
                    'discount_percentage' => $gd['discount'],
                    'mobile_verified_at'  => now(),
                    'national_id_verified_at' => $gd['veteran_type'] ? now() : null,
                ]
            );
            $u->syncRoles(['guest']);
            $guests[] = $u;
        }

        // Store IDs in cache for other seeders
        cache()->put('seeder_hosts', collect($hosts)->pluck('id')->toArray(), 60);
        cache()->put('seeder_guests', collect($guests)->pluck('id')->toArray(), 60);
    }
}
