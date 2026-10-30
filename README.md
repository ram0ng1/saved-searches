<p align="center">
  <img src="icon.svg" width="80" height="80" alt="Saved Searches">
</p>

<h1 align="center">Saved Searches</h1>

<p align="center">
  <a href="https://github.com/ram0ng1/saved-searches/actions/workflows/ci.yml"><img alt="CI" src="https://img.shields.io/github/actions/workflow/status/ram0ng1/saved-searches/ci.yml?branch=main&style=flat-square&label=ci"></a>
  <a href="https://packagist.org/packages/ramon/saved-searches"><img alt="Packagist" src="https://img.shields.io/packagist/v/ramon/saved-searches?style=flat-square&label=packagist"></a>
  <a href="https://packagist.org/packages/ramon/saved-searches"><img alt="Downloads" src="https://img.shields.io/packagist/dt/ramon/saved-searches?style=flat-square"></a>
  <img alt="Flarum" src="https://img.shields.io/badge/flarum-2.x-e7672e?style=flat-square">
  <a href="LICENSE"><img alt="License" src="https://img.shields.io/badge/license-MIT-blue?style=flat-square"></a>
  <a href="https://donate.stripe.com/fZe5o66nebkf39S28a"><img alt="Donate" src="https://img.shields.io/badge/donate-stripe-6772E5?style=flat-square"></a>
</p>

<p align="center">Save a search and get notified when something new matches it.</p>

Saved Searches lets users keep the searches they care about and hear back when new discussions or replies match them. It works with the same words and filters as the forum search, so whatever a user can find there, they can save. Inspired by Discourse's [saved searches plugin](https://github.com/discourse/discourse-saved-searches).

Each user picks how often each search is checked, and gets one notification per search and run, by alert, email or both, like any other Flarum notification. Only what is new, written by someone else, visible to the user and still unread counts.

## What it does

- **Save Search** button on the search results (core, Canela and the Avocado search page), plus a **Saved Searches** section in the user's settings
- Same words and filters as the forum search (`author:`, `is:unread`, `tag:` with flarum/tags…), run with the user's own permissions
- Checked as soon as possible (every 5 minutes), hourly, daily or weekly, per search
- One notification per search and run, linking to the newest result, or to the search when there is more than one
- *Save searches and receive notifications of new results* permission (members by default) and a per-user limit (5 by default)
- Plays nice with `flarum/gdpr` for export, anonymization and erasure
- Built for Flarum 2.x (PHP 8.3+)

## Installation

```sh
composer require ramon/saved-searches
php flarum migrate
php flarum cache:clear
```

Then enable Saved Searches on the Extensions page. The permission and the per-user limit live in the admin panel.

Saved searches run from Flarum's scheduler, so the server's cron must call `php flarum schedule:run` every minute:

```
* * * * * cd /path/to/flarum && php flarum schedule:run >> /dev/null 2>&1
```

## Good to know

- Each run only looks at comments posted since the search's last check. A discussion counts when one of those comments matches every word of the search (or the discussion title does, case insensitive); the other filters then run through Flarum's discussion search as the owner, on those discussions only.
- That keeps runs cheap on large forums: no fulltext index scan, and the cost grows with the activity since the last check, not with the size of the forum. A run checks up to 1000 discussions per search and stops after 4 minutes; searches it did not reach are picked up by the next one, oldest first.
- On PostgreSQL the query JIT is turned off during a run: the tag permission subqueries made it compile every query for seconds on slow hardware.
- `php flarum saved-searches:run` runs the due searches by hand.

## Development

```sh
composer install
composer test:setup        # once: creates the SQLite test database
composer test              # unit + integration tests
composer analyse:phpstan
cd js && npm ci && npm run check-typings && npm run format-check && npm run build
```

## License

[MIT](LICENSE). Questions and ideas are welcome on the [issue tracker](https://github.com/ram0ng1/saved-searches/issues).
