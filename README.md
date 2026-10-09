# Simple — starter app

A JSON API starter built on the [Simple framework](framework/README.md) (`lmntricx/simple-framework`). It comes
with registration, login, API tokens, email verification, password reset and user management already wired up.

```bash
composer install
cp .env.example .env
php simple key:generate
php simple migrate
php simple serve          # http://127.0.0.1:8000
```

Then try `POST /auth/register` with `name`, `email` and `password`. Development emails (verification links,
password resets) are written to `storage/logs/mail.log`.

**Documentation:** everything the framework does (routing, ORM, auth, queues, scheduling, mail, storage, testing)
is in [framework/README.md](framework/README.md).

## What's in this repo

```
app/            this app: controllers, models, mail templates, service provider
config/ routes/ database/ public/ storage/ tests/
framework/      the framework package (lmntricx/simple-framework), installed into vendor/ by Composer
```

The framework lives here during development and is installed as a local Composer "path" package (a link from
`vendor/lmntricx/simple-framework` to `framework/`), so changes to it apply immediately.

## Tests

```
composer test                                  # app tests, then framework tests
vendor/bin/phpunit                             # app only
vendor/bin/phpunit -c framework/phpunit.xml    # framework only
```

CI (`.github/workflows/tests.yml`) runs both on PHP 8.2–8.4 with SQLite, MySQL and PostgreSQL.

## Publishing the framework separately

When you want other projects to install the framework with `composer require lmntricx/simple-framework`:

1. Create an empty GitHub repo, e.g. `lmntricx/simple-framework`.
2. Run `bin/split-framework.sh https://github.com/lmntricx/simple-framework.git`. It copies `framework/` with its
   full history into that repo.
3. Tag a release there (`git tag v2.0.0 && git push --tags`) and submit the repo at https://packagist.org.
4. Here, replace the `"repositories"` entry in `composer.json` with `"lmntricx/simple-framework": "^2.0"` once you
   want this app to use the published package.

Run the script again whenever you want to push new framework changes to that repo.
