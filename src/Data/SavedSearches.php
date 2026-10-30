<?php

/*
 * This file is part of ramon/saved-searches.
 *
 * Copyright (c) 2026 Ramon Guilherme.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ramon\SavedSearches\Data;

use Flarum\Gdpr\Data\Type;
use Ramon\SavedSearches\SavedSearch;

/**
 * As buscas salvas entram na exportação do flarum/gdpr e saem no apagamento.
 */
class SavedSearches extends Type
{
    public static function exportDescription(): string
    {
        return self::staticTranslator()->trans('ramon-saved-searches.lib.gdpr.export_description');
    }

    public static function anonymizeDescription(): string
    {
        return self::staticTranslator()->trans('ramon-saved-searches.lib.gdpr.anonymize_description');
    }

    public static function deleteDescription(): string
    {
        return self::staticTranslator()->trans('ramon-saved-searches.lib.gdpr.delete_description');
    }

    public function export(): ?array
    {
        $export = [];

        SavedSearch::query()
            ->where('user_id', $this->user->id)
            ->orderBy('id')
            ->each(function (SavedSearch $search) use (&$export) {
                $export[] = [
                    "saved-searches/search-{$search->id}.json" => $this->encodeForExport([
                        'query' => $search->query,
                        'frequency' => $search->frequency,
                        'created_at' => $search->created_at?->toIso8601String(),
                    ]),
                ];
            });

        return $export ?: null;
    }

    public function anonymize(): void
    {
        // A conta continua anônima, mas sem quem leia os avisos.
        $this->delete();
    }

    public function delete(): void
    {
        SavedSearch::query()->where('user_id', $this->user->id)->delete();
    }
}
