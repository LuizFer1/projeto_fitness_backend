<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\PlanPrice;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'code' => 'free',
                'name' => 'Free',
                'description' => 'Plano gratuito com recursos essenciais.',
                'trial_days' => 0,
                'prices' => [],
            ],
            [
                'code' => 'plus',
                'name' => 'Plus',
                'description' => 'Recursos avançados de IA e acompanhamento.',
                'trial_days' => 30,
                'prices' => [
                    ['billing_period' => 'monthly',    'price_cents' => 2990],
                    ['billing_period' => 'semiannual', 'price_cents' => 14990],
                    ['billing_period' => 'annual',     'price_cents' => 24990],
                ],
            ],
            [
                'code' => 'pro',
                'name' => 'Pro',
                'description' => 'Tudo do Plus + análise nutricional ilimitada e planos personalizados.',
                'trial_days' => 30,
                'prices' => [
                    ['billing_period' => 'monthly',    'price_cents' => 4990],
                    ['billing_period' => 'semiannual' , 'price_cents' => 24990],
                    ['billing_period' => 'annual',     'price_cents' => 44990],
                ],
            ],
        ];

        foreach ($plans as $data) {
            $plan = Plan::updateOrCreate(
                ['code' => $data['code']],
                [
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'trial_days' => $data['trial_days'],
                    'is_active' => true,
                ]
            );

            foreach ($data['prices'] as $price) {
                PlanPrice::updateOrCreate(
                    [
                        'plan_id' => $plan->id,
                        'billing_period' => $price['billing_period'],
                        'currency' => 'BRL',
                    ],
                    [
                        'price_cents' => $price['price_cents'],
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
