# AGENTS.md — Collectimate Phase 1

Short entry rules for agents. **Details live in `docs/01`–`docs/08` — do not restate full specs here.**

## What this is

Laravel 12 CRM monolith (Vue 3 + Inertia + Vite + shadcn-vue + TanStack Table + Tailwind, MariaDB). Full app lives in this repo despite the “FrontEnd” name.

Not in Phase 1 CRM foundation: Future-nav Knowledge Center product, Calling, Email/Messaging products, AI/RAG, analytics platforms. (Entity Knowledge Groups + Knowledge Center repository on Entity Show is in scope.)

**Active product slice (post–Phase 1 CRM):** SMS Queuing — SMS Configuration, SMS Dashboard, Laravel-owned outbound queue (from Account SMS Send activities), C++ SMS service HTTP bridge + callback. Still out: Calling, Email inbox/campaigns, Messaging apps, AI/RAG.

## Read order

1. [`docs/README.md`](docs/README.md) — index + ownership
2. [`docs/01-vision-and-scope.md`](docs/01-vision-and-scope.md) — scope
3. [`docs/08-implementation-roadmap.md`](docs/08-implementation-roadmap.md) — next slice
4. Task-specific: `03` domain · `04` modules · `05` security · `06` UI · `07` stack

Do not use any removed `summary-mvp1` brief; docs `01`–`08` are the source of truth.

## Rules

1. Keep it simple; Laravel conventions first.
2. Users authenticate; **Agent Profiles** operate; never own business records on User.
3. **Campaign Assignment** is the security boundary; scope every business query (Super Admin bypasses).
4. Business hierarchy is **`Entity → Campaign → Account`** (Account Master under Campaign), with multi contact/address/secondary/social, soft delete, purge, batch import.
5. Vocabulary is locked: **Entity / Campaign / Account only**. Never Client, Customer, or Contracts (for Account).
6. One UI system via shared Vue/shadcn-vue + TanStack Table components — [06](docs/06-ui-standards.md).
7. Locked stack only — [07](docs/07-technical-standards.md). No React/Next, Livewire-primary UI, vanilla-JS admin, or detached SPA/API.
8. Every listing: search, filters, sort, pagination, export (per-module `{module}.export` on roles).
9. Stay in roadmap order; no Calling/Email/AI scope creep. SMS Queuing is an approved product slice — see [04](docs/04-modules.md) / [08](docs/08-implementation-roadmap.md).

## Hierarchy

```
Access:  User → Agent Profile → Campaign Assignment → Campaign
Data:    Entity → Campaign → Account → child rows
```

## Repo state

Laravel app scaffolded (Vue/Inertia/shadcn-vue + TanStack Table). MariaDB via `.env`. Run `php artisan serve` + `npm run dev` (or `npm run build`). Super admin seeded: username `admin` / password `password`.
