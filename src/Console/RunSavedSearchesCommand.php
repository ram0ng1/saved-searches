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

use Flarum\Console\AbstractCommand;
use Ramon\SavedSearches\Search\SavedSearchRunner;

/**
 * `php flarum saved-searches:run` — roda as buscas salvas vencidas.
 * Agendado a cada 5 minutos no extend.php; depende do cron rodar
 * `php flarum schedule:run`.
 */
class RunSavedSearchesCommand extends AbstractCommand
{
    public function __construct(
        protected SavedSearchRunner $runner
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('saved-searches:run')
            ->setDescription('Run the saved searches that are due and notify their owners of new results.');
    }

    protected function fire(): int
    {
        $notified = $this->runner->runDue();

        $this->info("Saved searches with new results: $notified.");

        return 0;
    }
}
