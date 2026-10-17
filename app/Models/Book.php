<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Book extends Model
{
    /** @use HasFactory<\Database\Factories\BookFactory> */
    use HasFactory;

    protected $fillable = [
        'author_id',
        'isbn',
        'title',
        'description',
        'replacement_price',
        'published_year',
    ];

    protected function casts(): array
    {
        return [
            'replacement_price' => 'decimal:2',
            'published_year' => 'integer',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    public function copies(): HasMany
    {
        return $this->hasMany(BookCopy::class);
    }
}
