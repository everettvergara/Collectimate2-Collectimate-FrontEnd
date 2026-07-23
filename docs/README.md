# Collectimate Phase 1 — Docs

Working guides for the Phase 1 CRM foundation. **Authoritative detail lives in the numbered docs below.**

## Doc ownership (avoid duplicating across files)

| Doc | Owns |
|-----|------|
| [01-vision-and-scope.md](01-vision-and-scope.md) | Why Phase 1 exists; in / out of scope; success criteria |
| [02-architecture.md](02-architecture.md) | Auth vs operations; security hierarchy; campaign scoping principle |
| [03-domain-model.md](03-domain-model.md) | Entities, fields, relationships, schema conventions |
| [04-modules.md](04-modules.md) | Module features, screens, admin nav groups |
| [05-security.md](05-security.md) | Roles, permissions, audit, export authorization |
| [06-ui-standards.md](06-ui-standards.md) | Design tokens, listing/detail/form UX patterns |
| [07-technical-standards.md](07-technical-standards.md) | Locked stack, coding rules, forbidden tech |
| [08-implementation-roadmap.md](08-implementation-roadmap.md) | Build order (slices 0–13) |

Root [`AGENTS.md`](../AGENTS.md) is the short entry checklist for coding agents.

## Quick facts

| Item | Value |
|------|--------|
| Product | Mini CRM foundation (not SMS / Calling / AI) |
| App | Laravel 12 monolith |
| UI | Vue 3 + Inertia + Vite + PrimeVue + Tailwind |
| DB | MariaDB 11+ |
| Core graph | `Entity → Campaign → Account` (access: `User → Agent Profile → Campaign Assignment → Campaign`) |
| Vocabulary | Entity / Campaign / Account only — never Client, Customer, or Contracts |

Stack details: [07](07-technical-standards.md). UX details: [06](06-ui-standards.md). Build order: [08](08-implementation-roadmap.md).
