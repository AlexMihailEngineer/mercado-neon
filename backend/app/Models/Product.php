<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class Product extends Model
{
    use HasFactory, Searchable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'external_id',
        'title',
        'description',
        'price',
        'category',
        'url',
        'image_url',
    ];

    /**
     * Get the indexable data array for the model.
     * This defines exactly what gets pushed to the Typesense container.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id'          => (string) $this->id,
            'external_id' => (string) $this->external_id,
            'title'       => (string) $this->title,
            'description' => (string) $this->description,
            // Typesense requires strict floating-point definitions for numeric sorting/filtering
            'price'       => (float) $this->price,
            'category'    => (string) $this->category,
            'image_url'   => (string) $this->image_url,
            // Convert timestamps to Unix epochs for easier temporal querying in Typesense
            'created_at'  => $this->created_at?->timestamp ?? 0,
        ];
    }

    /**
     * The explicit schema for Typesense.
     */
    public function typesenseCollectionSchema(): array
    {
        return [
            'name'   => 'products', // This should match your table or scout index name
            'fields' => [
                ['name' => 'id', 'type' => 'string'],
                ['name' => 'external_id', 'type' => 'string'],
                ['name' => 'title', 'type' => 'string'],
                ['name' => 'description', 'type' => 'string'],
                ['name' => 'price', 'type' => 'float'],
                ['name' => 'category', 'type' => 'string', 'facet' => true], // 'facet' allows filtering by category
                ['name' => 'image_url', 'type' => 'string', 'index' => false, 'optional' => true],
                ['name' => 'created_at', 'type' => 'int64'],
            ],
            'default_sorting_field' => 'created_at',
        ];
    }

    /**
     * A more robust way to specify search parameters for the driver.
     */
    public function typesenseSearchParameters(): array
    {
        return [
            'query_by' => 'title,description,category',
            'sort_by'  => 'created_at:desc',
            'inplace_fields' => 'title,description', // Optimization for highlighting
        ];
    }

    /**
     * Ensure the collection name matches exactly what you defined 
     * in typesenseCollectionSchema.
     */
    public function searchableAs(): string
    {
        return 'products';
    }
}
