<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\PlanFeature;
use Illuminate\Database\Seeder;

final class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'code' => 'starter',
                'name' => 'Starter',
                'description' => 'Cocok untuk barbershop 1 cabang yang baru mulai go-digital.',
                'price_monthly' => 99_000,
                'max_branches' => 1,
                'max_staff' => 5,
                'features' => [
                    'payroll' => 'basic',
                    'branding' => 'logo',
                ],
            ],
            [
                'code' => 'pro',
                'name' => 'Pro',
                'description' => 'Untuk barbershop berkembang hingga 3 cabang dengan skema payroll lengkap.',
                'price_monthly' => 249_000,
                'max_branches' => 3,
                'max_staff' => 20,
                'features' => [
                    'payroll' => 'full',
                    'branding' => 'logo_and_color',
                ],
            ],
            [
                'code' => 'business',
                'name' => 'Business',
                'description' => 'Multi-outlet hingga 10 cabang dengan white-label penuh & fitur lengkap.',
                'price_monthly' => 499_000,
                'max_branches' => 10,
                'max_staff' => null,
                'features' => [
                    'payroll' => 'full_with_slip',
                    'branding' => 'full',
                ],
            ],
        ];

        foreach ($plans as $planData) {
            $features = $planData['features'];
            unset($planData['features']);

            $plan = Plan::query()->updateOrCreate(
                ['code' => $planData['code']],
                $planData + ['is_active' => true]
            );

            foreach ($features as $key => $value) {
                PlanFeature::query()->updateOrCreate(
                    ['plan_id' => $plan->id, 'feature_key' => $key],
                    ['value' => $value]
                );
            }
        }
    }
}
