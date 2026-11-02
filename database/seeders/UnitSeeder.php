<?php

namespace Database\Seeders;

use App\Core\Enums\UnitType;
use App\Modules\Catalog\Models\Unit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UnitSeeder extends Seeder
{
    /**
     * Seed essential measurement units for F&B and coffee shops.
     */
    public function run(): void
    {
        $units = [
            // Weight
            ['name' => 'Kilogram', 'code' => 'KG', 'type' => UnitType::WEIGHT],
            ['name' => 'Gram', 'code' => 'GR', 'type' => UnitType::WEIGHT],
            ['name' => 'Milligram', 'code' => 'MG', 'type' => UnitType::WEIGHT],

            // Volume
            ['name' => 'Liter', 'code' => 'LTR', 'type' => UnitType::VOLUME],
            ['name' => 'Milliliter', 'code' => 'ML', 'type' => UnitType::VOLUME],

            // Count / Pieces
            ['name' => 'Pieces', 'code' => 'PCS', 'type' => UnitType::COUNT],
            ['name' => 'Pack', 'code' => 'PACK', 'type' => UnitType::COUNT],
            ['name' => 'Box', 'code' => 'BOX', 'type' => UnitType::COUNT],
            ['name' => 'Porsi', 'code' => 'PORSI', 'type' => UnitType::COUNT],
        ];

        foreach ($units as $u) {
            Unit::firstOrCreate(
                ['code' => $u['code']],
                [
                    'id' => (string) Str::ulid(),
                    'name' => $u['name'],
                    'type' => $u['type'],
                ]
            );
        }
    }
}
