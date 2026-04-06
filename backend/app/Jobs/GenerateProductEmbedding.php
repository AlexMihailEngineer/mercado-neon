<?php

namespace App\Jobs;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GenerateProductEmbedding implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     * Network calls to AI services can occasionally timeout.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The maximum number of seconds to allow before failing.
     * * @var int
     */
    public $timeout = 120;

    protected Product $product;

    /**
     * Create a new job instance.
     */
    public function __construct(Product $product)
    {
        $this->product = $product;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // 1. Prepare the semantic payload
        // We combine category, title, and description so the AI understands the full context.
        $textToEmbed = sprintf(
            "Category: %s. Product: %s. Description: %s",
            $this->product->category,
            $this->product->title,
            $this->product->description
        );

        try {
            // 2. Request the vector from the local Ollama container
            $ollamaResponse = Http::timeout(60)->post('http://ollama:11434/api/embeddings', [
                'model'  => 'nomic-embed-text',
                'prompt' => $textToEmbed,
            ]);

            if ($ollamaResponse->failed()) {
                throw new \Exception('Ollama Embedding API failed: ' . $ollamaResponse->body());
            }

            $embedding = $ollamaResponse->json('embedding');

            if (empty($embedding)) {
                throw new \Exception('Ollama returned an empty embedding array.');
            }

            // 3. Upsert the vector to the Python FAISS engine
            $vectorResponse = Http::timeout(10)->post('http://vector-engine:8000/upsert', [
                'product_id' => $this->product->id,
                'vector'     => $embedding,
            ]);

            if ($vectorResponse->failed()) {
                throw new \Exception('Vector Engine Upsert failed: ' . $vectorResponse->body());
            }

            Log::info("Successfully generated and stored embedding for Product ID: {$this->product->id}");
        } catch (\Throwable $e) {
            Log::error("Embedding Pipeline Error for Product {$this->product->id}: " . $e->getMessage());

            // Re-throw so the Laravel Queue worker knows the job failed and can retry it
            throw $e;
        }
    }
}
