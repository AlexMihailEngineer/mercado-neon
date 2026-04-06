<?php

namespace App\Strategies;

use App\Contracts\DataIngestionStrategy;
use Illuminate\Support\Facades\Http;

class HardwareApiStrategy implements DataIngestionStrategy
{
    protected array $categories = ['laptops', 'smartphones'];

    public function fetch(): array
    {
        $normalizedData = [];

        foreach ($this->categories as $category) {
            $response = Http::get("https://dummyjson.com/products/category/{$category}");

            if ($response->successful()) {
                $products = $response->json('products');

                foreach ($products as $item) {
                    $normalizedData[] = [
                        // Generate a unique external_id prefixed to avoid collisions
                        'external_id' => 'dummyjson_' . $item['id'],
                        'title'       => $item['title'],
                        'description' => $item['description'],
                        'price'       => (float) $item['price'],
                        'category'    => $item['category'],
                        'url'         => null, // DummyJSON doesn't provide a direct product URL
                        'image_url'   => $item['thumbnail'],
                    ];
                }
            }
        }

        return $normalizedData;
    }
}
