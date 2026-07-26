# 06 — UI / UX Standards

Owns: design tokens, page patterns, shared components.  
Stack packages → [07](07-technical-standards.md). Module list / nav contents → [04](04-modules.md). Export auth → [05](05-security.md).

Implement UX through **Vue 3 + Inertia + shadcn-vue + TanStack Table** shared components — not vanilla JS or one-off HTML tables.

## Design tokens

CSS variables only (e.g. `resources/css/app.css`). No per-module color/font one-offs.

### Typography

| Token | Value | Usage |
|-------|--------|--------|
| `--font-family-base` | `"Calibri Light", Calibri, "Segoe UI", sans-serif` | All UI |
| `--font-size-base` | `0.8125rem` (13px) | Body, cells, inputs, buttons |
| `--font-size-sm` | `0.75rem` (12px) | Labels, table headers, helpers |
| `--font-size-xs` | `0.6875rem` (11px) | Meta / quiet text |
| `--font-size-page-title` | `1rem` (16px) | Page titles |
| `--font-weight-regular` | `300` | Default |

No bold UI (`600`/`700`/`bold`). Emphasize with color/size/spacing. Small fonts throughout.

### Colors

| Token | Hex | Role |
|-------|-----|------|
| `--color-bg-app` | `#F4F6F8` | App background |
| `--color-bg-surface` | `#FFFFFF` | Panels / tables |
| `--color-bg-subtle` | `#EEF1F4` | Sidebars / filter bars |
| `--color-bg-table-odd` | `#FFFFFF` | Odd rows |
| `--color-bg-table-even` | `#F0F3F6` | Even rows |
| `--color-bg-table-hover` | `#D9E6F2` | Row hover |
| `--color-bg-table-header` | `#E8EDF2` | Header row |
| `--color-border` | `#D0D7DE` | Borders |
| `--color-border-strong` | `#B6C0CA` | Strong borders |
| `--color-text` | `#2C333A` | Primary text |
| `--color-text-muted` | `#5C6770` | Secondary |
| `--color-text-label` | `#4A5560` | Labels / headers |
| `--color-primary` | `#2F5D8C` | Primary actions |
| `--color-primary-hover` | `#254A70` | Primary hover |
| `--color-primary-subtle` | `#E4EEF7` | Soft highlight |
| `--color-success` | `#2F6F4E` | Success |
| `--color-warning` | `#8A6A1F` | Warning |
| `--color-danger` | `#9B3B3B` | Danger |
| `--color-info` | `#2F6B8A` | Info |
| `--color-nav-bg` | `#1F2A33` | Nav chrome |
| `--color-nav-text` | `#D7DEE5` | Nav links |
| `--color-nav-text-active` | `#FFFFFF` | Active nav |
| `--color-nav-hover` | `#2A3844` | Nav hover |
| `--color-nav-group` | `#8A97A3` | Nav group titles |

Avoid purple-glow, cream/terracotta, or newspaper aesthetics. Status badge colors from Entity Status when set.

```css
body {
  font-family: var(--font-family-base);
  font-size: var(--font-size-base);
  font-weight: var(--font-weight-regular);
  color: var(--color-text);
  background: var(--color-bg-app);
}
.form-label,
.table thead th {
  font-size: var(--font-size-sm);
  font-weight: var(--font-weight-regular);
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--color-text-label);
}
```

## Shell & navigation

- Authenticated: left sidebar + optional top user menu, page header; global toast popup (vue-sonner) from `AppLayout`, top-right — not an inline banner in the nav/content chrome  
- Guest: login / forgot password only  
- Nav groups and items: [04](04-modules.md) (Overview / CRM / Operations / Administration / Future)  
- Group headings: uppercase, `--font-size-xs`, `--color-nav-group`, not bold  
- Hide unauthorized items; still enforce server-side  

## Listing page (mandatory)

Every listing (including nested Account/Entity child lists):

| Control | Required |
|---------|----------|
| Search | Yes |
| Filters | Yes |
| Sorting | Yes |
| Pagination | Yes (show even for one page) |
| Export / Download | Yes (gated by `{module}.export`) |
| Create / bulk / row actions | As permitted |

```
[ PAGE TITLE ] [ EXPORT ] [ CREATE ]
[ SEARCH ] [ FILTERS ] [ CLEAR ]
[ BULK ACTIONS ]
| table — zebra + hover |
[ SHOWING X–Y OF Z ] [ pager ]
```

- Query-string state for search/filters/sort  
- Export honors current filters; formats Excel/CSV (+ PDF when warranted); audited ([05](05-security.md))  
- One shared Collectimate listing wrapper around TanStack Table (`CollectimateDataTable`)  

## Tables

- Alternating rows + hover (`--color-bg-table-*`)  
- Header: uppercase small labels, not bold  
- Empty state; horizontal scroll on small screens  

## Detail & forms

**Entity tabs:** Profile · Campaigns · Statuses · Action Codes · Templates · Knowledge Groups (+ later Comments · History · Files · Statistics)  
**Knowledge Group Show** (drill-down from Knowledge Groups View): Knowledge Center items for that group (text / URL / PDF)
**Account tabs:** Profile · Contact Info · Addresses · Secondary Contacts · Social Links  

Labels: **Entity** / **Campaign** / **Account** only — never Client, Customer, or Contracts.

Forms: uppercase small labels · required indicators · field errors · Save · Save & Close · Cancel.  
Submit via Inertia. Prefer full-page forms; modals for simple confirms. Confirm destructive actions.

Row actions order: View → Edit → Delete (+ module actions after).  
Row actions are **icon-only** (`ListingRowActions`: Eye / Pencil / Trash) with `title` + `aria-label`.

## Other screens

- **Assignment:** Assigned vs Available lists with search (paginate if long); same chrome  
- **Dashboard:** Activity today tables (Campaign × Status) + Account Portfolio Summary (Entity-only filter, drill-down in new tab) + Agents (Online/Offline + time ago); CRM-only  
- **Feedback:** session flash (`flash.success` / `flash.error`) must surface as **popup toasts on all authenticated pages** via `AppLayout` + vue-sonner; never as inline text beside the sidebar. No bold alert titles — color + icon  

## Shared components (required)

`AppLayout` · PageHeader · Toaster · ListingToolbar · FilterPanel · CollectimateDataTable · ListingRowActions · Pagination · StatusTag · FormLabel · FormActions · TabView · ConfirmDialog  

Theme shadcn-vue (CSS variable aliases) to the Collectimate tokens above.

## Responsive & a11y

Collapse sidebar on small screens; on desktop, show/hide via the header control (persisted in `localStorage`). Labels tied to inputs. Icon-only controls need `aria-label`. Status needs text, not color alone.

## Don’ts

- Vanilla JS / jQuery product UI  
- React / Angular / Livewire-primary UI  
- Detached SPA/API as main app  
- Hand-rolled grids when CollectimateDataTable / TanStack Table fits  
- Bold chrome · missing listing controls · tables without zebra/hover  
- Future-nav Knowledge Center product / comms inbox / AI chat screens in Phase 1 (Entity Knowledge Groups tab + Group Show item CRUD are allowed)  
