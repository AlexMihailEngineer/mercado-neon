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
            'created_at'  => $this->created_at->timestamp,
        ];
    }
}
