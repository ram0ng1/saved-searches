import app from 'flarum/forum/app';
import extractText from 'flarum/common/utils/extractText';

import type SavedSearch from '../../common/models/SavedSearch';
import type { Frequency } from '../../common/models/SavedSearch';

export const t = (key: string, params: Record<string, unknown> = {}) => app.translator.trans(`ramon-saved-searches.forum.${key}`, params);

/** Texto puro, para aria-label, title e confirm(). */
export const tText = (key: string, params: Record<string, unknown> = {}) => extractText(t(key, params));

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

/**
 * A busca que a lista de discussões está mostrando: os filtros que ela pede
 * à API (com os de outras extensões, como a tag da página) e o texto
 * montado a partir deles, para o nome bater com o que vai rodar.
 */
export function currentSearch(): { query: string; filters: Record<string, string | string[]> } | null {
  if (!app.search.state.getInitialSearch()) return null;

  const filters: Record<string, string | string[]> = { ...(app.discussions.requestParams().filter || {}) };
  const { q, ...rest } = filters;
  const query = app.search.gambits.from('discussions', typeof q === 'string' ? q : '', rest).trim();

  return query ? { query, filters } : null;
}

/** Salva uma busca; o erro de validação da API vira alerta do próprio core. */
export function saveSearch(query: string, filters: Record<string, string | string[]>, frequency: Frequency = 'daily'): Promise<SavedSearch> {
  return app.store.createRecord<SavedSearch>('saved-searches').save({ query: query.trim(), filters, frequency });
}
