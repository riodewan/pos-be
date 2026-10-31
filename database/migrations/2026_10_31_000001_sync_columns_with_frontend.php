<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Enhance tables table
        Schema::table('tables', function (Blueprint $table) {
            $table->string('area', 50)->default('Indoor')->after('name');
            $table->string('category', 100)->default('Meja Standar (2-4 Orang)')->after('area');
            $table->integer('table_number')->nullable()->after('category');
            $table->string('operational_status', 30)->default('Kosong')->after('status');
            $table->text('notes')->nullable()->after('operational_status');
        });

        // 2. Enhance ingredients table
        Schema::table('ingredients', function (Blueprint $table) {
            $table->string('category', 50)->default('Kopi')->after('name');
            $table->decimal('cost_price', 15, 2)->default(0)->after('category');
            $table->decimal('min_stock', 15, 4)->default(2)->after('cost_price');
            $table->text('description')->nullable()->after('min_stock');
            $table->string('image_url', 500)->nullable()->after('description');
            $table->char('supplier_id', 26)->nullable()->after('image_url');
            $table->string('storage_location', 100)->default('Gudang Bahan Kering')->after('supplier_id');
            $table->integer('shelf_life_value')->nullable()->after('storage_location');
            $table->string('shelf_life_unit', 20)->default('Bulan')->after('shelf_life_value');
            $table->date('expiry_date')->nullable()->after('shelf_life_unit');

            $table->foreign('supplier_id', 'fk_ingredients_supplier_id')
                ->references('id')->on('suppliers')->nullOnDelete();
        });

        // 3. Enhance products table
        Schema::table('products', function (Blueprint $table) {
            $table->string('category', 100)->default('Kopi')->after('name');
            $table->string('type', 50)->default('Minuman')->after('category');
            $table->decimal('cost_price', 15, 2)->default(0)->after('selling_price');
        });

        // 4. Enhance customers table
        Schema::table('customers', function (Blueprint $table) {
            $table->string('status', 20)->default('Aktif')->after('tier');
            $table->string('segmentation', 50)->default('Reguler')->after('status');
            $table->string('gender', 20)->nullable()->after('segmentation');
            $table->date('birth_date')->nullable()->after('gender');
            $table->text('notes')->nullable()->after('birth_date');
            $table->text('address')->nullable()->after('notes');
            $table->string('avatar_url', 500)->nullable()->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('tables', function (Blueprint $table) {
            $table->dropColumn(['area', 'category', 'table_number', 'operational_status', 'notes']);
        });

        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropForeign('fk_ingredients_supplier_id');
            $table->dropColumn([
                'category',
                'cost_price',
                'min_stock',
                'description',
                'image_url',
                'supplier_id',
                'storage_location',
                'shelf_life_value',
                'shelf_life_unit',
                'expiry_date',
            ]);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['category', 'type', 'cost_price']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'status',
                'segmentation',
                'gender',
                'birth_date',
                'notes',
                'address',
                'avatar_url',
            ]);
        });
    }
};

