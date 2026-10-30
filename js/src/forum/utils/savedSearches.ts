import app from 'flarum/forum/app';

import type SavedSearch from '../../common/models/SavedSearch';
import type { Frequency } from '../../common/models/SavedSearch';

export const t = (key: string, params: Record<string, unknown> = {}) => app.translator.trans(`ramon-saved-searches.forum.${key}`, params);

export function canUseSavedSearches(): boolean {
  return !!app.session.user && !!app.forum.attribute<boolean>('canUseSavedSearches');
}

export function maxSavedSearches(): number {
  return app.forum.attribute<number>('savedSearchesMax') || 5;
}

/**
 * Os filtros da API para um texto de busca, do jeito que a lista de
 * discussões os monta: `tag:x author:y` viram filtros, o resto vira `q`.
 */
export function filtersFor(query: string): Record<string, string | string[]> {
  const filters = app.search.gambits.apply('discussions', { q: query });

  if (!filters.q) delete filters.q;

  return filters;
}

/** Salva uma busca; o erro de validação da API vira alerta do próprio core. */
export function saveSearch(query: string, frequency: Frequency = 'daily'): Promise<SavedSearch> {
  return app.store.createRecord<SavedSearch>('saved-searches').save({
    query: query.trim(),
    filters: filtersFor(query.trim()),
    frequency,
  });
}
