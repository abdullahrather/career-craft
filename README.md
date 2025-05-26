<div align="center">

# CareerCraft 🚀  
*Your all-in-one platform for crafting careers, now powered by the **TALL Stack***  

[![Laravel](https://img.shields.io/badge/Laravel-11.x-red)](https://laravel.com) 
[![Livewire](https://img.shields.io/badge/Livewire-3.x-purple)](https://livewire.laravel.com) 
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](#-license)

</div>

---

## ✨ Key Features

| Module / Page | What it does |
| --------------| ------------ |
| **Landing & Signup** | Social auth, AppSumo coupon flow (Laravel Jetstream) |
| **Generator Dashboard** | AI-powered résumé, LinkedIn & cover-letter builders (Livewire components) |
| **Interview Prep** | Real-time Q&A bot (streamed with Livewire + Laravel Reverb) |
| **Email Monitoring** | IMAP listener + queue workers *(coming soon)* |
| **Smart / Auto Apply** | Background job submissions & tracking *(coming soon)* |
| **User Dashboard** | Application stats, profile completeness, saved docs |
| **Admin Panel** | Live metrics, role management, ticketing & feedback |
| **Settings** | Account, billing, notification preferences |

---

## 🏗️ Tech Stack

| Layer | Choice | Why |
| ----- | ------ | --- |
| **Backend + SSR** | **Laravel 11** | Robust routing, queues, policies, event broadcasting |
| **Realtime Components** | **Livewire 3** | Server-driven UI updates without SPA overhead |
| **Frontend Interactions** | **Alpine.js** | Lightweight JS for toggles, modals, etc. |
| **Styling** | **Tailwind CSS v3** | Utility-first, works seamlessly with Blade & Livewire |
| **Database** | PostgreSQL 15 | Strong JSON/CTE support for analytics queries |
| **Auth** | Laravel Jetstream (Fortify + Sanctum) |
| **AI Integration** | OpenAI (gpt-4o) via Laravel HTTP client |
| **Dev-Ops** | Docker Compose → Fly.io / Laravel Forge |
| **CI/CD** | GitHub Actions + Pest tests |

---

## 📊 Admin Analytics

* Live counters: **Total Sign-ups**, **AppSumo cohort**, **Active Sessions** (Laravel Reverb presence channel)  
* Livewire charts (e.g. Filament Charts) to compare **sign-ups vs active sessions**  
* **Industry** & **Role** breakdown (Healthcare, Tech, Software Eng., etc.)  
* **Support Tickets** with assignment & status (Polymorphic `ticketables` table)  
* **Role & permission** control (Laravel Permissions package)

---

## 🚀 Getting Started (local)

```bash
# 1. Clone
git clone https://github.com/abdullahrather/career-craft.git
cd career-craft

# 2. Install PHP deps
composer install

# 3. Install JS deps & compile assets
npm install
npm run dev          # uses Vite + Tailwind JIT

# 4. Environment
cp .env.example .env
php artisan key:generate
# add DB_URL, OPENAI_API_KEY, APP_URL, etc.

# 5. Spin up containers (optional)
docker compose up -d

# 6. Migrate & seed
php artisan migrate --seed

# 7. Serve
php artisan serve           # http://127.0.0.1:8000
```                                  <-- ✨ CLOSES the code fence
---

## 🗂️ Project Structure

```text
app/
├── Livewire/          # Interactive components
├── Models/
resources/
├── views/
│   ├── components/    # Blade components
│   └── livewire/      # Livewire blade views
└── js/
    └── alpine/        # Alpine stores/plugins
database/
├── migrations/
└── seeders/
routes/
├── web.php
└── livewire.php

---

## 🛣️ Roadmap

- [ ] **MVP** – résumé generator + basic dashboards  
- [ ] **Admin analytics graphs** with Filament Charts  
- [ ] **Interview bot** streaming via Reverb  
- [ ] **Auto-apply** background jobs & Chrome extension  
- [ ] **Multi-tenant mode** (companies & agencies)

---

## 🤝 Contributing

1. Fork & create a branch: `git checkout -b feature/amazing-idea`  
2. Run `pest` & `phpstan` before opening a PR  
3. Fill out the PR template (why, screenshots, test coverage)

---

> Built with ❤️ on the **TALL Stack** — because tall ambitions deserve a tall tech stack!

<details>
<summary>What changed vs. the React prototype?</summary>

| Section | Old | New |
|---------|-----|-----|
| **Tech Stack** | React, Node, Prisma | Laravel 11, Livewire 3, Alpine.js |
| **Dev commands** | `pnpm dev` | `php artisan serve`, `npm run dev` |
| **Project structure** | `apps/web`, `apps/api` monorepo | Laravel folder tree |
| **Realtime layer** | WebSockets via Node | Laravel Reverb presence channels |
| **Charts** | D3/Recharts | Livewire + Filament Charts |

</details>
