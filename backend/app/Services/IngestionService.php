<?php

namespace App\Services;

use App\Contracts\DataIngestionStrategy;
use App\Models\Product;
use App\Jobs\GenerateProductEmbedding;
use Illuminate\Support\Facades\Log;

class IngestionService
{
    /**
     * Execute the ingestion process using a specific strategy.
     */
    public function run(DataIngestionStrategy $strategy): void
    {
        try {
            $items = $strategy->fetch();

            foreach ($items as $item) {
                // updateOrCreate ensures we don't duplicate products on multiple runs
                $product = Product::updateOrCreate(
                    ['external_id' => $item['external_id']],
                    [
                        'title'       => $item['title'],
                        'description' => $item['description'],
                        'price'       => $item['price'],
                        'category'    => $item['category'],
                        'url'         => $item['url'],
                        'image_url'   => $item['image_url'],
                    ]
                );

                // Dispatch the embedding job to the background queue
                if ($product->wasRecentlyCreated || $product->wasChanged(['title', 'description', 'category'])) {
                    GenerateProductEmbedding::dispatch($product);
                }
            }

            Log::info('Ingestion complete. Processed ' . count($items) . ' items.');
        } catch (\Throwable $e) {
            Log::error('Ingestion Service Error: ' . $e->getMessage());

            throw $e;
        }
    }
}
