<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gap #9 — "Dua periode akademik bisa aktif bersamaan".
 *
 * 1. Default kolom `status` pada `academic_years` & `semesters` diubah dari
 *    'active' menjadi 'inactive', sehingga create/update yang tidak mengirim
 *    `status` tidak lagi diam-diam mengaktifkan periode baru.
 * 2. Ditambahkan partial unique index pada lapisan basis data supaya paling
 *    banyak satu baris ber-status 'active' per tabel, bahkan saat ada race
 *    condition antar request.
 */
return new class extends Migration
{
    private const TABLES = ['academic_years', 'semesters'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            // (a) Normalisasi data lama: sisakan satu baris aktif saja supaya
            //     pembuatan unique index di bawah tidak gagal.
            $this->keepSingleActiveRow($table);

            // (b) Ubah default kolom menjadi 'inactive'.
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('status', 20)->default('inactive')->change();
            });

            // (c) Jamin "maksimal satu periode aktif" di level basis data.
            $this->createSingleActiveIndex($table);
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            $this->dropSingleActiveIndex($table);

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('status', 20)->default('active')->change();
            });
        }
    }

    /**
     * Sisakan satu baris aktif (yang paling awal dibuat) dan nonaktifkan sisanya.
     */
    private function keepSingleActiveRow(string $table): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $keep = DB::table($table)
            ->where('status', 'active')
            ->orderBy('id')
            ->value('id');

        $query = DB::table($table)->where('status', 'active');

        if ($keep !== null) {
            $query->where('id', '!=', $keep);
        }

        $query->update(['status' => 'inactive']);
    }

    private function createSingleActiveIndex(string $table): void
    {
        $index = $this->indexName($table);
        $driver = DB::connection()->getDriverName();

        // SQLite & PostgreSQL mendukung partial unique index secara native.
        if (in_array($driver, ['sqlite', 'pgsql'], true)) {
            DB::statement(
                "CREATE UNIQUE INDEX {$index} ON {$table} (status) WHERE status = 'active'"
            );

            return;
        }

        // MySQL/MariaDB tidak punya partial index. Sebagai gantinya dipakai
        // generated column bernilai NULL untuk baris non-aktif (unique index
        // mengizinkan banyak NULL) dan 1 untuk baris aktif.
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement(
                "ALTER TABLE {$table}
                    ADD COLUMN active_unique_flag TINYINT
                        GENERATED ALWAYS AS (IF(status = 'active', 1, NULL)) VIRTUAL,
                    ADD UNIQUE INDEX {$index} (active_unique_flag)"
            );

            return;
        }

        // Driver lain (sqlsrv, dsb.) sengaja dilewati: invariant "satu periode
        // aktif" tetap dijaga di lapisan aplikasi (transaksi pada controller).
    }

    private function dropSingleActiveIndex(string $table): void
    {
        $index = $this->indexName($table);
        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            if (Schema::hasColumn($table, 'active_unique_flag')) {
                DB::statement("ALTER TABLE {$table} DROP INDEX {$index}");
                DB::statement("ALTER TABLE {$table} DROP COLUMN active_unique_flag");
            }

            return;
        }

        if (in_array($driver, ['sqlite', 'pgsql'], true)) {
            DB::statement("DROP INDEX IF EXISTS {$index}");
        }
    }

    private function indexName(string $table): string
    {
        return $table . '_single_active_unique';
    }
};
