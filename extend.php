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

use Flarum\Api\Resource\ForumResource;
use Flarum\Discussion\Search\DiscussionSearcher;
use Flarum\Extend;
use Flarum\Gdpr\Extend\UserData;
use Flarum\Search\Database\DatabaseSearchDriver;
use Flarum\User\User;
use Ramon\SavedSearches\Api\ForumResourceFields;
use Ramon\SavedSearches\Api\Resource\SavedSearchResource;
use Ramon\SavedSearches\Console\RunSavedSearchesCommand;
use Ramon\SavedSearches\Console\RunSavedSearchesSchedule;
use Ramon\SavedSearches\Notification\SavedSearchBlueprint;
use Ramon\SavedSearches\Search\DiscussionIdFilter;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js'),

    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\View())
        ->namespace('ramon-saved-searches', __DIR__.'/views'),

    (new Extend\Settings())
        ->default('ramon-saved-searches.max_per_user', 5),

    (new Extend\Model(User::class))
        ->hasMany('savedSearches', SavedSearch::class, 'user_id'),

    new Extend\ApiResource(SavedSearchResource::class),

    (new Extend\ApiResource(ForumResource::class))
        ->fields(ForumResourceFields::class),

    // Por e-mail só se o membro ligar: "assim que possível" mandaria um a cada 5 minutos.
    (new Extend\Notification())
        ->type(SavedSearchBlueprint::class, ['alert']),

    (new Extend\SearchDriver(DatabaseSearchDriver::class))
        ->addFilter(DiscussionSearcher::class, DiscussionIdFilter::class),

    // A cada 5 minutos, para a frequência "imediata"; as outras esperam vencer.
    (new Extend\Console())
        ->command(RunSavedSearchesCommand::class)
        ->schedule(RunSavedSearchesCommand::class, RunSavedSearchesSchedule::class),

    (new Extend\Conditional())
        ->whenExtensionEnabled('flarum-gdpr', fn () => [
            (new UserData())
                ->addType(Data\SavedSearches::class),
        ]),
];
