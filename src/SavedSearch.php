<?php

/*
 * This file is part of ramon/saved-searches.
 *
 * Copyright (c) 2026 Ramon Guilherme.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ramon\SavedSearches;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $query
 * @property array<string, string|list<string>> $filters
 * @property string $frequency
 * @property int $last_post_id
 * @property Carbon $last_searched_at
 * @property Carbon|null $last_notified_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 */
class SavedSearch extends AbstractModel
{
    protected $table = 'saved_searches';

    public $timestamps = true;

    protected $casts = [
        'id' => 'integer',
        'user_id' => 'integer',
        'filters' => 'array',
        'last_post_id' => 'integer',
        'last_searched_at' => 'datetime',
        'last_notified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Buscas cuja frequência já venceu.
     *
     * @param Builder<SavedSearch> $query
     */
    public function scopeDue(Builder $query, Carbon $now): void
    {
        $query->where(function (Builder $query) use ($now) {
            foreach (Frequency::ALL as $frequency) {
                $query->orWhere(fn (Builder $query) => $query
                    ->where('frequency', $frequency)
                    ->where('last_searched_at', '<=', Frequency::dueBefore($frequency, $now)));
            }
        });
    }
}
