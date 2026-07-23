# 05 — Security and Permissions

Owns: roles, permission keys, campaign-scope enforcement, audit, export authorization.  
Hierarchy rationale → [02](02-architecture.md). Domain → [03](03-domain-model.md).

## Model

1. **Role permissions** — may the user perform this module action?  
2. **Campaign Assignment** — which business rows are visible/mutable?

```
User → Role/Permissions → Agent Profile → Campaign Assignment → Campaign Access
```

Super Admin bypasses **campaign filtering** only (still authenticated; still audited).

## Principles

- Auth ≠ operations; Users never own business records  
- Assign campaigns to Agent Profiles only  
- Auto-filter all business queries (except Super Admin)  
- Every listing export is a distinct `{module}.export` permission and is audited  
- Audit security-relevant actions  

## Roles

| Role | Intent |
|------|--------|
| Super Administrator | Full access; bypass campaign filters |
| Administrator | System / user / campaign admin |
| Supervisor | Oversee campaign work |
| Agent | Day-to-day CRM on assigned campaigns |
| Viewer | Read-oriented (export optional per matrix) |

## Permission keys

CRUD + **export** per listable module. Account also has **purge**.

```
users.view|create|update|delete|export
agent_profiles.view|create|update|delete|export
campaigns.view|create|update|archive|export
campaign_assignments.manage
entities.view|create|update|delete|export
accounts.view|create|update|delete|export|purge
comments.view|create|update|delete|export
files.view|create|delete|export
statuses.view|manage|export
reports.view|export
imports.run
audit_logs.view|export
settings.manage
```

Account children authorize through `accounts.*` (no separate “contracts” permissions).  
Roles UI: Export toggle beside View/Create/Update/Delete.  
Enforce in Policies/Gates; hide in Vue UI (UI hide is not enough).

## Agent Profile rules

| User type | Profile |
|-----------|---------|
| Super Admin / some Admins | Optional |
| Operational roles | Required |

No profile → no campaign-scoped business access (clear config error, not silent empty data).

## Campaign scoping

Hierarchy for data: `Entity → Campaign → Account`. Security filter is still **assigned campaign IDs**.

| Record | Rule |
|--------|------|
| Campaign | `id` in assigned campaign IDs |
| Account (+ children) | `campaign_id` in assigned set |
| Entity | Super Admin, or Entity has ≥1 Campaign in assigned set |
| Entity Comments / History / Files | Same as parent Entity |
| Reports / Exports / dashboard | Same rules |

- One shared scope mechanism  
- Deny direct ID access outside assignment  
- Soft delete = `accounts.delete` (or equiv.); purge = `accounts.purge`  
- Import/export only within allowed campaigns  
- Never describe Entity as Client/Customer in permission copy or audit labels

## Export authorization

1. `{module}.export` + module view  
2. Campaign scope (unless Super Admin)  
3. Same filters as listing (unless explicit authorized “all in scope”)  
4. Generate file → audit → download  

## Audit

**Events:** Login/Logout · Create/Update/Delete · Purge · Import/Export · Campaign Assignment · Permission changes  

**Fields:** actor user, agent profile (nullable), action, subject type/id, campaign id, metadata, occurred_at  

**Access:** privileged roles; searchable/filterable listing (+ export if permitted)

## Feature checklist

1. Authenticated?  
2. Role allows action?  
3. In assigned campaign (or Super Admin)?  
4. Audited if required?  
5. UI hidden **and** server-enforced?

## Failure modes

| Risk | Prevention |
|------|------------|
| IDOR | Policy + scope on show/update/delete |
| Export unscoped | Same scope helper as listing |
| Assign campaign to User | Domain forbids; Agent Profile FKs only |
| Button-only security | Policy on every route |

## Policy map (suggested)

| Area | Focus |
|------|--------|
| User / Role | Admin management |
| AgentProfile / Campaign / Assignment | As permissions + audit |
| Entity | Module CRUD/export + Entity visibility via assigned campaigns |
| Campaign / Account (+ children) | Module CRUD/export/purge + campaign scope |
| Reports / listing exports | view + `{module}.export` + scope |
| AuditLog / Settings | Privileged only |
