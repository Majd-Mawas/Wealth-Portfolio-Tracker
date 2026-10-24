<?php

namespace App\Models;

use App\Enums\AssetType;
use Database\Factories\AssetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asset extends Model
{
    /** @use HasFactory<AssetFactory> */
    use HasFactory;

    protected $fillable = [
        'portfolio_id',
        'type',
        'name',
        'symbol',
        'currency',
        'current_price',
        'price_as_of',
        'metadata',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => AssetType::class,
            'current_price' => 'decimal:8',
            'price_as_of' => 'datetime',
            'metadata' => 'array',
            'archived_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Portfolio, $this> */
    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(Portfolio::class);
    }

    /** @return HasMany<Transaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
