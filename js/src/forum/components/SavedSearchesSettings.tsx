import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Select from 'flarum/common/components/Select';
import type Mithril from 'mithril';

import type SavedSearch from '../../common/models/SavedSearch';
import { FREQUENCIES, type Frequency } from '../../common/models/SavedSearch';
import { maxSavedSearches, saveSearch, t } from '../utils/savedSearches';

/**
 * Lista das buscas salvas na página de configurações: mudar a frequência
 * salva na hora; apagar pede confirmação.
 */
export default class SavedSearchesSettings extends Component {
  loading = true;
  searches: SavedSearch[] = [];
  query = '';
  frequency: Frequency = 'daily';
  adding = false;
  busy: Record<string, boolean> = {};

  oninit(vnode: Mithril.Vnode) {
    super.oninit(vnode);

    app.store
      .find<SavedSearch[]>('saved-searches')
      .then((searches) => {
        this.searches = searches;
      })
      .finally(() => {
        this.loading = false;
        m.redraw();
      });
  }

  view() {
    if (this.loading) return <LoadingIndicator display="block" />;

    const max = maxSavedSearches();
    const full = this.searches.length >= max;

    return (
      <div className="SavedSearchesSettings">
        <p className="helpText">{t('settings.help')}</p>

        {this.searches.length === 0 ? (
          <p className="SavedSearchesSettings-empty">{t('settings.empty')}</p>
        ) : (
          <ul className="SavedSearchesSettings-list">{this.searches.map((search) => this.row(search))}</ul>
        )}

        {full ? (
          <p className="helpText">{t('settings.limit_reached', { max })}</p>
        ) : (
          <form className="SavedSearchesSettings-add" onsubmit={this.add.bind(this)}>
            <input
              className="FormControl"
              aria-label={t('settings.query_label')}
              placeholder={t('settings.query_placeholder')}
              maxlength={255}
              value={this.query}
              oninput={(e: InputEvent) => (this.query = (e.target as HTMLInputElement).value)}
            />
            {this.frequencySelect(this.frequency, (value) => (this.frequency = value))}
            <Button type="submit" className="Button" loading={this.adding} disabled={!this.query.trim()}>
              {t('settings.add_button')}
            </Button>
          </form>
        )}
      </div>
    );
  }

  row(search: SavedSearch) {
    const id = search.id()!;

    return (
      <li className="SavedSearchesSettings-item" key={id}>
        <a className="SavedSearchesSettings-query" href={app.route('index', { q: search.query() })}>
          {search.query()}
        </a>
        {this.frequencySelect(search.frequency(), (value) => this.changeFrequency(search, value), !!this.busy[id])}
        <Button
          className="Button Button--icon Button--link"
          icon="fas fa-trash-alt"
          aria-label={t('settings.delete_button', { query: search.query() })}
          disabled={!!this.busy[id]}
          onclick={() => this.remove(search)}
        />
      </li>
    );
  }

  frequencySelect(value: Frequency, onchange: (value: Frequency) => void, disabled = false) {
    const options: Record<string, Mithril.Children> = {};
    FREQUENCIES.forEach((frequency) => (options[frequency] = t(`frequencies.${frequency}`)));

    return (
      <Select
        className="SavedSearchesSettings-frequency"
        aria-label={t('settings.frequency_label')}
        options={options}
        value={value}
        disabled={disabled}
        onchange={onchange}
      />
    );
  }

  add(e: SubmitEvent) {
    e.preventDefault();
    if (!this.query.trim()) return;

    this.adding = true;

    saveSearch(this.query, this.frequency)
      .then((search) => {
        this.searches.push(search);
        this.query = '';
      })
      .catch(() => {})
      .finally(() => {
        this.adding = false;
        m.redraw();
      });
  }

  changeFrequency(search: SavedSearch, frequency: Frequency) {
    const id = search.id()!;
    const previous = search.frequency();
    this.busy[id] = true;

    // Em caso de erro o Model já devolve o atributo anterior; o select acompanha.
    search
      .save({ frequency })
      .catch(() => search.pushAttributes({ frequency: previous }))
      .finally(() => {
        this.busy[id] = false;
        m.redraw();
      });
  }

  remove(search: SavedSearch) {
    if (!confirm(String(t('settings.delete_confirm', { query: search.query() })))) return;

    const id = search.id()!;
    this.busy[id] = true;

    search
      .delete()
      .then(() => {
        this.searches = this.searches.filter((item) => item !== search);
      })
      .catch(() => {})
      .finally(() => {
        this.busy[id] = false;
        m.redraw();
      });
  }
}
