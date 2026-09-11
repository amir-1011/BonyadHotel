<?php

use App\Models\ServiceCatalog;
use App\Services\VeteranPolicyService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('service_catalogs')) {
            return;
        }

        $serviceIds = DB::table('service_catalogs')
            ->whereIn('key', ServiceCatalog::RETIRED_HALL_KEYS)
            ->pluck('id');

        if ($serviceIds->isEmpty()) {
            return;
        }

        if (Schema::hasTable('veteran_group_service_discounts')) {
            DB::table('veteran_group_service_discounts')
                ->whereIn('service_catalog_id', $serviceIds)
                ->delete();
        }

        if (Schema::hasTable('service_catalog_variants')) {
            DB::table('service_catalog_variants')
                ->whereIn('service_catalog_id', $serviceIds)
                ->delete();
        }

        if (Schema::hasTable('booking_services')) {
            DB::table('booking_services')
                ->whereIn('service_catalog_id', $serviceIds)
                ->update(['service_catalog_id' => null, 'service_catalog_variant_id' => null]);
        }

        DB::table('service_catalogs')->whereIn('id', $serviceIds)->delete();

        if (function_exists('app')) {
            try {
                app(VeteranPolicyService::class)->clearCache();
            } catch (\Throwable) {
                // Cache may be unavailable during migrate.
            }
        }
    }

    public function down(): void
    {
        // Retired hall catalog keys are not restored.
    }
};
