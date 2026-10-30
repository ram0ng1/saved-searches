<?php

/*
 * This file is part of ramon/saved-searches.
 *
 * Copyright (c) 2026 Ramon Guilherme.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ramon\SavedSearches\Api;

use Flarum\Api\Context;
use Flarum\Api\Schema;
use Flarum\Settings\SettingsRepositoryInterface;
use Ramon\SavedSearches\Api\Resource\SavedSearchResource;

/**
 * O que o fórum precisa para mostrar (ou não) as buscas salvas.
 */
class ForumResourceFields
{
    public function __construct(
        protected SettingsRepositoryInterface $settings
    ) {
    }

    /**
     * @return array<Schema\Attribute>
     */
    public function __invoke(): array
    {
        return [
            Schema\Boolean::make('canUseSavedSearches')
                ->get(fn (object $forum, Context $context) => $context->getActor()->hasPermission('savedSearches.use')),
            Schema\Integer::make('savedSearchesMax')
                ->get(fn () => SavedSearchResource::maxPerUser($this->settings)),
        ];
    }
}
