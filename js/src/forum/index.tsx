import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import FieldSet from 'flarum/common/components/FieldSet';
import IndexPage from 'flarum/forum/components/IndexPage';
import type ItemList from 'flarum/common/utils/ItemList';
import type Mithril from 'mithril';

import SavedSearchesSettings from './components/SavedSearchesSettings';
import SaveSearchButton from './components/SaveSearchButton';
import addAvocadoSaveSearch from './integrations/avocado';
import { canUseSavedSearches, currentSearch, t } from './utils/savedSearches';

export { default as extend } from './extend';

app.initializers.add('ramon-saved-searches', () => {
  // Configurações e grade de notificações são carregadas sob demanda: o extend
  // pelo nome do módulo espera o chunk chegar.
  extend('flarum/forum/components/SettingsPage', 'settingsItems', function (items: ItemList<Mithril.Children>) {
    if (!canUseSavedSearches()) return;

    items.add(
      'savedSearches',
      <FieldSet className="Settings-savedSearches FieldSet--min" label={t('settings.heading')}>
        <SavedSearchesSettings />
      </FieldSet>,
      65
    );
  });

  extend('flarum/forum/components/NotificationGrid', 'notificationTypes', function (items: ItemList<any>) {
    if (!canUseSavedSearches()) return;

    items.add('savedSearch', {
      name: 'savedSearch',
      icon: 'fas fa-bookmark',
      label: t('settings.notify_label'),
    });
  });

  // "Salvar Busca" só aparece com uma busca ativa na lista de discussões.
  extend(IndexPage.prototype, 'actionItems', function (items: ItemList<Mithril.Children>) {
    if (canUseSavedSearches() && currentSearch()) {
      items.add('saveSearch', <SaveSearchButton />, 50);
    }
  });

  addAvocadoSaveSearch();
});
