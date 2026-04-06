<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RagMatchingService
{
    /**
     * Execute the full RAG workflow for a given query.
     */
    public function executeSemanticSearch(string $userQuery): array
    {
        try {
            // STEP 1: Vectorize the User's Query
            // We use nomic-embed-text to turn "A fast laptop for a cyberpunk hacker" into math.
            $embeddingResponse = Http::timeout(15)->post('http://ollama:11434/api/embeddings', [
                'model'  => 'nomic-embed-text',
                'prompt' => $userQuery,
            ]);

            if ($embeddingResponse->failed()) {
                throw new \Exception('Failed to generate query embedding.');
            }

            $queryVector = $embeddingResponse->json('embedding');

            // STEP 2: Query the FAISS Vector Engine
            // Find the 3 closest mathematical matches to our query vector.
            $faissResponse = Http::timeout(5)->post('http://vector-engine:8000/search', [
                'vector' => $queryVector,
                'top_k'  => 3,
            ]);

            if ($faissResponse->failed()) {
                throw new \Exception('Vector Engine search failed.');
            }

            $matches = $faissResponse->json('results');

            if (empty($matches)) {
                return ['products' => [], 'synthesis' => 'No suitable hardware found in the database.'];
            }

            // Extract the IDs and maintain the exact order returned by FAISS (closest first)
            $productIds = collect($matches)->pluck('product_id')->toArray();

            // STEP 3: Retrieve full Product details from MySQL
            // The FIELD() function ensures MySQL doesn't reorder our perfectly ranked AI results
            $products = Product::whereIn('id', $productIds)
                ->orderByRaw('FIELD(id, ' . implode(',', $productIds) . ')')
                ->get();

            // STEP 4: The "Cyberpunk" Synthesis (LLM Recommendation)
            $synthesis = $this->synthesizeRecommendation($userQuery, $products);

            return [
                'products'  => $products,
                'synthesis' => $synthesis,
            ];
        } catch (\Throwable $e) {
            Log::error('RAG Pipeline Failure: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Use Llama 3 to explain WHY these products match the user's intent.
     */
    private function synthesizeRecommendation(string $query, $products): string
    {
        // Format the retrieved data into a tight context string for the LLM
        $context = $products->map(function ($p) {
            return "- {$p->title}: {$p->price} RON. ({$p->description})";
        })->implode("\n");

        // The Prompt Engineering: Giving the AI a persona and strict rules
        $systemPrompt = "You are an underground tech broker in a cyberpunk market. A client asked you for: '{$query}'. " .
            "Based ONLY on the following available inventory, write a brief, edgy, 2-sentence sales pitch " .
            "explaining why these specific items are what they need. Do not invent items.\n\n" .
            "Inventory:\n" . $context;

        try {
            // We use the /api/generate endpoint for standard text completion
            $llmResponse = Http::timeout(45)->post('http://ollama:11434/api/generate', [
                'model'   => 'qwen2:0.5b',
                'prompt'  => $systemPrompt,
                'stream'  => false, // Set to true later if you want typewriter effects in Vue
                'options' => [
                    'temperature' => 0.7, // A bit of creativity for the cyberpunk vibe
                    'num_predict' => 60, // Keep it short so it doesn't crush your 4GB VRAM
                    'keep_alive' => '1h', // Keep the model in memory for an hour
                ]
            ]);

            return $llmResponse->json('response') ?? 'Here is the hardware you requested. No questions asked.';
        } catch (\Exception $e) {
            Log::warning('LLM Synthesis timed out or failed. Falling back to default message.');
            return 'Here is the hardware you requested. Secure connection established.';
        }
    }
}
