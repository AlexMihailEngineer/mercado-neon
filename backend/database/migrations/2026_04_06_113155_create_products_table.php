<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            // Unique identifier from the external API (e.g., DummyJSON ID)
            $table->string('external_id')->unique();

            $table->string('title');
            $table->text('description');

            // 10 digits total, 2 after the decimal (standard for e-commerce pricing)
            $table->decimal('price', 10, 2);

            $table->string('category');

            // Nullable because the DummyAPI might not provide a direct frontend URL for the item
            $table->string('url')->nullable();

            // URLs can sometimes exceed the default 255 string limit; text is safer for long CDN links
            $table->text('image_url');

            $table->timestamps();

            // Optional: Add an index on category if you plan to filter by it at the database level
            // before hitting Typesense.
            $table->index('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
