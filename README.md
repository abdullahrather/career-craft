# CareerCraft

An AI-assisted job search platform. You keep one structured career profile, and CareerCraft turns it into tailored CVs and cover letters, finds jobs that fit, tracks every application, reads employer replies and helps you prepare for interviews. It's built for the German job market first, in English and German.

**Status:** in development. So far: the foundation (Docker, tests, CI), accounts, the landing page, the career profile and importing an existing CV, in English and German with a dark theme.

## Career profile

One structured record of everything you have done, which every CV, cover letter and job match will be built from:

- About you: headline, summary and contact details
- Work experience, with achievements as the bullet points a CV shows
- Education, skills with levels, projects, certificates, and languages at CEFR levels (A1 to C2, or native)
- Links, such as LinkedIn or a portfolio
- Entries can be added, edited, reordered and deleted, and a profile strength score lists what to add next
- An optional photo for German CVs, framed in the browser and saved as a 600 × 800 JPEG without the original file's metadata

## Import

- **From a CV:** upload a PDF or Word file. A local AI model reads the text and suggests what goes where. The file isn't kept, and its text is deleted once it has been read.
- **From LinkedIn:** upload the data export LinkedIn lets you download (a ZIP of CSV files). It's read directly, without AI.
- Either way, you check every field before anything is saved. Personal details you already filled in are only replaced if you tick them, entries the profile already has are marked, and anything that doesn't fit the profile, such as a job without a start date, is listed as left out.

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
| AI | Laravel AI SDK with Ollama, running a free model on your own machine |
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

### Reading CVs with AI

CV import uses [Ollama](https://ollama.com) on your machine, outside Docker. Install it, then download the model once:

```bash
ollama pull qwen2.5:7b
```

A CV takes about 10 to 30 seconds on a laptop with a graphics card. `OLLAMA_URL` and `OLLAMA_MODEL` in `.env` change where Ollama runs and which model it uses. LinkedIn import and everything else work without it.

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
