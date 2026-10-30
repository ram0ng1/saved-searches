import app from 'flarum/admin/app';
import Extend from 'flarum/common/extenders';

export default [
  new Extend.Admin()
    .setting(() => ({
      setting: 'ramon-saved-searches.max_per_user',
      type: 'number',
      min: 1,
      max: 50,
      label: app.translator.trans('ramon-saved-searches.admin.settings.max_per_user_label'),
      help: app.translator.trans('ramon-saved-searches.admin.settings.max_per_user_help'),
    }))
    .permission(
      () => ({
        icon: 'fas fa-bookmark',
        label: app.translator.trans('ramon-saved-searches.admin.permissions.use_label'),
        permission: 'savedSearches.use',
      }),
      'view'
    ),
];
