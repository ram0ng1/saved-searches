# Saved Searches

Save a search on your Flarum 2 forum and get notified when new discussions or
replies match it. Inspired by Discourse's
[saved searches plugin](https://github.com/discourse/discourse-saved-searches).

- **Save from the search page**: run a search and click **Save search**, or
  type one in **Settings → Saved searches**.
- **Same search as the forum**: words and filters work as they do in the
  forum search (`author:`, `is:unread`, `tag:` with flarum/tags…), with the
  member's own permissions.
- **How often**: as soon as possible (every 5 minutes), hourly, daily or
  weekly, per search.
- **One notification per search and run**: it links to the newest result,
  or to the search when there is more than one. Members choose alert and/or
  email in their notification settings, like any other notification.
- **Only what's new and unread**: replies and discussions posted after the
  last check, by someone else, that the member can see and hasn't read yet.
- **Limits and permission**: the *Save searches and get notified of new
  results* permission (members by default) and a per-member maximum
  (5 by default) in the extension settings.
- **flarum/gdpr**: saved searches are included in data exports and removed
  when the account is erased or anonymised.

## Installation

```bash
composer require ramon/saved-searches
php flarum migrate
php flarum cache:clear
```

Saved searches run from Flarum's scheduler, so the server's cron must run
`php flarum schedule:run` every minute:

```
* * * * * cd /path/to/flarum && php flarum schedule:run >> /dev/null 2>&1
```

You can also run them by hand with `php flarum saved-searches:run`.

## How a search matches

Each run looks only at comments posted since the search's last check. A
discussion counts when one of those comments was written by someone else, is
visible to the owner and hasn't been read yet, and, when the search has text,
when every word appears in that comment or in the discussion's title (case
doesn't matter). The other filters (`author:`, `tag:`, `is:unread`…) then run
through Flarum's discussion search as the owner, on those discussions only.

Because only new comments are scanned, a run stays cheap on large forums: it
doesn't use the database's fulltext index, and its cost grows with the
activity since the last check, not with the size of the forum. Each run
checks up to 1000 discussions per search and stops after 4 minutes; searches
it didn't reach are picked up by the next run.

## Updating

```bash
composer update ramon/saved-searches
php flarum migrate
php flarum cache:clear
```

## Links

- [Source](https://github.com/ram0ng1/saved-searches)
- [Issues](https://github.com/ram0ng1/saved-searches/issues)

## License

MIT
