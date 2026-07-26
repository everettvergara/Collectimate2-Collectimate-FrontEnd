# 01 — Vision and Scope

Owns: why Phase 1 exists, what is in/out, success criteria.  
Stack → [07](07-technical-standards.md). Architecture → [02](02-architecture.md). Modules → [04](04-modules.md).

## Objective

Build a **CRM foundation** every future Collectimate module can inherit (SMS, Calling, Voice AI, Email, Messaging, RAG, Analytics) **without architectural rewrites**.

Phase 1 is a secure, maintainable mini CRM. Calling, Email/Messaging products, AI, and communications-analytics platforms remain out of scope. **SMS Queuing** is an approved post–Phase 1 product slice (config, dashboard, Laravel-owned queue, C++ service bridge) that plugs into Account activities.

## Product promise

When Phase 1 ships, the org can:

- Authenticate users; manage users, roles, permissions
- Represent operators as Agent Profiles; organize work as `Entity → Campaign → Account`
- Assign campaigns as the security boundary
- Manage Entities, Campaigns, and **Account Master** (with rich account profile data)
- Use one vocabulary: Entity / Campaign / Account — never Client, Customer, or Contracts
- Import/export with auditability; run simple reports
- Use one consistent admin UI

## In scope

**Access:** Login, forgot/change password, profile · Users · Roles/Permissions · Agent Profiles · Campaigns · Campaign Assignment · campaign-scoped queries · Audit Logs · Settings

**CRM:** Dashboard (CRM counts only) · Entities · Account Master (profile; multi contact info/addresses/secondary contacts/social URLs; soft delete + purge; batch import) · Entity comments/history/files/statistics · Entity Statuses (per Entity) · Entity Knowledge Groups + Knowledge Center repository (text / URL / PDF; no AI) · Activity / Contact / Address Types · Reports · Import · Export/Download on every listing (per-module role permission)

**Cross-cutting:** Soft deletes, timestamps, `created_by`/`updated_by` · one shared listing/detail/form UX · Policies/Gates · Laravel Storage

## Out of scope

Placeholder nav only — no operational builds (except SMS Queuing below):

- Future-nav Knowledge Center product (standalone KC app / RAG product UX) — distinct from Entity Knowledge Groups + Group Show repository
- Calling, Email campaigns/inbox, Messaging apps
- AI / RAG / embeddings / auto-reply runtime against knowledge items
- Communication analytics / BI on the CRM Dashboard (SMS has its own dashboard)
- Docker / Kubernetes / microservices as the app shape
- SIP / Calling queue services

**SMS Queuing (approved product slice):** SMS Configuration · SMS Dashboard · Laravel-owned outbound SMS queue fed from Account SMS Send activities (single + bulk, “Queue in SMS”) · HTTP bridge to local C++ SMS service + callback endpoint · batch cancel. Not a full SMS marketing/inbox product.

## Success criteria

1. Auth (login, recovery/change, profile)
2. Users, roles/permissions, Agent Profiles
3. Campaign Assignment + campaign filtering on all business data
4. CRUD for every in-scope CRM module
5. Account Master complete (profile + children + purge)
6. Imports (Entities, Accounts, Account children) and audited exports
7. Audit logs and reports
8. Consistent responsive UI
9. Future modules can plug in without changing ownership/security model

## Principles

Keep it simple. Laravel conventions. Avoid overengineering. Reuse shared UI. Auth and operations stay separate (`User → Agent Profile → Campaign Assignment → Campaign`). Business data: `Entity → Campaign → Account`.
