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

Each saved search runs Flarum's discussion search as its owner, newest
activity first (the latest 50 discussions per run). A discussion counts when
it has comments newer than the last check, written by someone else, visible
to the owner and not read yet. Results are discussions, as in the forum
search: a reply in a discussion that matches the search counts even if the
reply itself doesn't contain the words.

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
