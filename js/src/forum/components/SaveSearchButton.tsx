import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import Link from 'flarum/common/components/Link';
import type DiscussionListState from 'flarum/forum/states/DiscussionListState';
import type Mithril from 'mithril';

import { canUseSavedSearches, currentSearch, saveSearch, t } from '../utils/savedSearches';

interface SaveSearchButtonAttrs {
  /** A lista cuja busca é salva; páginas de busca de temas têm a própria. */
  state?: DiscussionListState;
  className?: string;
}

/**
 * "Salvar Busca" para a lista de discussões que está sendo mostrada. Usa as
 * classes de botão do core, então segue o tema ativo.
 */
export default class SaveSearchButton extends Component<SaveSearchButtonAttrs> {
  saving = false;

  view(): Mithril.Children {
    const search = canUseSavedSearches() ? currentSearch(this.attrs.state) : null;
    if (!search) return null;

    return (
      <Button
        className={`Button SaveSearchButton ${this.attrs.className || ''}`}
        icon="far fa-bookmark"
        loading={this.saving}
        onclick={() => this.save(search.query, search.filters)}
      >
        {t('save_search.button')}
      </Button>
    );
  }

  save(query: string, filters: Record<string, string | string[]>) {
    this.saving = true;

    saveSearch(query, filters)
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
      .finally(() => {
        this.saving = false;
        m.redraw();
      });
  }
}
