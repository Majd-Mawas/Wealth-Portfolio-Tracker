<?php

namespace App\Models;

use App\Enums\TransactionKind;
use App\Enums\TransactionSource;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory;

    protected $fillable = [
        'asset_id',
        'created_by',
        'kind',
        'quantity_delta',
        'unit_price',
        'fees',
        'occurred_at',
        'note',
        'source',
        'reversal_of_id',
    ];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Transactions are append-only and cannot be updated.');
        });

        static::deleting(function (): never {
            throw new LogicException('Transactions are append-only and cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'kind' => TransactionKind::class,
            'quantity_delta' => 'decimal:8',
            'unit_price' => 'decimal:8',
            'fees' => 'decimal:8',
            'occurred_at' => 'datetime',
            'source' => TransactionSource::class,
        ];
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<Transaction, $this> */
    public function reversedTransaction(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    /** @return HasOne<Transaction, $this> */
    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'reversal_of_id');
    }
}
