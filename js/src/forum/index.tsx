import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import Button from 'flarum/common/components/Button';
import FieldSet from 'flarum/common/components/FieldSet';
import Link from 'flarum/common/components/Link';
import IndexPage from 'flarum/forum/components/IndexPage';
import NotificationGrid from 'flarum/forum/components/NotificationGrid';
import SettingsPage from 'flarum/forum/components/SettingsPage';
import type ItemList from 'flarum/common/utils/ItemList';
import type Mithril from 'mithril';

import SavedSearchesSettings from './components/SavedSearchesSettings';
import { canUseSavedSearches, saveSearch, t } from './utils/savedSearches';

export { default as extend } from './extend';

app.initializers.add('ramon-saved-searches', () => {
  extend(SettingsPage.prototype, 'settingsItems', function (items: ItemList<Mithril.Children>) {
    if (!canUseSavedSearches()) return;

    items.add(
      'savedSearches',
      <FieldSet className="Settings-savedSearches FieldSet--min" label={t('settings.heading')}>
        <SavedSearchesSettings />
      </FieldSet>,
      65
    );
  });

  extend(NotificationGrid.prototype, 'notificationTypes', function (items: ItemList<any>) {
    if (!canUseSavedSearches()) return;

    items.add('savedSearch', {
      name: 'savedSearch',
      icon: 'fas fa-bookmark',
      label: t('settings.notify_label'),
    });
  });

  // "Salvar esta busca" só aparece com uma busca ativa na lista de discussões.
  extend(IndexPage.prototype, 'actionItems', function (items: ItemList<Mithril.Children>) {
    const query = app.search.state.params().q;

    if (!query || !canUseSavedSearches()) return;

    items.add(
      'saveSearch',
      <Button
        className="Button SaveSearchButton"
        icon="far fa-bookmark"
        onclick={(e: MouseEvent) => {
          const button = e.currentTarget as HTMLButtonElement;
          button.disabled = true;

          saveSearch(query)
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
