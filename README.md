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
- A live preview that is the PDF itself: Gotenberg makes it after every change and pdf.js draws it page by page, so its pages and page breaks are exactly the download's. Choose the font and colour, one page or two, and which sections to show in which order. Empty sections are left out by themselves.
- Saved versions: each one keeps the PDF (made the same way as the preview) and a Word file exactly as they were, so it's always clear what was sent. CVs can be duplicated to start a variant.
- A parser check on every version: the PDF is read back as plain text, the way an applicant tracking system reads it, and anything from the CV that doesn't come through as whole words is listed.
- Compare any two versions line by line, as a parser sees them.
- Word files use Arial or Georgia, which every computer has, since Word can't carry the CV's web fonts along.

## Job analysis

Paste a job post, or its web address, under **Jobs**. In the background, the AI reads the role, company, location, work mode (on site, hybrid, remote), seniority and the required and nice-to-have skills, in English or German.

- **Only what the post says:** a skill, title, company or place the AI names but the post doesn't is dropped. The post's language is worked out from its text.
- **Linked posts:** many job boards describe a post as structured data (schema.org `JobPosting`), which is used first; otherwise the page's text. Pages built in the browser can't be read, so paste those. Only public web pages are opened: no private or internal addresses, and every redirect is checked.
- **Your match, calculated:** each skill is looked up in your skills and languages, then in the rest of your profile, with common other names treated as the same (Postgres and PostgreSQL, Deutsch and German, REST-APIs and REST API). Required skills count three times as much as nice-to-have ones. The page shows which skills you have, where they were found, and which are missing, and the score updates as your profile changes.

### Tailored CV

From a job post, pick one of your CVs and choose **Tailor this CV**. CareerCraft makes a copy for that job, and the AI rewords its summary and the bullet points of your three latest jobs, in the CV's language, to bring forward what fits. Each suggestion appears on the CV page as a diff to accept or reject.

- **Nothing new added:** the AI only gets the job's skills you already have, and every suggestion goes through the [fact guard](#fact-guard). A flagged suggestion is accepted only once you confirm it.
- **Your profile stays as it is:** accepted changes show on the tailored CV only, and only while your profile still has the text they reword.
- **What the job asks for first:** in a tailored CV, your skills and bullet points that name one of the job's skills come first.
- **Before and after:** the match score for what this CV shows, before and after its changes. As nothing new may be added, it rises only when a change brings up a skill the CV didn't show yet.

### Cover letters

From a job post, choose **Write the letter**: in English or German (Anschreiben), formal, warm or confident, short or standard, and optionally with one of your CVs. The AI writes the subject and body from the job and your profile. You edit the address block, the person to greet, the subject and the text, with a live preview.

- **Fact guard on every paragraph:** the AI only gets the job's title, company and the skills you already have, never the post's full text, so it has no missing skill to claim. Anything your profile doesn't state is flagged, and the PDF can be made only once each flag is confirmed or taken out of the letter.
- **DIN 5008 layout:** the address field where a window envelope expects it, place and date, subject, greeting and closing in the letter's language, and "Anlage: Lebenslauf" when a CV goes with it. The letter takes its font and colour from that CV.
- **One PDF to send:** download the letter alone, or the letter followed by its CV as one file.

### LinkedIn

At the end of the career profile, **Suggest a headline and About** writes both from your profile, in English or German, within LinkedIn's limits. The fact guard checks the headline and every paragraph of the About; a flagged one can be copied only once you confirm it. You paste them into LinkedIn yourself: CareerCraft never signs in to LinkedIn.

## Find jobs

**Find jobs** searches free public job sources and keeps what it finds as one shared list:

| Source | What it gives | Key |
|---|---|---|
| [Bundesagentur für Arbeit](https://www.arbeitsagentur.de/jobsuche/) | Germany's largest job board, searched by title and place; each job's description is read from its details | none |
| [Arbeitnow](https://www.arbeitnow.com) | the newest jobs, mostly in Germany, as a feed without search | none |
| [Adzuna](https://developer.adzuna.com) | a search across German job boards, with short descriptions | a free key in `ADZUNA_APP_ID` and `ADZUNA_APP_KEY`; left out without one |

- **In the background:** a search shows the jobs already found straight away, and fetches new ones; the list updates as they arrive. Each source is asked the same search at most once an hour.
- **One form:** titles without the gender note "(m/w/d)", descriptions as plain text without web addresses, remote or hybrid where the source says so, and salaries only when the employer gives them (not Adzuna's estimates).
- **Duplicates merged:** the same job on two sources (the same title, company without its legal form, and place) is one job with both links.
- **Analyse:** any job opens as a job post, ready for the match score, a tailored CV and a cover letter. Jobs no source has shown for 30 days drop out of the list.
- **Best match first:** jobs are ranked by your career profile, calculated rather than guessed. Each of your skills in a job's title counts 3 points, and in its description 1 (other names count too, so "Postgres" matches "PostgreSQL"). Each word of your headline and job titles in the job's title counts 2. Each card lists the skills of yours the job asks for. Without skills or job titles in the profile, or with "Newest first", the newest jobs come first. A job's points are kept per profile, and worked out again only when the job or the profile changes.
- **Not interested:** hides a job for you, with Undo. Words from the titles you hide rank similar jobs lower, unless your own profile uses them: hiding a "Sales Manager" lowers other sales jobs, but not your "Developer" ones.
- **Filters:** work mode (remote, hybrid or on site) and "only with a salary".
- **Companies you follow:** paste a link to a company's job board on [Greenhouse](https://developers.greenhouse.io/job-board.html), [Lever](https://github.com/lever/postings-api) or [Ashby](https://developers.ashbyhq.com/docs/public-job-posting-api) (the board, or one of its jobs). Its open jobs join the list straight away, with salaries where Lever or Ashby boards show them. No key is needed.
- **Company pages:** a company's name on a job, or in Companies you follow, shows its jobs alone, with how many are open, where and how it hires, and whether you follow its board.
- **Saved searches:** save the search on the page, including a company page, with an email every day or every week. Up to 20 per person; open one to run it again, or change or delete it.
- **Daily refresh:** the `scheduler` service in `compose.yaml` runs `php artisan careercraft:refresh-jobs` every morning at 06:00. It fetches each followed board and each saved search again, and deletes jobs no source has shown for 30 days. Run the command yourself to refresh at any time.
- **Job alerts:** at 07:00 the scheduler runs `php artisan careercraft:send-job-alerts`. Each saved search that is due emails the jobs found since it was last checked, up to 10 of them best match first, in your language. Nothing new, no email; only confirmed email addresses get alerts. In development they land in Mailpit.

## Apply queue

Speed without bots that apply unattended: each job is prepared for you, and nothing goes to an employer without your review.

- **Queue:** "Queue" on a job in Find jobs. The post is analysed, then a copy of the CV you chose is tailored to it and a letter is written in the post's language, in the tone you chose. All of it happens in the background; with the local AI it takes a few minutes per job.
- **Limits:** 10 jobs a day per person, none once the day's AI allowance is used, and each job once (a skipped job can be queued again).
- **Review:** one page per job with the match, the tailored CV's suggestions to accept or reject, and the letter with anything marked to confirm. Suggestions left undecided keep your own words.
- **Answers to common questions:** each job also gets answers to "Why do you want to work at this company?" and "Why are you a good fit for this role?", in the post's language, from your profile only and checked by the fact guard; anything it can't find in your profile is shown before you paste the answer. Your salary expectation, notice period, earliest start and work permit, entered once under Application details in the career profile (never on a CV), are shown beside them. Everything has a Copy button, and the answers can be written again.
- **Approve:** saves the tailored CV as a version and adds the job to your applications in Applied, with copies of the CV and letter and "Prepared with the apply queue" on its timeline. Then you apply on the job's page.
- **Log:** every job approved or skipped stays listed, with when it was queued and decided.

## Browser extension

The `extension/` folder holds a Chrome and Edge extension that fills application forms on Greenhouse, Lever, Personio and Workday from CareerCraft. You check the form and press submit yourself; it never submits.

- **Install:** open `chrome://extensions` (or `edge://extensions`), turn on developer mode, choose "Load unpacked" and pick the `extension` folder.
- **Connect:** under Settings → Browser extension, make a token (shown once; it can only read what the extension needs and log a submitted application, and can be revoked) and paste it into the extension with the CareerCraft address (`http://localhost:8000`).
- **Fill:** on a job's application form, press "Fill this form". For a job in your applications, it fills your name, email, phone, place, LinkedIn, GitHub and website, your application details, and the queue's answers to common questions that the fact guard checked and flagged nothing in, and attaches the CV and letter recorded for the job. Only empty fields are filled, each value once; dropdowns and questions about gender, ethnicity, disability or veteran status are left to you.
- **Personio and Workday:** Personio forms have no letter slot, so the letter goes under "Other". Workday asks you to sign in to the employer's site first and spreads the form over several steps; press "Fill this form" on each step.
- **Log it:** after you submit, open the extension again in that tab (the site's confirmation page is fine) and press "I submitted it". The application goes on its timeline and, if it was in Saved, moves to Applied with today's date.
- **Permissions:** the extension works on a tab only when you press its button, and talks only to CareerCraft on `localhost`.
- **API:** `/api/extension/profile`, `/api/extension/application?url=…` and `/api/extension/applications/{id}/documents/{cv|letter}` to read, and `POST /api/extension/applications/{id}/submitted` to log a submission, with the token as a bearer token. 60 requests a minute.

## Sending by email

Applications go out from your own address, never from CareerCraft's.

- **Gmail:** "Send through Gmail" in the settings asks Google for permission to send email only (not to read it). The tokens are stored encrypted, the access token is refreshed when it runs out, and a test email is sent on connecting. If Google takes the permission back, the settings ask to connect again. See [Send and read with Gmail](#send-and-read-with-gmail-optional) for the Google setup.
- **Outlook:** "Send through Outlook" works the same way for personal and work Microsoft accounts, through Microsoft Graph with permission to send email only. A state value checked on the way back keeps a sign-in someone else started from being slipped in. See [Send and read with Outlook](#send-and-read-with-outlook-optional) for the Microsoft setup.
- **Your mail account:** under Sending email in the settings, enter your provider's mail server, port and encryption, and your user name and password (some providers, such as Gmail, need an app password). The password is stored encrypted and never shown again. Saving sends a test email to your address; only an account whose test went through can send. Outside development the server must be a public address on a mail port (465, 587, 25 or 2525). In development, Mailpit works as the server (`mailpit`, port 1025, no encryption).
- **Send by email:** on an application with its CV and letter recorded, check the recipient (an address found in the job post is filled in), the subject and a short note in the letter's language, then press Send. The recorded CV and letter go attached as PDFs.
- **Log and limits:** every email sent is listed on the application with its text and attachments, and noted on its timeline; an application in Saved moves to Applied. Up to 20 application emails a day.
- **Job board submission:** Greenhouse, Lever and Ashby only take applications through their APIs with a key from the employer, so CareerCraft sends by email instead.

## Replies from employers

Connect your mailbox, and employers' replies show up on the applications they're about.

- **Gmail:** "Read replies in Gmail" in the settings asks Google for permission to read email only (`gmail.readonly`: CareerCraft can't send, change or delete anything). The inbox is read once before it's saved, the tokens are stored encrypted, and the access token is refreshed when it runs out. If Google takes the permission back, checking pauses and the settings ask to connect again. See [Send and read with Gmail](#send-and-read-with-gmail-optional) for the Google setup.
- **Outlook:** "Read replies in Outlook" works the same way for personal and work Microsoft accounts, through Microsoft Graph with permission to read email only (`Mail.Read`). A state value checked on the way back keeps a sign-in someone else started from being slipped in, and Microsoft's newest refresh token is kept. See [Send and read with Outlook](#send-and-read-with-outlook-optional) for the Microsoft setup.
- **Connect by IMAP:** under Replies from employers in the settings, enter your provider's IMAP server, port and encryption, and your user name and password. Gmail, Outlook and most others need an app password (for Gmail: `imap.gmail.com`, port 993, SSL/TLS). CareerCraft logs in before saving, and the password is stored encrypted and never shown again. Outside development the server must be a public address on port 993 or 143.
- **What is read:** every 15 minutes the scheduler runs `php artisan careercraft:check-mailboxes`, which looks at the sender, subject and reply headers of new emails in the inbox; the first check goes back 30 days. Only an email about one of your applications has its text read, and only those are kept. Emails stay unread in your mailbox, and nothing is changed or deleted there. "Check now" checks at once, up to four times an hour. If an IMAP server numbers its emails afresh (a new UIDVALIDITY), the next check reads the last 30 days again; emails already kept aren't kept twice.
- **What counts as a reply:** a reply to an application email CareerCraft sent (by its thread), an email from the company's domain or with the company's name as its sender, or one from an applicant tracking system such as Greenhouse, Lever, Personio or Workday that names the company. Job alerts from LinkedIn, Indeed, StepStone and similar, and CareerCraft's own emails, are left alone. When the company has several of your applications, the job named in the subject wins.
- **On the application:** each reply is listed under Replies with its text, and noted on the timeline.
- **What happens next:** the AI reads each reply as a rejection, interview invite, assessment, request for information, offer, or something else, such as a note that your application arrived. The label shows on the reply. A rejection moves the application to Rejected, an invite or assessment to Interviewing, and an offer to Offer. Applications only move forward, so a late invite doesn't undo an offer, and nothing moves out of Rejected. An invite with a fixed time adds the interview, with its length and place, but only when the hour is written in the email, so a time the AI made up is left out. For everything except "something else", you get an email saying what came in and what changed, with the interview attached as a calendar file. If the AI is unavailable, the reply stays unlabelled and nothing changes.
- **Correcting a label:** under each reply, "This email is" shows its label, and you can change it there, or label one the AI couldn't read. What the old label did is taken back: the application returns to its column if nothing else has moved it since, and an interview the AI added is deleted. Then the new label is acted on in the same way, without the email to you. An interview time isn't read from the email again, so add it under Interviews. A later email that says the same, such as the same template for another job (the company's name and the job title aside), gets your label instead of the AI's, and the timeline says so. An email that is only similar is still read by the AI: with the local model, labels given to the AI as examples didn't change its answers reliably.
- **When a check fails:** the reason shows in the settings. If the server refused the login, checking pauses until you save the settings again or press Check now, so a changed password isn't tried against your server every quarter hour. Anything else, such as a server that didn't answer, is tried again with the next check.
- **Try it locally:** Docker Compose runs GreenMail, a demo mailbox (Mailpit can't be read over IMAP). Connect server `greenmail`, port 3143, no encryption, user `demo`, password `demo`; emails sent through `greenmail:3025` to `demo@careercraft.test` land in it.

## Applications

A board of every job you are going for: Saved, Applied, Interviewing, Offer and Rejected.

- **Add jobs:** "Track" on a job in Find jobs, "Track this job" on an analysed job post, or add one found elsewhere with its link and where you found it.
- **Move cards:** drag a card to another column or place, or change its status in the card, which works by keyboard too. The first move to Applied dates the application today, unless it already has a date.
- **What you sent:** on an application's page, record the CV version and cover letter you sent. A copy of each PDF stays with the application, so it shows exactly what went out even after the CV is deleted or the letter changed. Only a letter with nothing left to confirm can be recorded.
- **Contacts:** the people behind an application, such as the recruiter or hiring manager, with email, phone and LinkedIn links.
- **Notes:** dated notes on an application, newest first.
- **Timeline:** what happened to an application, in order: when it was added and where it was found, every status change, the documents recorded, and the notes and contacts added. It is worded when shown, in your language.
- **Tasks:** things to do by a day, such as a thank-you email. Tick one off when done; due and overdue tasks show on the dashboard.
- **Interviews:** a date, time, length and place (an address, a phone number or a video link). "Add to calendar" downloads an `.ics` file for Google Calendar, Outlook or Apple Calendar. The time is kept as entered, so the calendar shows the interview at that hour.
- **Follow-ups:** an application in Applied with no news for 7 days (you choose, under Reminders in the settings) and no open task is suggested for a follow-up on the dashboard.
- **Morning email:** at 07:30 the scheduler runs `php artisan careercraft:send-reminders`. It emails tasks due, interviews today and tomorrow, and applications to follow up. A follow-up is mentioned once per quiet spell, not every morning. Nothing to say, no email; it can be turned off in the settings, and only confirmed email addresses get it.

## Interview prep

"Practise for the interview" on an application's page lists the questions you are likely to be asked, to answer out loud.

- **About this job:** for an application tracked from an analysed job post, "Write questions" has the AI write six questions from the post: its duties, the skills it asks for and its level, each with what it tests. The AI gets only the post, nothing from your profile. With the local model it takes about 5 to 15 seconds, in the background; the questions are kept with the application and can be written again.
- **Common questions:** behavioural questions for everyone, then questions for the kind of role the job title names (software, data, design, product or sales), for its level (from the analysed post, or words such as "Senior" or "Werkstudent" in the title), and about the company.
- **In the interview's language:** the questions are in the job post's language, so a German post gets German questions whatever language the page is in. Without an analysed post, they follow the page.

## Accounts

- Sign up with email confirmation, sign in, and reset a forgotten password. The reset form answers the same whether or not an account exists, so it can't be used to find out who has one.
- Two-factor login with an authenticator app, and one-use recovery codes.
- Optional sign-in with Google or GitHub. A Google or GitHub account only links to an existing CareerCraft account when the provider has confirmed the email address, and two-factor login still applies. Someone who signs in only this way can set a password in the settings.
- A list of signed-in devices, and signing out all the others.
- Emails go out in the language the person uses the site in.

## Stack

| Part | Choice |
|---|---|
| Backend | PHP 8.5, Laravel 13 |
| Interface | Livewire 4, Alpine.js, Tailwind CSS 4, Vite 8 |
| Data | PostgreSQL 18, Redis 8 |
| Email in development | Mailpit, and GreenMail as a demo mailbox |
| Reading mailboxes | ImapEngine (IMAP) |
| PDF | Gotenberg (headless Chromium), PhpWord for Word files |
| Accounts | Laravel Fortify, Laravel Socialite |
| AI | Laravel AI SDK: Ollama (free, local) by default, or Claude or an OpenAI-compatible API |
| Quality | Pest 5, Larastan 3 at the strictest level, Pint |

Everything runs in Docker, so PHP doesn't need to be installed on your machine.

## Run it

You need Docker. On Windows, clone the project inside WSL2 (for example into your Ubuntu home folder) and run the commands there, as Laravel recommends: Docker reads files on a Windows drive so slowly that every page takes seconds, and edits don't reload the page.

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
| (inside Docker only) | GreenMail, a demo mailbox for trying replies (IMAP on port 3143) |
| localhost:5433 | PostgreSQL, for a database tool (user and password `careercraft`) |

The first `docker compose up` installs the frontend packages, which takes a minute.

### AI

By default the AI runs on [Ollama](https://ollama.com) on your machine, outside Docker, for free. Install it, then download the model once:

```bash
ollama pull qwen3.5:9b
```

A CV takes about 10 to 30 seconds on a laptop with a graphics card. `OLLAMA_URL` and `OLLAMA_MODEL` in `.env` change where Ollama runs and which model it uses. LinkedIn import and everything else work without it.

Models tested with CareerCraft's prompts and its fact guard suite (`vendor/bin/pest tests/Live`). Pick by your graphics card's memory (on Windows: Task Manager → Performance → GPU; with NVIDIA: `nvidia-smi`; on a Mac with Apple silicon, its memory):

| Memory | Model | Download | Notes |
|---|---|---|---|
| 8 GB or more | `qwen3.5:9b` (default) | 6.6 GB | The most complete job reading and the best German. |
| 6 GB | `qwen2.5:7b` | 4.7 GB | The fastest. Weaker German, and misses skills a post names as alternatives. |
| 10 GB or more | `gemma4:12b` | 8 GB | Passes the fact guard suite, but writes mixed German and English when tailoring a CV. |

Without a graphics card, Ollama runs on the processor, much more slowly. A model's first request after a while takes extra seconds while it loads.

To change the AI, make your account an admin and open **AI settings** in the sidebar (`/admin/ai`):

```bash
docker compose exec app php artisan careercraft:admin you@example.com
```

There you choose Ollama, Claude or any OpenAI-compatible API, the model, a timeout, a daily token allowance per person and token prices, and send a test that streams the answer. Keys for paid providers go in `.env` (`ANTHROPIC_API_KEY`, `OPENAI_COMPATIBLE_API_KEY`) and are never stored in the database. Every AI request is recorded with its tokens and cost, by person and by model.

### Sign in with Google or GitHub (optional)

The buttons appear once you add your own app keys to `.env`: `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET`, or `GITHUB_CLIENT_ID` and `GITHUB_CLIENT_SECRET`. Set the callback URL in the provider's settings to `http://localhost:8000/auth/google/callback` or `http://localhost:8000/auth/github/callback`.

### Send and read with Gmail (optional)

The same Google keys let candidates send applications through their Gmail and read employers' replies in it. In the Google Cloud project behind those keys:

1. Enable the Gmail API.
2. Add `http://localhost:8000/settings/mail/gmail/callback` (sending) and `http://localhost:8000/settings/mailbox/gmail/callback` (replies) as authorised redirect URIs.
3. Add the `https://www.googleapis.com/auth/gmail.send` and `https://www.googleapis.com/auth/gmail.readonly` scopes to the OAuth consent screen. `gmail.send` is a sensitive scope and `gmail.readonly` a restricted one: while the app is in testing, only the test users you list can connect, and Google reviews the app before anyone else can.

### Send and read with Outlook (optional)

Register a free app in the [Microsoft Entra admin center](https://entra.microsoft.com) (App registrations → New registration):

1. Supported account types: accounts in any organizational directory and personal Microsoft accounts.
2. Redirect URIs: platform Web, `http://localhost:8000/settings/mail/outlook/callback` (sending) and `http://localhost:8000/settings/mailbox/outlook/callback` (replies).
3. API permissions: Microsoft Graph, delegated, `Mail.Send`, `Mail.Read`, `User.Read` and `offline_access`.
4. Certificates & secrets: a new client secret.

Then set `MICROSOFT_CLIENT_ID` (the application ID) and `MICROSOFT_CLIENT_SECRET` (the secret's value) in `.env`. `MICROSOFT_TENANT` defaults to `common`, for personal and work accounts alike.

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
