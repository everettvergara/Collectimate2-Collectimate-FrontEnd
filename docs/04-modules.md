# 04 — Modules

Owns: module purpose, features, screens, nav groups.  
Fields → [03](03-domain-model.md). AuthZ → [05](05-security.md). UI patterns → [06](06-ui-standards.md).

Every listable screen uses the standard listing (search, filters, sort, pagination, export). Export is a per-module role permission.

**Vocabulary:** UI and nav say Entity / Campaign / Account only — never Client, Customer, or Contracts.

---

## Authentication

Login · Forgot password · Change password · User profile. Laravel Auth. Audit login/logout.

## Dashboard

Widgets: Activity today (per Entity: Campaign × Status activity counts for today) · Account Portfolio Summary (Entity filter; status counts per accessible Campaign; drill-down to Account Master in new tab; excludes template entity) · Agents (Online = login audit today, else Offline; last activity as time ago). Campaign-scoped. No communication stats.

## Users

CRUD identities; link `0..1` Agent Profile; enable/disable. Admins may omit profile; operators should have one.

## Roles & Permissions

Seed roles: Super Admin, Admin, Supervisor, Agent, Viewer.  
Matrix: view/create/update/delete/export (+ archive/purge where needed). Roles UI exposes Export beside CRUD. Policies/Gates. Audit permission changes.

## Agent Profiles

Operational staff CRUD; link to User; assignment of campaigns (shared with Campaign Assignment UX).

## Entities

Top-level master (has many Campaigns). View sections: Profile · Campaigns table (add/view/edit/delete) · Entity Statuses · Entity Action Codes · Templates; later tabs Comments · History · Files · Statistics. Hard delete with type-name confirm cascades Campaigns/Accounts. Listing + `entities.export`.

## Campaigns

Belongs to one Entity. Create / edit / archive / delete. Assignment of agents from campaign screen (agents table on Campaign Show). Accounts via Account Master nav (not listed on Campaign Show).

## Campaign Assignment

Primary security boundary. Assign/remove Agent Profiles ↔ Campaigns (both sides). Audit changes. Never assign to Users.

## Account Master

Account under Campaign (`Entity → Campaign → Account`). CRM nav listing + Campaign → Accounts.

Detail tabs: Profile · Contact Info · Addresses · Secondary Contacts · Social Links.  
Soft delete + `accounts.purge`. Batch import via Import module. Permissions: `accounts.view/create/update/delete/export/purge`.

## Entity Statuses, Action Codes & Templates

Per-entity catalogs live under Entity Show (Entity Statuses / Entity Action Codes / Templates). Account badges use Entity Status. Templates store SMS/email/chat body content with `{variable}` placeholders; they are not a messaging send product. CRM nav also includes read-only Activity Types and Contact Types, plus Address Types.

## Reports

Entity / Campaign / Account / User summaries. Export Excel/CSV/PDF. Scoped + audited.

## Import

CSV/Excel: Entities, Campaigns, Accounts, Contact Info, Addresses, Secondary Contacts, Social Links. Validate, error report, entity/campaign context, audit, templates.

## Export / Download

On **every** listing toolbar. Per-module `{module}.export`. Hide without permission; server enforce; audit; campaign scope; honor listing filters.

## Audit Logs

Searchable/filterable log of security and data-movement events ([05](05-security.md)). Privileged roles only.

## Settings

General · Company · Lookups · System config. Permission-gated.

---

## Admin nav groups

| Group | Items |
|-------|--------|
| Overview | Dashboard |
| CRM | Entities (Campaigns / Entity Statuses / Entity Action Codes / Templates on Entity Show), Account Master |
| Operations | Reports, Import |
| Administration | Users, Roles & Permissions, Agent Profiles, Audit Logs, Settings, Demo Mode (template/demo data tool) |
| Future | Disabled placeholders (Knowledge Center, SMS, Calling, Email, Messaging, AI, Analytics) |

Campaign Assignment UX remains on Campaign and Agent Profile screens (optional admin shortcut allowed).

## Dependencies

```mermaid
flowchart LR
  Auth --> Users
  Users --> Roles
  Users --> AgentProfiles
  Entities --> Campaigns
  AgentProfiles --> CampaignAssignment
  Campaigns --> CampaignAssignment
  Campaigns --> AccountMaster
  AccountMaster --> ImportExport
  Entities --> ImportExport
  Campaigns --> ImportExport
  AccountMaster --> Reports
  Entities --> Reports
  Campaigns --> Reports
```

Build order: [08](08-implementation-roadmap.md).
