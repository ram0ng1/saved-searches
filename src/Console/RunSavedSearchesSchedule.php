<?php

/*
 * This file is part of ramon/saved-searches.
 *
 * Copyright (c) 2026 Ramon Guilherme.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ramon\SavedSearches\Console;

use Illuminate\Console\Scheduling\Event;

class RunSavedSearchesSchedule
{
    public function __invoke(Event $event): void
    {
        $event->everyFiveMinutes()->withoutOverlapping();
    }
}
