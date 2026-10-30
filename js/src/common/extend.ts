import Extend from 'flarum/common/extenders';

import SavedSearch from './models/SavedSearch';

export default [
  new Extend.Store() //
    .add('saved-searches', SavedSearch),
];
