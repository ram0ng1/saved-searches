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

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Flarum\Notification\NotificationSyncer;
use Flarum\Post\Post;
use Flarum\Search\SearchCriteria;
use Flarum\Search\SearchManager;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Psr\Log\LoggerInterface;
use Ramon\SavedSearches\Notification\SavedSearchBlueprint;
use Ramon\SavedSearches\SavedSearch;
use Throwable;

/**
 * Roda as buscas salvas vencidas e avisa o dono do que apareceu desde a
 * última vez.
 *
 * Primeiro acha as discussões com comentários novos que contam para o dono
 * (de outra pessoa, visíveis a ele e ainda não lidos); depois roda a busca
 * do fórum (SearchManager) como o dono, só nessas discussões. Filtros,
 * gambits de outras extensões e permissões valem igual à busca normal.
 */
class SavedSearchRunner
{
    /** Discussões com novidade examinadas por busca e rodada. */
    public const MAX_CANDIDATES = 1000;

    public function __construct(
        protected SearchManager $search,
        protected NotificationSyncer $notifications,
        protected LoggerInterface $log
    ) {
    }

    /**
     * @return int quantas buscas geraram notificação
     */
    public function runDue(?Carbon $now = null): int
    {
        $now ??= Carbon::now();
        // Teto fixo da rodada: posts criados enquanto ela roda ficam para a próxima.
        $ceiling = (int) Post::query()->max('id');
        $notified = 0;

        SavedSearch::query()
            ->due($now)
            ->with('user')
            ->chunkById(100, function ($searches) use ($ceiling, $now, &$notified) {
                foreach ($searches as $search) {
                    try {
                        $notified += $this->run($search, $ceiling, $now) ? 1 : 0;
                    } catch (Throwable $e) {
                        // Uma busca com problema não trava as outras e não perde
                        // resultados: o marcador fica onde estava.
                        $this->log->warning('[saved-searches] search '.$search->id.' failed: '.$e->getMessage());
                        $search->last_searched_at = $now;
                        $search->save();
                    }
                }
            });

        return $notified;
    }

    public function run(SavedSearch $search, int $ceiling, Carbon $now): bool
    {
        $user = $search->user;

        if (! $user || ! $this->canRun($user) || $ceiling <= $search->last_post_id) {
            $this->advance($search, $ceiling, $now, false);

            return false;
        }

        $candidates = $this->newPosts($search, $user, $ceiling);

        if ($candidates === []) {
            $this->advance($search, $ceiling, $now, false);

            return false;
        }

        $filters = $this->filters($search);
        $filters['id'] = array_keys($candidates);

        $matched = $this->search
            ->query(Discussion::class, new SearchCriteria($user, $filters, count($candidates), 0, ['lastPostedAt' => 'desc']))
            ->getResults()
            ->pluck('id')
            ->all();

        $matches = array_intersect_key($candidates, array_flip($matched));

        if ($matches === []) {
            $this->advance($search, $ceiling, $now, false);

            return false;
        }

        $latest = Post::query()->with(['discussion', 'user'])->find(max($matches));

        if ($latest === null || $latest->discussion === null) {
            $this->advance($search, $ceiling, $now, false);

            return false;
        }

        try {
            $this->notifications->sync(new SavedSearchBlueprint($search, $latest, count($matches)), [$user]);
        } catch (Throwable $e) {
            // Na fila sync o e-mail sai aqui dentro: servidor de e-mail fora do
            // ar não desfaz o aviso que já foi gravado no sino.
            $this->log->warning('[saved-searches] notification delivery for search '.$search->id.' failed: '.$e->getMessage());
        }

        $this->advance($search, $ceiling, $now, true);

        return true;
    }

    /**
     * Discussões com comentário novo que conta para o dono: de outra
     * pessoa, visível a ele e ainda não lido (nem pela leitura da
     * discussão, nem pelo "marcar tudo como lido").
     *
     * @return array<int, int> id da discussão => id do comentário mais novo
     */
    protected function newPosts(SavedSearch $search, User $user, int $ceiling): array
    {
        $grammar = Post::query()->getQuery()->getGrammar();

        return Post::whereVisibleTo($user)
            ->where('posts.type', 'comment')
            ->whereNull('posts.hidden_at')
            ->where('posts.id', '>', $search->last_post_id)
            ->where('posts.id', '<=', $ceiling)
            ->where(fn (Builder $query) => $query->whereNull('posts.user_id')->orWhere('posts.user_id', '!=', $user->id))
            ->when($user->marked_all_as_read_at, fn (Builder $query, $markedAt) => $query->where('posts.created_at', '>', $markedAt))
            ->leftJoin('discussion_user as saved_search_state', function (JoinClause $join) use ($user) {
                $join->on('saved_search_state.discussion_id', '=', 'posts.discussion_id')
                    ->where('saved_search_state.user_id', '=', $user->id);
            })
            ->where(fn (Builder $query) => $query
                ->whereNull('saved_search_state.last_read_post_number')
                ->orWhereColumn('posts.number', '>', 'saved_search_state.last_read_post_number'))
            ->groupBy('posts.discussion_id')
            // wrap() aplica o prefixo de tabela, que o SQL cru não recebe.
            ->selectRaw($grammar->wrap('posts.discussion_id').' as discussion_id, max('.$grammar->wrap('posts.id').') as latest_id')
            ->orderByDesc('latest_id')
            ->limit(self::MAX_CANDIDATES)
            ->pluck('latest_id', 'discussion_id')
            ->map(fn ($id) => (int) $id)
            ->mapWithKeys(fn (int $id, $discussionId) => [(int) $discussionId => $id])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function filters(SavedSearch $search): array
    {
        $filters = is_array($search->filters) ? $search->filters : [];

        return $filters ?: ['q' => $search->query];
    }

    /**
     * Suspensos caem para o grupo de visitantes no flarum/suspend e perdem a
     * permissão por aí.
     */
    protected function canRun(User $user): bool
    {
        return $user->is_email_confirmed && $user->hasPermission('savedSearches.use');
    }

    protected function advance(SavedSearch $search, int $ceiling, Carbon $now, bool $notified): void
    {
        $search->last_post_id = max($search->last_post_id, $ceiling);
        $search->last_searched_at = $now;

        if ($notified) {
            $search->last_notified_at = $now;
        }

        $search->save();
    }
}
