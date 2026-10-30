import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import Button from 'flarum/common/components/Button';
import FieldSet from 'flarum/common/components/FieldSet';
import Link from 'flarum/common/components/Link';
import IndexPage from 'flarum/forum/components/IndexPage';
import type ItemList from 'flarum/common/utils/ItemList';
import type Mithril from 'mithril';

import SavedSearchesSettings from './components/SavedSearchesSettings';
import { canUseSavedSearches, currentSearch, saveSearch, t } from './utils/savedSearches';

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

  // "Salvar busca" só aparece com uma busca ativa na lista de discussões.
  extend(IndexPage.prototype, 'actionItems', function (items: ItemList<Mithril.Children>) {
    if (!canUseSavedSearches()) return;

    const search = currentSearch();
    if (!search) return;

    items.add(
      'saveSearch',
      <Button
        className="Button SaveSearchButton"
        icon="far fa-bookmark"
        onclick={(e: MouseEvent) => {
          const button = e.currentTarget as HTMLButtonElement;
          button.disabled = true;

          saveSearch(search.query, search.filters)
            .then(() => {
              app.alerts.show(
                {
                  type: 'success',
                  controls: [
                    <Link className="Button Button--link" href={app.route('settings')}>
                      {t('save_search.manage_link')}
                    </Link>,
                  ],
                },
                t('save_search.saved_message')
              );
            })
            .catch(() => {})
            .finally(() => (button.disabled = false));
        }}
      >
        {t('save_search.button')}
      </Button>,
      50
    );
  });
});
