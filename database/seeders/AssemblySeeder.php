<?php

namespace Database\Seeders;

use App\Models\Assembly;
use Illuminate\Database\Seeder;

class AssemblySeeder extends Seeder
{
    public function run(): void
    {
        $jsonPath = database_path('seeders/assemblies.json');

        if (!file_exists($jsonPath)) {
            $this->command->error('assemblies.json not found! Run generate_seeder_json.py first.');
            return;
        }

        $assemblies = json_decode(file_get_contents($jsonPath), true);

        foreach ($assemblies as $data) {
            Assembly::updateOrCreate(
                ['ac_no' => $data['ac_no']],
                $data
            );
        }

        $this->command->info('Seeded ' . count($assemblies) . ' assemblies from JSON.');
    }
}
