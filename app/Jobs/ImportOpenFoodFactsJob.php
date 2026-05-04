<?php

namespace App\Jobs;

use App\Models\Food;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Batch-imports foods from the OpenFoodFacts Brasil dataset via their API.
 * Each job instance fetches one page of products and upserts into the foods table.
 * Dispatch via: ImportOpenFoodFactsJob::dispatch(1) for page 1, etc.
 */
class ImportOpenFoodFactsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 60;

    private const PAGE_SIZE = 100;

    public function __construct(private int $page = 1) {}

    public function handle(): void
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders(['User-Agent' => 'EvoFit/1.0 (contact@evofit.app)'])
                ->get('https://br.openfoodfacts.org/cgi/search.pl', [
                    'action'       => 'process',
                    'json'         => '1',
                    'page'         => $this->page,
                    'page_size'    => self::PAGE_SIZE,
                    'sort_by'      => 'unique_scans_n',
                ]);

            if (!$response->ok()) {
                Log::warning('OpenFoodFacts import failed', ['page' => $this->page, 'status' => $response->status()]);
                return;
            }

            $products = $response->json('products', []);
            $imported = 0;

            foreach ($products as $product) {
                $barcode = $product['code'] ?? null;
                $name    = $product['product_name'] ?? $product['product_name_en'] ?? null;

                if (!$barcode || !$name) {
                    continue;
                }

                $nutriments = $product['nutriments'] ?? [];

                Food::updateOrCreate(
                    ['barcode_ean' => $barcode],
                    [
                        'name'               => mb_substr($name, 0, 150),
                        'category'           => mb_substr($product['food_groups'] ?? '', 0, 80) ?: null,
                        'calories_100g'      => $nutriments['energy-kcal_100g'] ?? $nutriments['energy_100g'] ?? 0,
                        'protein_g'          => $nutriments['proteins_100g'] ?? 0,
                        'carbs_g'            => $nutriments['carbohydrates_100g'] ?? 0,
                        'fat_g'              => $nutriments['fat_100g'] ?? 0,
                        'fiber_g'            => $nutriments['fiber_100g'] ?? null,
                        'sodium_mg'          => isset($nutriments['sodium_100g']) ? $nutriments['sodium_100g'] * 1000 : null,
                        'standard_portion_g' => 100,
                        'is_active'          => true,
                        'source'             => 'openfoodfacts',
                        'external_id'        => (string) $barcode,
                    ]
                );

                $imported++;
            }

            Log::info('OpenFoodFacts page imported', ['page' => $this->page, 'imported' => $imported]);

            // Dispatch next page if this page was full
            if (count($products) === self::PAGE_SIZE) {
                static::dispatch($this->page + 1)->delay(now()->addSeconds(5));
            }
        } catch (\Throwable $e) {
            Log::error('OpenFoodFacts import error', ['page' => $this->page, 'error' => $e->getMessage()]);
            throw $e;
        }
    }
}
