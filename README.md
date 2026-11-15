# CareerCraft

An AI-assisted job search platform. You keep one structured career profile, and CareerCraft turns it into tailored CVs and cover letters, finds jobs that fit, tracks every application, reads employer replies and helps you prepare for interviews. It's built for the German job market first, in English and German.

**Status:** in development. So far: the foundation (Docker, tests, CI), accounts, the landing page, the career profile, importing an existing CV, the CV builder, an AI writing assistant with a fact guard, and job analysis, in English and German with a dark theme.

## Career profile

One structured record of everything you have done, which every CV, cover letter and job match will be built from:

- About you: headline, summary and contact details
- Work experience, with achievements as the bullet points a CV shows
- Education, skills with levels, projects, certificates, and languages at CEFR levels (A1 to C2, or native)
- Links, such as LinkedIn or a portfolio
- Entries can be added, edited, reordered and deleted, and a profile strength score lists what to add next
- An optional photo for German CVs, framed in the browser and saved as a 600 × 800 JPEG without the original file's metadata

## Writing assistant

Under the summary, achievements and descriptions in the profile, the AI suggests better wording:

- **Improve bullets:** a strong verb and a clear result for each achievement. Where a result has no number, it leaves a placeholder such as [X%] for you to fill in rather than inventing one.
- **Rewrite** the summary in two to four plain sentences.
- **Fix tone:** professional and confident, without changing the facts.
- **Translate** between English and German, with the job titles and phrases CVs use in each language.

Each suggestion is shown as a word-by-word diff to accept or reject, bullet by bullet. An accepted suggestion goes into the field, and nothing is saved until you press Save. It works with the free local model; each text goes to the AI on its own, which keeps a small model from mixing bullets up.

### Fact guard

Every suggestion is checked against your profile and the text it rewrites, so the AI can't slip an invented fact into your CV. A new employer, customer, school, degree, certificate, job title, technology, number or date is listed under the suggestion, which can't be accepted until you confirm it's true. A suggestion that couldn't be checked is treated the same way.

The check combines rules and the AI: numbers and, in English, names are compared directly; a title moved to another of your employers is caught from your jobs; the AI lists other claims, and a second, narrower question clears honest rewordings. The AI can add a flag but never remove one the rules found. A result told without a number ("improved reliability") isn't flagged: a small model can't tell it from honest rewording.

`vendor/bin/pest tests/Live` runs tricky cases against the real local model (Ollama must be running): every invented fact must be flagged, and honest rewordings must pass.

## Import

- **From a CV:** upload a PDF or Word file. A local AI model reads the text and suggests what goes where. The file isn't kept, and its text is deleted once it has been read.
- **From LinkedIn:** upload the data export LinkedIn lets you download (a ZIP of CSV files). It's read directly, without AI.
- Either way, you check every field before anything is saved. Personal details you already filled in are only replaced if you tick them, entries the profile already has are marked, and anything that doesn't fit the profile, such as a job without a start date, is listed as left out.

## CV builder

- Several CVs from the same profile, each with its own name, template and settings. A change in the profile shows up in all of them.
- Four templates: Classic, Modern and Compact, all single-column with plain text in reading order so applicant tracking systems read them as written, and a German tabular Lebenslauf with personal details, photo and a signature line with place and date.
- Headings and dates follow the CV's language, so one profile gives an English CV ("Mar 2021 – Present") and a German one ("03/2021 – heute").
- A live A4 preview that shows where pages end, with a choice of font and colour, one page or two, and sections that can be hidden and reordered. Empty sections are left out by themselves.
- Saved versions: each one keeps the PDF (made by Gotenberg from the same page as the preview) and a Word file exactly as they were, so it's always clear what was sent. CVs can be duplicated to start a variant.
- A parser check on every version: the PDF is read back as plain text, the way an applicant tracking system reads it, and anything from the CV that doesn't come through as whole words is listed.
- Compare any two versions line by line, as a parser sees them.
- Word files use Arial or Georgia, which every computer has, since Word can't carry the CV's web fonts along.

## Job analysis

Paste a job post, or its web address, under **Jobs**. In the background, the AI reads the role, company, location, work mode (on site, hybrid, remote), seniority and the required and nice-to-have skills, in English or German.

- **Only what the post says:** a skill, title, company or place the AI names but the post doesn't is dropped. The post's language is worked out from its text.
- **Linked posts:** many job boards describe a post as structured data (schema.org `JobPosting`), which is used first; otherwise the page's text. Pages built in the browser can't be read, so paste those. Only public web pages are opened: no private or internal addresses, and every redirect is checked.
- **Your match, calculated:** each skill is looked up in your skills and languages, then in the rest of your profile, with common other names treated as the same (Postgres and PostgreSQL, Deutsch and German, REST-APIs and REST API). Required skills count three times as much as nice-to-have ones. The page shows which skills you have, where they were found, and which are missing, and the score updates as your profile changes.

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
| PDF | Gotenberg (headless Chromium), PhpWord for Word files |
| Accounts | Laravel Fortify, Laravel Socialite |
| AI | Laravel AI SDK: Ollama (free, local) by default, or Claude or an OpenAI-compatible API |
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
| (inside Docker only) | Gotenberg, which makes the PDFs |
| localhost:5433 | PostgreSQL, for a database tool (user and password `careercraft`) |

The first `docker compose up` installs the frontend packages, which takes a minute.

### AI

By default the AI runs on [Ollama](https://ollama.com) on your machine, outside Docker, for free. Install it, then download the model once:

```bash
ollama pull qwen2.5:7b
```

A CV takes about 10 to 30 seconds on a laptop with a graphics card. `OLLAMA_URL` and `OLLAMA_MODEL` in `.env` change where Ollama runs and which model it uses. LinkedIn import and everything else work without it.

To change the AI, make your account an admin and open **AI settings** in the sidebar (`/admin/ai`):

```bash
docker compose exec app php artisan careercraft:admin you@example.com
```

There you choose Ollama, Claude or any OpenAI-compatible API, the model, a timeout, a daily token allowance per person and token prices, and send a test that streams the answer. Keys for paid providers go in `.env` (`ANTHROPIC_API_KEY`, `OPENAI_COMPATIBLE_API_KEY`) and are never stored in the database. Every AI request is recorded with its tokens and cost, by person and by model.

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
