<?php

use App\Models\Accommodation;
use App\Models\Booking;
use App\Models\MedicalAccommodationContract;
use App\Models\MedicalAccommodationSetting;
use App\Models\MedicalAccommodationTariff;
use App\Services\MedicalAccommodationProvisioner;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('medical_accommodation_contracts')) {
            Schema::create('medical_accommodation_contracts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('accommodation_id')->constrained()->cascadeOnDelete();
                $table->foreignId('program_employer_id')->nullable()->constrained('program_employers')->nullOnDelete();
                $table->string('contract_number', 40);
                $table->date('starts_on')->nullable();
                $table->date('ends_on')->nullable();
                $table->boolean('is_active')->default(true);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['accommodation_id', 'contract_number'], 'mac_contract_number_unique');
                $table->index(['accommodation_id', 'is_active'], 'mac_acc_active_index');
            });
        } else {
            try {
                Schema::table('medical_accommodation_contracts', function (Blueprint $table) {
                    $table->unique(['accommodation_id', 'contract_number'], 'mac_contract_number_unique');
                });
            } catch (\Throwable) {
                // Unique already exists from a previous attempt.
            }
        }

        Schema::table('medical_accommodation_tariffs', function (Blueprint $table) {
            if (! Schema::hasColumn('medical_accommodation_tariffs', 'contract_id')) {
                $table->foreignId('contract_id')
                    ->nullable()
                    ->after('accommodation_id')
                    ->constrained('medical_accommodation_contracts')
                    ->cascadeOnDelete();
            }
        });

        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'medical_contract_id')) {
                $table->foreignId('medical_contract_id')
                    ->nullable()
                    ->after('medical_tariff_id')
                    ->constrained('medical_accommodation_contracts')
                    ->nullOnDelete();
            }
        });

        $provisioner = app(MedicalAccommodationProvisioner::class);

        foreach (Accommodation::query()->with(['city.province', 'county.province', 'medicalAccommodationSetting'])->orderBy('id')->get() as $accommodation) {
            $setting = $accommodation->medicalAccommodationSetting
                ?? MedicalAccommodationSetting::query()->firstOrCreate(
                    ['accommodation_id' => $accommodation->id],
                    [
                        'skip_cancellation_penalties' => true,
                        'require_overnight'           => true,
                    ],
                );

            $provisioner->ensureDefaultContract($accommodation, $setting);
        }

        foreach (MedicalAccommodationTariff::query()->whereNull('contract_id')->get() as $tariff) {
            $setting = MedicalAccommodationSetting::query()->firstOrCreate(
                ['accommodation_id' => $tariff->accommodation_id],
                [
                    'skip_cancellation_penalties' => true,
                    'require_overnight'           => true,
                ],
            );
            $accommodation = Accommodation::query()->find($tariff->accommodation_id);
            if (!$accommodation) {
                continue;
            }
            $contract = $provisioner->ensureDefaultContract($accommodation, $setting);
            $tariff->update(['contract_id' => $contract->id]);
        }

        foreach (Booking::query()->whereNotNull('medical_tariff_id')->whereNull('medical_contract_id')->get() as $booking) {
            $contractId = MedicalAccommodationTariff::query()
                ->whereKey($booking->medical_tariff_id)
                ->value('contract_id');
            if ($contractId) {
                $booking->update(['medical_contract_id' => $contractId]);
            }
        }

        $this->ensureAccommodationIdIndexOnTariffs();
        $this->dropTariffAccommodationKeyUnique();

        try {
            Schema::table('medical_accommodation_tariffs', function (Blueprint $table) {
                $table->unique(['contract_id', 'key'], 'mac_tariff_contract_key_unique');
            });
        } catch (\Throwable) {
            // Unique already exists.
        }
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (Schema::hasColumn('bookings', 'medical_contract_id')) {
                $table->dropConstrainedForeignId('medical_contract_id');
            }
        });

        Schema::table('medical_accommodation_tariffs', function (Blueprint $table) {
            try {
                $table->dropUnique('mac_tariff_contract_key_unique');
            } catch (\Throwable) {
            }
            if (Schema::hasColumn('medical_accommodation_tariffs', 'contract_id')) {
                $table->dropConstrainedForeignId('contract_id');
            }
            try {
                $table->unique(['accommodation_id', 'key']);
            } catch (\Throwable) {
            }
        });

        Schema::dropIfExists('medical_accommodation_contracts');
    }

    private function ensureAccommodationIdIndexOnTariffs(): void
    {
        $indexes = $this->tariffIndexNames();
        $hasDedicated = collect($indexes)->contains(function (array $index) {
            $columns = $index['columns'] ?? [];

            return $columns === ['accommodation_id'];
        });

        if ($hasDedicated) {
            return;
        }

        try {
            Schema::table('medical_accommodation_tariffs', function (Blueprint $table) {
                $table->index('accommodation_id', 'mac_tariff_acc_id_index');
            });
        } catch (\Throwable) {
            // Index already exists under another name.
        }
    }

    private function dropTariffAccommodationKeyUnique(): void
    {
        try {
            Schema::table('medical_accommodation_tariffs', function (Blueprint $table) {
                $table->dropForeign(['accommodation_id']);
            });
        } catch (\Throwable) {
            // SQLite or already dropped.
        }

        $dropped = false;
        foreach ([
            'medical_accommodation_tariffs_accommodation_id_key_unique',
        ] as $name) {
            try {
                Schema::table('medical_accommodation_tariffs', function (Blueprint $table) use ($name) {
                    $table->dropUnique($name);
                });
                $dropped = true;
                break;
            } catch (\Throwable) {
            }
        }

        if (! $dropped) {
            try {
                Schema::table('medical_accommodation_tariffs', function (Blueprint $table) {
                    $table->dropUnique(['accommodation_id', 'key']);
                });
            } catch (\Throwable) {
                // Index may already have been replaced.
            }
        }

        try {
            Schema::table('medical_accommodation_tariffs', function (Blueprint $table) {
                $table->foreign('accommodation_id')
                    ->references('id')
                    ->on('accommodations')
                    ->cascadeOnDelete();
            });
        } catch (\Throwable) {
            // Foreign key already present.
        }
    }

    /** @return list<array<string, mixed>> */
    private function tariffIndexNames(): array
    {
        $sm = Schema::getConnection()->getSchemaBuilder();
        if (! method_exists($sm, 'getIndexes')) {
            return [];
        }

        return $sm->getIndexes('medical_accommodation_tariffs');
    }
};
