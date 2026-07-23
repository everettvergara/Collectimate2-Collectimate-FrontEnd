# 07 — Technical Standards

Owns: locked stack, coding conventions, forbidden tech, packaging.  
UX tokens/patterns → [06](06-ui-standards.md). Security behavior → [05](05-security.md). Schema → [03](03-domain-model.md).

## Locked stack

| Layer | Choice |
|-------|--------|
| Backend | Laravel 12+ (scaffold may be Laravel 13), PHP 8.3+ |
| Database | MariaDB 11+ |
| UI app | Vue 3 (Composition API) |
| Bridge | Inertia.js |
| Bundler | Vite |
| UI components | PrimeVue (+ PrimeIcons) |
| Utility CSS | Tailwind CSS + Collectimate tokens ([06](06-ui-standards.md)) |
| ORM | Eloquent |
| AuthZ | Laravel Auth, Policies, Gates |
| Files | Laravel Storage |
| Import/Export | PhpSpreadsheet (Maatwebsite Excel when PHP/Laravel compatible) |
| PDF | barryvdh/laravel-dompdf |
| VCS | Git / GitHub |

Scaffold: Laravel Breeze (Vue + Inertia) → add PrimeVue → apply Collectimate theme/components.  
Root Blade = Inertia shell only. This repo is the full monolith (not React/Next, not a detached SPA).

## Forbidden (Phase 1)

- React / Next.js, Angular  
- Livewire as primary UI  
- Vanilla JS / jQuery as primary UI  
- Homegrown tables/forms when PrimeVue covers the need  
- Detached SPA + custom JSON API as the main app shape  
- MongoDB, PostgreSQL, SQL Server, Redis, Elasticsearch  
- Docker, Kubernetes, RabbitMQ, Kafka, microservices  

## Coding standards

| Area | Standard |
|------|----------|
| Controllers | REST-ish resources returning `Inertia::render` |
| Validation | Form Requests |
| Authorization | Policies + Gates |
| UI | Vue pages + PrimeVue via shared Collectimate wrappers |
| Services | When multi-model workflows need them |
| Repositories | Only when justified |

**PHP:** thin controllers; domain naming (`Entity`, `Campaign`, `Account`, `AgentProfile`); no Client/Customer synonyms; no unnecessary abstraction.  
**Vue:** `resources/js/Pages`, `resources/js/Components`; Inertia forms/router; no second UI framework; no vanilla core UX.  
**Data graph:** `Entity → Campaign → Account` ([03](03-domain-model.md)).  
**Theme:** implement [06](06-ui-standards.md) tokens; every list uses shared listing + DataTable (search/filters/sort/pager/export).

## Database

Migrations + FKs + soft deletes + `created_by`/`updated_by` + timestamps + indexes.  
Unique `(campaign_id, agent_profile_id)`. Seed roles, statuses, lookups. Reversible `down()` when safe.

## Import / export

| Rule | Detail |
|------|--------|
| Import sheets | Entities, Campaigns, Accounts, Contact Info, Addresses, Secondary Contacts, Social Links |
| Listing export | Required on every listing |
| Permission | `{module}.export` — policy + hide in UI |
| Scope | Assigned campaigns only (unless Super Admin); honor filters |
| Audit | Every import and export |

## Security engineering

Authorize every action; shared campaign scope; never trust UI hiding alone; hash passwords with Laravel defaults; audit per [05](05-security.md); validate uploads.

## Testing (priority)

Auth · permission deny/allow · campaign scope + IDOR · Super Admin bypass · import/export auth + audit · Entity/Account isolation. PHPUnit/Laravel tools only.

## Packages allowed without extra approval

Laravel · inertia-laravel · Vue 3 · Vite Vue plugin · PrimeVue/PrimeIcons · Tailwind (+ Breeze peers) · Maatwebsite Excel · dompdf · optional Heroicons  

Other deps need explicit approval and must not violate the forbidden list.

## Definition of done

In scope · validated · authorized · campaign-scoped when needed · shared UI patterns · audited when required · migrations/seeders if schema changed · basic authZ/happy-path tests for risk points.
