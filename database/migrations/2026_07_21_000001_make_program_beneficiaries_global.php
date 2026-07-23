<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('program_beneficiaries')) {
            return;
        }

        if ($this->hasIndex('program_beneficiaries', 'program_beneficiaries_beneficiary_code_unique')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        if ($this->hasForeignKey('program_beneficiaries', 'program_beneficiaries_accommodation_id_foreign')) {
            Schema::table('program_beneficiaries', function (Blueprint $table) {
                $table->dropForeign(['accommodation_id']);
            });
        }

        if ($this->hasIndex('program_beneficiaries', 'program_beneficiaries_accommodation_id_beneficiary_code_unique')) {
            Schema::table('program_beneficiaries', function (Blueprint $table) {
                $table->dropUnique(['accommodation_id', 'beneficiary_code']);
            });
        }

        if ($this->columnIsNotNullable('program_beneficiaries', 'accommodation_id')) {
            Schema::table('program_beneficiaries', function (Blueprint $table) {
                $table->unsignedBigInteger('accommodation_id')->nullable()->change();
            });
        }

        $seen = [];
        $rows = DB::table('program_beneficiaries')->orderBy('id')->get();

        foreach ($rows as $row) {
            $code = (string) $row->beneficiary_code;
            if (isset($seen[$code])) {
                DB::table('program_beneficiary_costs')
                    ->where('program_beneficiary_id', $row->id)
                    ->update(['program_beneficiary_id' => $seen[$code]]);

                DB::table('program_beneficiaries')->where('id', $row->id)->delete();
                continue;
            }

            $seen[$code] = (int) $row->id;
        }

        DB::table('program_beneficiaries')->update(['accommodation_id' => null]);

        if (!$this->hasIndex('program_beneficiaries', 'program_beneficiaries_beneficiary_code_unique')) {
            Schema::table('program_beneficiaries', function (Blueprint $table) {
                $table->unique('beneficiary_code');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('program_beneficiaries')) {
            return;
        }

        if ($this->hasIndex('program_beneficiaries', 'program_beneficiaries_beneficiary_code_unique')) {
            Schema::table('program_beneficiaries', function (Blueprint $table) {
                $table->dropUnique(['beneficiary_code']);
            });
        }

        Schema::table('program_beneficiaries', function (Blueprint $table) {
            $table->unsignedBigInteger('accommodation_id')->nullable(false)->change();
            $table->foreign('accommodation_id')->references('id')->on('accommodations')->cascadeOnDelete();
            $table->unique(['accommodation_id', 'beneficiary_code']);
        });
    }

    private function hasForeignKey(string $table, string $foreignKey): bool
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return false;
        }

        $database = Schema::getConnection()->getDatabaseName();

        $result = DB::selectOne(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ?',
            [$database, $table, $foreignKey, 'FOREIGN KEY'],
        );

        return $result !== null;
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list('{$table}')");

            foreach ($indexes as $index) {
                if (($index->name ?? '') === $indexName) {
                    return true;
                }
            }

            return false;
        }

        $database = Schema::getConnection()->getDatabaseName();

        $result = DB::selectOne(
            'SELECT INDEX_NAME FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?
             LIMIT 1',
            [$database, $table, $indexName],
        );

        return $result !== null;
    }

    private function columnIsNotNullable(string $table, string $column): bool
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $columns = DB::select("PRAGMA table_info('{$table}')");

            foreach ($columns as $col) {
                if (($col->name ?? '') === $column) {
                    return (int) ($col->notnull ?? 0) === 1;
                }
            }

            return false;
        }

        $database = Schema::getConnection()->getDatabaseName();

        $result = DB::selectOne(
            'SELECT IS_NULLABLE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?
             LIMIT 1',
            [$database, $table, $column],
        );

        return $result && strtoupper((string) $result->IS_NULLABLE) === 'NO';
    }
};
