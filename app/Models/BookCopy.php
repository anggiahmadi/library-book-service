<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookCopy extends Model
{
    /** @use HasFactory<\Database\Factories\BookCopyFactory> */
    use HasFactory;

    public const AVAILABLE = 'AVAILABLE';
    public const LOANED = 'LOANED';
    public const LOST = 'LOST';

    protected $fillable = [
        'book_id',
        'copy_code',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'string',
        ];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function isAvailable(): bool
    {
        return $this->status === self::AVAILABLE;
    }
}
