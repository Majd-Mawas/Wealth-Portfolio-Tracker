<?php

namespace App\Models;

use App\Enums\PortfolioRole;
use Database\Factories\PortfolioFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Portfolio extends Model
{
    /** @use HasFactory<PortfolioFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'base_currency',
    ];

    /** @return HasMany<PortfolioMembership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(PortfolioMembership::class);
    }

    /** @return HasMany<Asset, $this> */
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    /** @return HasManyThrough<Transaction, Asset, $this> */
    public function transactions(): HasManyThrough
    {
        return $this->hasManyThrough(Transaction::class, Asset::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'portfolio_memberships')
            ->withPivot('id', 'role')
            ->withTimestamps();
    }

    public function roleFor(User $user): ?PortfolioRole
    {
        if ($this->pivot?->role !== null && (int) $this->pivot->user_id === (int) $user->getKey()) {
            return PortfolioRole::tryFrom($this->pivot->role);
        }

        $role = $this->memberships()
            ->where('user_id', $user->getKey())
            ->value('role');

        if ($role instanceof PortfolioRole) {
            return $role;
        }

        return is_string($role) ? PortfolioRole::tryFrom($role) : null;
    }
}
