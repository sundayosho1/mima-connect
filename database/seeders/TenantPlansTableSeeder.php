<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Master\TenantPlan;
use Illuminate\Database\Seeder;

/**
 * Tenant Plans Table Seeder
 * 
 * Seeds the default subscription plans for the MiMaConnect platform.
 * 
 * @package Database\Seeders
 */
class TenantPlansTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = TenantPlan::getDefaultPlans();

        foreach ($plans as $plan) {
            TenantPlan::updateOrCreate(
                ['slug' => $plan['slug']],
                $plan
            );
        }

        $this->command->info('Tenant plans seeded successfully: ' . count($plans) . ' plans');
    }
}
