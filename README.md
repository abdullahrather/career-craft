# CareerCraft

An AI-assisted job search platform. You keep one structured career profile, and CareerCraft turns it into tailored CVs and cover letters, finds jobs that fit, tracks every application, reads employer replies and helps you prepare for interviews. It's built for the German job market first, in English and German.

**Status:** in development. This first version is the foundation: the app, its services, tests and CI, and a home page in English and German.

## Stack

| Part | Choice |
|---|---|
| Backend | PHP 8.5, Laravel 13 |
| Interface | Livewire 4, Alpine.js, Tailwind CSS 4, Vite 8 |
| Data | PostgreSQL 18, Redis 8 |
| Email in development | Mailpit |
| Quality | Pest 5, Larastan 3 at the strictest level, Pint |

Everything runs in Docker, so PHP doesn't need to be installed on your machine.

## Run it

You need Docker.

```bash
cp .env.example .env
docker compose run --rm app composer install
docker compose run --rm app php artisan key:generate
docker compose run --rm app php artisan migrate
docker compose up
```

| Address | What |
|---|---|
| http://localhost:8000 | The app |
| http://localhost:8025 | Mailpit, which catches every email the app sends |
| localhost:5433 | PostgreSQL, for a database tool (user and password `careercraft`) |

The first `docker compose up` installs the frontend packages, which takes a minute.

## Checks

The same checks run in CI on every pull request:

```bash
docker compose run --rm app vendor/bin/pest               # tests
docker compose run --rm app vendor/bin/phpstan analyse    # static analysis
docker compose run --rm app vendor/bin/pint --test        # code style
```

Tests use their own database, `careercraft_test`, so they never touch your development data.

## Languages

Every screen is in English and German. Text is written in English inside `__()`, and `lang/de.json` holds the German. A test fails when a text has no German, when German is no longer used, or when a `:placeholder` differs between the two.

The app follows the browser's language until someone picks one from the header.
