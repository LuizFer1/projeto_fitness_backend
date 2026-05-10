<?php

namespace Tests\Feature\Food;

use App\Models\Food;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FoodControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_lookup_returns_local_food_when_found(): void
    {
        $user = User::factory()->create();

        Food::create([
            'name' => 'Whey Protein',
            'calories_100g' => 380.00,
            'protein_g' => 80.00,
            'carbs_g' => 5.00,
            'fat_g' => 4.00,
            'standard_portion_g' => 30.00,
            'is_active' => true,
            'barcode_ean' => '7891234567890',
            'source' => 'manual',
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/foods/lookup?barcode=7891234567890')
            ->assertOk();

        $this->assertEquals('local', $response->json('source'));
        $this->assertEquals('Whey Protein', $response->json('data.name'));
    }

    public function test_lookup_falls_back_to_openfoodfacts(): void
    {
        $user = User::factory()->create();

        Http::fake([
            'world.openfoodfacts.org/*' => Http::response([
                'status' => 1,
                'product' => [
                    'product_name' => 'Produto OFF',
                    'code' => '1234567890123',
                    'nutriments' => [
                        'energy-kcal_100g' => 200,
                        'proteins_100g' => 10,
                        'carbohydrates_100g' => 30,
                        'fat_100g' => 5,
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/foods/lookup?barcode=1234567890123')
            ->assertOk();

        $this->assertEquals('openfoodfacts', $response->json('source'));
        $this->assertEquals('Produto OFF', $response->json('data.name'));

        $this->assertDatabaseHas('foods', [
            'barcode_ean' => '1234567890123',
            'source' => 'openfoodfacts',
        ]);
    }

    public function test_lookup_returns_404_when_not_found_anywhere(): void
    {
        $user = User::factory()->create();

        Http::fake([
            'world.openfoodfacts.org/*' => Http::response(['status' => 0], 200),
        ]);

        $this->actingAs($user)
            ->getJson('/api/v1/foods/lookup?barcode=0000000000000')
            ->assertNotFound();
    }

    public function test_lookup_handles_openfoodfacts_timeout_gracefully(): void
    {
        $user = User::factory()->create();

        Http::fake([
            'world.openfoodfacts.org/*' => Http::response(null, 500),
        ]);

        $this->actingAs($user)
            ->getJson('/api/v1/foods/lookup?barcode=9999999999999')
            ->assertNotFound();
    }

    public function test_lookup_requires_barcode_param(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/foods/lookup')
            ->assertUnprocessable();
    }

    public function test_lookup_requires_authentication(): void
    {
        $this->getJson('/api/v1/foods/lookup?barcode=123')->assertUnauthorized();
    }
}
