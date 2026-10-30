<?php

/*
 * This file is part of ramon/saved-searches.
 *
 * Copyright (c) 2026 Ramon Guilherme.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ramon\SavedSearches\Search;

use Flarum\Search\Database\DatabaseSearchState;
use Flarum\Search\Filter\FilterInterface;
use Flarum\Search\SearchState;
use Flarum\Search\ValidateFilterTrait;

/**
 * `filter[id]` na busca de discussões, como o do core para posts. A busca
 * agendada usa para rodar a busca do membro só nas discussões com
 * novidade; pela API só restringe o que a pessoa já podia ver.
 *
 * @implements FilterInterface<DatabaseSearchState>
 */
class DiscussionIdFilter implements FilterInterface
{
    use ValidateFilterTrait;

    public function getFilterKey(): string
    {
        return 'id';
    }

    public function filter(SearchState $state, string|array $value, bool $negate): void
    {
        $state->getQuery()->whereIn('discussions.id', $this->asIntArray($value), 'and', $negate);
    }
}
