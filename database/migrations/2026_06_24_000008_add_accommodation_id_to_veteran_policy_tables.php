<?php

use App\Models\Accommodation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('veteran_groups', function (Blueprint $table) {
            $table->foreignId('accommodation_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        Schema::table('service_catalogs', function (Blueprint $table) {
            $table->foreignId('accommodation_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        Schema::table('veteran_groups', function (Blueprint $table) {
            $table->dropUnique(['key']);
        });

        Schema::table('service_catalogs', function (Blueprint $table) {
            $table->dropUnique(['key']);
        });

        $this->copyGlobalPolicyToAccommodations();

        DB::table('veteran_groups')->whereNull('accommodation_id')->delete();
        DB::table('service_catalogs')->whereNull('accommodation_id')->delete();

        Schema::table('veteran_groups', function (Blueprint $table) {
            $table->unsignedBigInteger('accommodation_id')->nullable(false)->change();
            $table->unique(['accommodation_id', 'key'], 'veteran_groups_accommodation_key_unique');
        });

        Schema::table('service_catalogs', function (Blueprint $table) {
            $table->unsignedBigInteger('accommodation_id')->nullable(false)->change();
            $table->unique(['accommodation_id', 'key'], 'service_catalogs_accommodation_key_unique');
        });
    }

    public function down(): void
    {
        Schema::table('veteran_groups', function (Blueprint $table) {
            $table->dropUnique('veteran_groups_accommodation_key_unique');
            $table->dropConstrainedForeignId('accommodation_id');
            $table->unique('key');
        });

        Schema::table('service_catalogs', function (Blueprint $table) {
            $table->dropUnique('service_catalogs_accommodation_key_unique');
            $table->dropConstrainedForeignId('accommodation_id');
            $table->unique('key');
        });
    }

    private function copyGlobalPolicyToAccommodations(): void
    {
        $globalGroups = DB::table('veteran_groups')->whereNull('accommodation_id')->get();
        $globalServices = DB::table('service_catalogs')->whereNull('accommodation_id')->get();

        if ($globalGroups->isEmpty() || $globalServices->isEmpty()) {
            return;
        }

        $globalDiscounts = DB::table('veteran_group_service_discounts')
            ->whereIn('veteran_group_id', $globalGroups->pluck('id'))
            ->get();

        $accommodationIds = Accommodation::query()->pluck('id');

        foreach ($accommodationIds as $accommodationId) {
            if (DB::table('veteran_groups')->where('accommodation_id', $accommodationId)->exists()) {
                continue;
            }

            $groupIdMap = [];
            foreach ($globalGroups as $group) {
                $newId = DB::table('veteran_groups')->insertGetId([
                    'accommodation_id'       => $accommodationId,
                    'key'                    => $group->key,
                    'label'                  => $group->label,
                    'accommodation_discount' => $group->accommodation_discount,
                    'nights_per_dependent'   => $group->nights_per_dependent,
                    'max_nights_per_period'  => $group->max_nights_per_period,
                    'period_months'          => $group->period_months,
                    'weekly_free_sessions'   => $group->weekly_free_sessions,
                    'usage_notes'            => $group->usage_notes,
                    'sort_order'             => $group->sort_order,
                    'is_active'              => $group->is_active,
                    'created_at'             => $group->created_at ?? now(),
                    'updated_at'             => $group->updated_at ?? now(),
                ]);
                $groupIdMap[$group->id] = $newId;
            }

            $serviceIdMap = [];
            foreach ($globalServices as $service) {
                $newId = DB::table('service_catalogs')->insertGetId([
                    'accommodation_id'         => $accommodationId,
                    'key'                      => $service->key,
                    'name'                     => $service->name,
                    'default_price'            => $service->default_price,
                    'supports_free_sessions'   => $service->supports_free_sessions,
                    'default_discount'         => $service->default_discount,
                    'min_discount'             => $service->min_discount,
                    'max_discount'             => $service->max_discount,
                    'sort_order'               => $service->sort_order,
                    'is_active'                => $service->is_active,
                    'created_at'               => $service->created_at ?? now(),
                    'updated_at'               => $service->updated_at ?? now(),
                ]);
                $serviceIdMap[$service->id] = $newId;
            }

            foreach ($globalDiscounts as $discount) {
                $newGroupId = $groupIdMap[$discount->veteran_group_id] ?? null;
                $newServiceId = $serviceIdMap[$discount->service_catalog_id] ?? null;
                if (!$newGroupId || !$newServiceId) {
                    continue;
                }

                DB::table('veteran_group_service_discounts')->insert([
                    'veteran_group_id'       => $newGroupId,
                    'service_catalog_id'     => $newServiceId,
                    'discount_percentage'    => $discount->discount_percentage,
                    'free_sessions_eligible' => $discount->free_sessions_eligible,
                    'weekly_free_sessions'   => $discount->weekly_free_sessions ?? 0,
                    'created_at'             => $discount->created_at ?? now(),
                    'updated_at'             => $discount->updated_at ?? now(),
                ]);
            }
        }
    }
};
