<?php

/*
 * This file is part of ramon/saved-searches.
 *
 * Copyright (c) 2026 Ramon Guilherme.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ramon\SavedSearches\Api\Resource;

use Carbon\Carbon;
use Closure;
use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Api\Sort\SortColumn;
use Flarum\Foundation\ValidationException;
use Flarum\Locale\TranslatorInterface;
use Flarum\Post\Post;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Ramon\SavedSearches\Frequency;
use Ramon\SavedSearches\SavedSearch;
use Tobyz\JsonApiServer\Context as OriginalContext;

/**
 * As buscas salvas de quem está logado. Cada pessoa só enxerga as próprias:
 * o escopo filtra pelo dono, então editar ou apagar a de outra dá 404.
 *
 * @extends AbstractDatabaseResource<SavedSearch>
 */
class SavedSearchResource extends AbstractDatabaseResource
{
    /** Tamanho máximo dos filtros guardados, em JSON. */
    public const MAX_FILTERS_LENGTH = 1000;

    public function __construct(
        protected SettingsRepositoryInterface $settings,
        protected TranslatorInterface $translator
    ) {
    }

    public function type(): string
    {
        return 'saved-searches';
    }

    public function model(): string
    {
        return SavedSearch::class;
    }

    public function scope(Builder $query, OriginalContext $context): void
    {
        $query->where('user_id', (int) $context->getActor()->id);
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()
                ->authenticated()
                ->defaultSort('createdAt'),
            Endpoint\Create::make()
                ->authenticated()
                ->can('savedSearches.use'),
            Endpoint\Update::make()
                ->authenticated()
                ->can('savedSearches.use'),
            Endpoint\Delete::make()
                ->authenticated(),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\Str::make('query')
                ->requiredOnCreate()
                ->writable()
                ->minLength(1)
                ->maxLength(255)
                ->set(fn (SavedSearch $search, string $value) => $search->query = trim($value)),
            Schema\Arr::make('filters')
                ->writable()
                ->rule(fn () => $this->filtersRule()),
            Schema\Str::make('frequency')
                ->writable()
                ->in(Frequency::ALL),
            Schema\DateTime::make('createdAt'),
            Schema\DateTime::make('lastNotifiedAt'),
        ];
    }

    public function sorts(): array
    {
        return [
            SortColumn::make('createdAt'),
        ];
    }

    public function newModel(OriginalContext $context): object
    {
        $search = new SavedSearch();

        if ($context->creating(self::class)) {
            $search->user_id = (int) $context->getActor()->id;
            $search->frequency = Frequency::DAILY;
            $search->filters = [];
            // Só o que for publicado depois de salvar conta como novidade.
            $search->last_post_id = (int) Post::query()->max('id');
            $search->last_searched_at = Carbon::now();
        }

        return $search;
    }

    public function creating(object $model, OriginalContext $context): ?object
    {
        $max = max(1, (int) $this->settings->get('ramon-saved-searches.max_per_user', 5));
        $count = SavedSearch::query()->where('user_id', $model->user_id)->count();

        if ($count >= $max) {
            throw new ValidationException([
                'query' => $this->translator->trans('ramon-saved-searches.api.limit_reached', ['{max}' => $max]),
            ]);
        }

        if (SavedSearch::query()->where('user_id', $model->user_id)->where('query', $model->query)->exists()) {
            throw new ValidationException([
                'query' => $this->translator->trans('ramon-saved-searches.api.already_saved'),
            ]);
        }

        return parent::creating($model, $context);
    }

    /**
     * Filtros como a API de discussões recebe: chaves curtas e valores texto
     * (ou lista de textos). Qualquer outra forma derruba a busca agendada.
     */
    protected function filtersRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            if (! is_array($value) || strlen((string) json_encode($value)) > self::MAX_FILTERS_LENGTH) {
                $fail($this->translator->trans('ramon-saved-searches.api.invalid_filters'));

                return;
            }

            foreach ($value as $key => $item) {
                $valid = is_string($key) && preg_match('/^-?[A-Za-z][A-Za-z0-9_.-]{0,39}$/', $key)
                    && (is_string($item) || (is_array($item) && array_is_list($item) && array_filter($item, 'is_string') === $item));

                if (! $valid) {
                    $fail($this->translator->trans('ramon-saved-searches.api.invalid_filters'));

                    return;
                }
            }
        };
    }
}
