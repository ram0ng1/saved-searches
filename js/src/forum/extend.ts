import Extend from 'flarum/common/extenders';

import commonExtend from '../common/extend';
import SavedSearchNotification from './components/SavedSearchNotification';

export default [
  ...commonExtend,

  new Extend.Notification() //
    .add('savedSearch', SavedSearchNotification),
];
