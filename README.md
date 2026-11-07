# CareerCraft

An AI-assisted job search platform. You keep one structured career profile, and CareerCraft turns it into tailored CVs and cover letters, finds jobs that fit, tracks every application, reads employer replies and helps you prepare for interviews. It's built for the German job market first, in English and German.

**Status:** in development. So far: the foundation (Docker, tests, CI), accounts, and the landing page, in English and German with a dark theme.

## Accounts

- Sign up with email confirmation, sign in, and reset a forgotten password. The reset form answers the same whether or not an account exists, so it can't be used to find out who has one.
- Two-factor login with an authenticator app, and one-use recovery codes.
- Optional sign-in with Google or GitHub. A Google or GitHub account only links to an existing CareerCraft account when the provider has confirmed the email address, and two-factor login still applies.
- A list of signed-in devices, and signing out all the others.
- Emails go out in the language the person uses the site in.

## Stack

| Part | Choice |
|---|---|
| Backend | PHP 8.5, Laravel 13 |
| Interface | Livewire 4, Alpine.js, Tailwind CSS 4, Vite 8 |
| Data | PostgreSQL 18, Redis 8 |
| Email in development | Mailpit |
| Accounts | Laravel Fortify, Laravel Socialite |
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

### Sign in with Google or GitHub (optional)

The buttons appear once you add your own app keys to `.env`: `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET`, or `GITHUB_CLIENT_ID` and `GITHUB_CLIENT_SECRET`. Set the callback URL in the provider's settings to `http://localhost:8000/auth/google/callback` or `http://localhost:8000/auth/github/callback`.

## Checks

The same checks run in CI on every pull request:

```bash
docker compose run --rm app vendor/bin/pest               # tests
docker compose run --rm app vendor/bin/phpstan analyse    # static analysis
docker compose run --rm app vendor/bin/pint --test        # code style
```

Tests use their own database, `careercraft_test`, so they never touch your development data.

## Languages

Every screen and email is in English and German. Text is written in English inside `__()`, and `lang/de.json` holds the German. Validation and sign-in messages are in `lang/de/*.php`. A test fails when a text has no German, when German is no longer used, when a `:placeholder` differs between the two, or when a German language file is missing a message.

The app follows the browser's language until someone picks one from the header.

## Releases

Every merged pull request is tagged and released automatically: a patch release by default, a minor release with the `release:minor` label, and none with `skip-release`.

## License

[MIT](LICENSE)
