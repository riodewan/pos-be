<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menambahkan dukungan QR Sekali Pakai (Disposable QR) pada tabel qr_codes.
 *
 * - type        : TABLE (QR permanen meja) | DISPOSABLE (QR sekali pakai / event)
 * - label       : Nama acara / catatan untuk QR sekali pakai
 * - expires_at  : Batas waktu QR dapat digunakan (null = tanpa batas)
 * - max_uses    : Batas jumlah pesanan (null = tanpa batas)
 * - used_count  : Jumlah pesanan yang sudah dibuat lewat QR ini
 *
 * Partial unique index "satu QR ACTIVE per meja" kini hanya berlaku untuk type = TABLE,
 * sehingga QR sekali pakai bisa aktif bersamaan dengan QR permanen meja.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qr_codes', function (Blueprint $table) {
            $table->string('type', 20)->default('TABLE')->after('token');
            $table->string('label', 150)->nullable()->after('type');
            $table->timestampTz('expires_at', 6)->nullable()->after('status');
            $table->unsignedInteger('max_uses')->nullable()->after('expires_at');
            $table->unsignedInteger('used_count')->default(0)->after('max_uses');

            $table->index(['store_id', 'type'], 'idx_qr_codes_store_type');
        });

        DB::statement("ALTER TABLE qr_codes ADD CONSTRAINT chk_qr_codes_type CHECK (type IN ('TABLE', 'DISPOSABLE'))");

        DB::statement('DROP INDEX IF EXISTS uq_qr_codes_table_active');
        DB::statement("CREATE UNIQUE INDEX uq_qr_codes_table_active ON qr_codes (table_id) WHERE status = 'ACTIVE' AND type = 'TABLE' AND deleted_at IS NULL");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS uq_qr_codes_table_active');
        DB::statement("CREATE UNIQUE INDEX uq_qr_codes_table_active ON qr_codes (table_id) WHERE status = 'ACTIVE' AND deleted_at IS NULL");
        DB::statement('ALTER TABLE qr_codes DROP CONSTRAINT IF EXISTS chk_qr_codes_type');

        Schema::table('qr_codes', function (Blueprint $table) {
            $table->dropIndex('idx_qr_codes_store_type');
            $table->dropColumn(['type', 'label', 'expires_at', 'max_uses', 'used_count']);
        });
    }
};
