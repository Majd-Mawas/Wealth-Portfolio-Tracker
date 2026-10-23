<?php

namespace App\Models;

use App\Enums\PortfolioRole;
use Database\Factories\PortfolioMembershipFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortfolioMembership extends Model
{
    /** @use HasFactory<PortfolioMembershipFactory> */
    use HasFactory;

    protected $fillable = [
        'portfolio_id',
        'user_id',
        'role',
    ];

    protected function casts(): array
    {
        return [
            'role' => PortfolioRole::class,
        ];
    }

    /** @return BelongsTo<Portfolio, $this> */
    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(Portfolio::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
