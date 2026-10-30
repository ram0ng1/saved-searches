import Model from 'flarum/common/Model';

export type Frequency = 'immediately' | 'hourly' | 'daily' | 'weekly';

export const FREQUENCIES: Frequency[] = ['immediately', 'hourly', 'daily', 'weekly'];

export default class SavedSearch extends Model {
  query() {
    return Model.attribute<string>('query').call(this);
  }

  filters() {
    return Model.attribute<Record<string, string | string[]>>('filters').call(this);
  }

  frequency() {
    return Model.attribute<Frequency>('frequency').call(this);
  }

  createdAt() {
    return Model.attribute<Date, string>('createdAt', Model.transformDate).call(this);
  }

  lastNotifiedAt() {
    return Model.attribute<Date | null, string | null>('lastNotifiedAt', Model.transformDate).call(this);
  }
}
