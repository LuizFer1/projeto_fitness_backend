<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Plan;
use App\Models\PlanPrice;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            ['code' => 'free', 'name' => 'Free', 'trial_days' => 0],
            ['code' => 'plus', 'name' => 'Plus', 'trial_days' => 30],
            ['code' => 'pro',  'name' => 'Pro',  'trial_days' => 30],
        ];

        foreach ($plans as $planData) {
            Plan::updateOrCreate(
                ['code' => $planData['code']],
                array_merge($planData, [
                    'is_active' => true,
                ])
            );
        }

        $prices = [
            ['plan_code' => 'plus', 'billing_period' => 'monthly',    'price_cents' => 2990],
            ['plan_code' => 'plus', 'billing_period' => 'semiannual', 'price_cents' => 14990],
            ['plan_code' => 'plus', 'billing_period' => 'annual',     'price_cents' => 27990],
            ['plan_code' => 'pro',  'billing_period' => 'monthly',    'price_cents' => 4990],
            ['plan_code' => 'pro',  'billing_period' => 'semiannual', 'price_cents' => 24990],
            ['plan_code' => 'pro',  'billing_period' => 'annual',     'price_cents' => 45990],
        ];

        foreach ($prices as $priceData) {
            $plan = Plan::where('code', $priceData['plan_code'])->first();
            
            if ($plan) {
                PlanPrice::updateOrCreate(
                    [
                        'plan_uuid' => $plan->uuid,
                        'billing_period' => $priceData['billing_period']
                    ],
                    [
                        'price_cents' => $priceData['price_cents'],
                        'currency'    => 'BRL',
                        'is_active'   => true,
                    ]
                );
            }
        }
    }
}
