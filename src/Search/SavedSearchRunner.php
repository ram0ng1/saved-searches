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
 * A busca é a mesma do fórum (SearchManager), feita como o dono: filtros,
 * gambits de outras extensões e permissões valem igual. O resultado são
 * discussões; contam só os comentários novos de outras pessoas que o dono
 * ainda não leu.
 */
class SavedSearchRunner
{
    /** Discussões mais recentes que cada busca examina por rodada. */
    public const CANDIDATES = 50;

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
                        // Uma busca com filtro inválido não pode travar as outras.
                        $this->log->warning('[saved-searches] search '.$search->id.' failed: '.$e->getMessage());
                        $this->advance($search, $ceiling, $now, false);
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

        // Atalho barato antes da busca completa: nenhum comentário novo de outra pessoa.
        $anyNew = Post::query()
            ->where('type', 'comment')
            ->where('id', '>', $search->last_post_id)
            ->where('id', '<=', $ceiling)
            ->where(fn (Builder $query) => $query->whereNull('user_id')->orWhere('user_id', '!=', $user->id))
            ->exists();

        if (! $anyNew) {
            $this->advance($search, $ceiling, $now, false);

            return false;
        }

        $discussionIds = $this->search
            ->query(Discussion::class, new SearchCriteria($user, $this->filters($search), self::CANDIDATES, 0, ['lastPostedAt' => 'desc']))
            ->getResults()
            ->pluck('id')
            ->all();

        $matches = $discussionIds ? $this->newPosts($search, $user, $discussionIds, $ceiling) : [];

        if ($matches === []) {
            $this->advance($search, $ceiling, $now, false);

            return false;
        }

        $latest = Post::query()->with(['discussion', 'user'])->find(max($matches));

        if ($latest === null || $latest->discussion === null) {
            $this->advance($search, $ceiling, $now, false);

            return false;
        }

        $this->notifications->sync(new SavedSearchBlueprint($search, $latest, count($matches)), [$user]);
        $this->advance($search, $ceiling, $now, true);

        return true;
    }

    /**
     * O comentário mais novo de cada discussão encontrada, contando só o
     * que é de outra pessoa, visível ao dono e ainda não lido por ele.
     *
     * @param list<int> $discussionIds
     * @return list<int> ids dos posts, um por discussão
     */
    protected function newPosts(SavedSearch $search, User $user, array $discussionIds, int $ceiling): array
    {
        return Post::whereVisibleTo($user)
            ->whereIn('posts.discussion_id', $discussionIds)
            ->where('posts.type', 'comment')
            ->whereNull('posts.hidden_at')
            ->where('posts.id', '>', $search->last_post_id)
            ->where('posts.id', '<=', $ceiling)
            ->where(fn (Builder $query) => $query->whereNull('posts.user_id')->orWhere('posts.user_id', '!=', $user->id))
            ->leftJoin('discussion_user as saved_search_state', function (JoinClause $join) use ($user) {
                $join->on('saved_search_state.discussion_id', '=', 'posts.discussion_id')
                    ->where('saved_search_state.user_id', '=', $user->id);
            })
            ->where(fn (Builder $query) => $query
                ->whereNull('saved_search_state.last_read_post_number')
                ->orWhereColumn('posts.number', '>', 'saved_search_state.last_read_post_number'))
            ->groupBy('posts.discussion_id')
            ->selectRaw('max(posts.id) as latest_id')
            ->pluck('latest_id')
            ->map(fn ($id) => (int) $id)
            ->values()
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
