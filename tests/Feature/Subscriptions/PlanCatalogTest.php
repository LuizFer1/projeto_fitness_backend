<?php

namespace Tests\Feature\Subscriptions;

use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_is_public_and_returns_active_plans_with_prices(): void
    {
        $this->seed(PlanSeeder::class);

        $response = $this->getJson('/api/v1/plans/catalog');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    ['code', 'name', 'trial_days', 'prices'],
                ],
            ]);

        $codes = collect($response->json('data'))->pluck('code')->all();
        $this->assertContains('free', $codes);
        $this->assertContains('plus', $codes);
        $this->assertContains('pro', $codes);
    }
}
