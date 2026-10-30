import app from 'flarum/forum/app';
import Notification from 'flarum/forum/components/Notification';
import type Discussion from 'flarum/common/models/Discussion';

import { t } from '../utils/savedSearches';

interface SavedSearchContent {
  query: string;
  count: number;
  postNumber: number;
}

/**
 * Um resultado leva direto ao post; vários levam à busca.
 */
export default class SavedSearchNotification extends Notification {
  icon() {
    return 'fas fa-bookmark';
  }

  href() {
    const notification = this.attrs.notification;
    const content = notification.content<SavedSearchContent>();
    const discussion = notification.subject() as Discussion | undefined;

    if (content.count > 1 || !discussion) {
      return app.route('index', { q: content.query });
    }

    return app.route.discussion(discussion, content.postNumber);
  }

  content() {
    const content = this.attrs.notification.content<SavedSearchContent>();
    const discussion = this.attrs.notification.subject() as Discussion | undefined;

    if (content.count > 1) {
      return t('notifications.multiple_text', { count: content.count, query: content.query });
    }

    return t('notifications.single_text', { query: content.query, title: discussion?.title() ?? '' });
  }

  excerpt() {
    return null;
  }
}
