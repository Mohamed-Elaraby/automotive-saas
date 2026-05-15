# Platform Access Control UI

## Package 14: User Access Profile + Effective Permissions

The user-centered access profile is available at:

```text
/workspace/admin/access/users/{user}
```

Route names are registered under:

```text
automotive.admin.access.users.show
automotive.admin.access.users.roles.edit
automotive.admin.access.users.roles.update
```

### User Access Profile

The profile combines:

- user identity and status
- Workspace Owner and current-login badges
- product access summary
- branch access summary
- role summary
- effective permission summary
- seat impact
- access warnings
- activity placeholder for Package 18 audit logs

Tabs:

- Overview
- Products
- Branches
- Roles
- Effective Permissions
- Access Warnings
- Activity

### Role Assignment Per Product

Package 14 intentionally uses one active role per user per product. The data model can support multiple assignments, but the UI keeps the assignment model predictable while effective permissions are being introduced.

Rules:

- a user cannot be assigned a product role without product access
- a user cannot be assigned a role from another product
- updates are idempotent
- owner role changes are protected from self-lockout

### Effective Permissions

`EffectiveUserAccessService` calculates and explains access through:

- product subscription state
- product access records
- owner implicit access
- branch access records
- product roles
- product permissions

Explanation sources include:

- `role`
- `owner_implicit`
- `blocked_no_product_access`
- `blocked_no_branch_access`
- `blocked_missing_role`
- `blocked_inactive_subscription`
- `blocked_missing_permission`

This package displays effective access only. Package 15 and Package 16 will enforce menu/button visibility and backend route/controller permissions.

### Access Warnings

The profile reports warnings for states such as:

- product access without branch access
- product access without role
- role assigned while product access is revoked
- branch assigned while the product branch is disabled
- no product access
- inactive subscription
- owner missing explicit owner_sync records

Owner missing explicit records is informational because the Workspace Owner still has implicit access.

### Theme Reuse

The old theme pages reviewed for Package 14 were:

- `resources/views/users.blade.php`
- `resources/views/profile.blade.php`
- `resources/views/roles-permissions.blade.php`
- `resources/views/permission.blade.php`
- `resources/views/ui-nav-tabs.blade.php`
- `resources/views/ui-cards.blade.php`
- existing scoped views under `resources/views/automotive/admin/access`

Reused design details:

- page header actions
- user profile card structure
- solid primary tabs
- metric cards
- badges
- table and action button styling
- permission accordion/table matrix styling
- empty and placeholder states

## Package 13: Roles & Permission Matrix UI

The product-scoped role management UI is available at:

```text
/workspace/admin/access/roles
```

Route names are registered under:

```text
automotive.admin.access.roles.*
```

### Roles UI

The roles index lists product-scoped roles with:

- role name and description
- product key
- active user assignment count
- permission count
- system/template/custom badges
- active/inactive status
- created date
- actions for Permission Matrix, edit, duplicate, and delete

The page supports search, product filtering, status filtering, an empty state, and theme-style export dropdown placeholders.

Deletion is protected:

- Tenant Owner cannot be deleted.
- System roles cannot be deleted.
- Roles assigned to active users cannot be deleted.

### Permission Matrix

The matrix groups permissions by module and keeps permission keys explicit:

```text
automotive_service.work_orders.view
automotive_service.work_orders.create
automotive_service.estimates.approve
automotive_service.reports.export
automotive_service.access.roles.manage
automotive_service.access.branches.manage
```

The matrix supports:

- permission search
- select all permissions
- clear all permissions
- select/clear per module
- Read only, Manager, and Full access presets
- selected permission count
- warning badges for dangerous permissions
- save, reset, and back actions

Dangerous permissions include delete, role management, billing management, branch management, void, and reconcile actions.

### Role Templates

The tenant catalog seeder creates editable templates idempotently:

- Tenant Owner
- Automotive Owner
- Automotive Branch Manager
- Automotive Service Advisor
- Automotive Technician
- Automotive Accountant
- Automotive Inventory Keeper
- Automotive Viewer

Seeder:

```bash
php artisan db:seed --class=TenantProductPermissionCatalogSeeder
```

Run it inside tenant context when seeding a tenant database directly. It does not duplicate roles or permissions when run more than once.

### Architecture

Heavy role and permission logic lives outside Blade and controllers:

- `ProductRoleController`
- `ProductRoleManagementService`
- `ProductPermissionCatalogService`
- existing `ProductPermissionService`

The UI uses the scoped tenant admin layout:

```text
automotive.admin.layouts.adminLayout.mainlayout
```

The old theme pages reviewed for the implementation were:

- `resources/views/roles-permissions.blade.php`
- `resources/views/permission.blade.php`
- `resources/views/users.blade.php`
- `resources/views/data-tables.blade.php`
- `resources/views/ui-modals.blade.php`
- existing scoped access views under `resources/views/automotive/admin/access`

Reused design details:

- header button layout
- export dropdown pattern
- input-group search style
- filter dropdown/select rhythm
- `table table-nowrap datatable` table style
- badges for status/type/counts
- action dropdowns
- permission accordion/table matrix pattern
- card summary metrics

Never run:

```bash
php artisan route:cache
```

## Package 15 Menu/Button Visibility

Package 15 adds UI visibility enforcement only. It improves navigation and button UX, but backend route/controller authorization remains Package 16.

Core implementation:

- `AccessVisibilityService` centralizes menu, module, and action visibility decisions.
- Blade conditionals are available as `@productCan`, `@productCannot`, `@branchCan`, `@ownerAccess`, and `@notOwnerAccess`.
- Sidebar sections and quick-create actions are filtered per request from the existing workspace product manifest.
- Dashboard actions are filtered through the same visibility service.
- Access Control UI actions now hide or show disabled/read-only states for role, user product, user branch, role assignment, permission matrix, and product branch management actions.
- Header branch switching continues to use `BranchContextService`, so only allowed branches are listed.

Visibility rules are product-scoped and use explicit permission keys such as:

```text
automotive_service.access.roles.manage
automotive_service.access.branches.manage
automotive_service.work_orders.view
```

Owner behavior:

- Workspace Owner retains implicit full visibility.
- Owner Sync remains visible only to the owner.
- Owner access still does not consume product seats by default.

Request cache:

- Visibility checks use request-local in-memory caches inside `AccessVisibilityService`.
- No persistent cache is used because role and permission invalidation is not finalized yet.

Package boundary:

- Package 15 hides UI elements for UX.
- Package 16 must enforce backend routes/controllers for security.

## Package 16 Backend Permission Enforcement

Package 16 adds backend authorization for the Access Control management surface. UI visibility is no longer the only protection for these routes.

Protected areas:

- Access Control dashboard and diagnostics
- access users index/profile
- product access grant/revoke
- branch assignment
- product branch enable/disable
- user role assignment
- role CRUD and duplication
- role Permission Matrix edit/update

Middleware:

```php
->middleware('tenant.product.permission:automotive_service,automotive_service.access.roles.manage')
->middleware('tenant.product.permission:automotive_service,automotive_service.access.users.manage')
->middleware('tenant.product.permission:automotive_service,automotive_service.access.branches.manage')
```

The middleware checks the authenticated `automotive_admin` user, active or trialing product subscription, product access, product-scoped permission, Workspace Owner implicit access, and branch access when a current branch is required.

Multiple acceptable permission keys can be separated with `|`:

```php
tenant.product.permission:automotive_service,automotive_service.access.manage|automotive_service.access.roles.manage
```

Branch-required actions can pass a third argument:

```php
tenant.product.permission:automotive_service,automotive_service.work_orders.view,current_branch
```

Safe branch-context selector routes remain available to authenticated tenant users that need branch selection:

- `automotive.admin.access.branch-context.select`
- `automotive.admin.access.branch-context.store`
- `automotive.admin.access.branch-context.switch`
- `automotive.admin.access.branch-context.clear`

Controller-level guards were also added around direct mutation methods so forbidden POST/PUT/DELETE requests do not change data if a route is called manually.

Package boundary:

- Package 15 controls menu/button visibility for UX.
- Package 16 enforces Access Control backend routes and controller mutations.
- Package 17 will add branch-scoped data filtering for operational records.

## Package 17 Branch-Scoped Data Filtering

Package 17 adds branch-level data visibility for Automotive workspace records. Users should only see records in branches enabled for the product and assigned to them.

Core service:

- `BranchScopeService`

Responsibilities:

- resolve allowed branch ids for a user and product
- resolve the current branch from branch context
- assert direct branch access for detail/mutation actions
- apply allowed-branch filters to Eloquent queries
- apply current-branch filters for branch-context-specific dashboards/lists

Reusable model scope trait:

- `HasBranchScope`
- `visibleToUser(...)`
- `visibleToUserOrGlobal(...)`
- `forAllowedBranches(...)`
- `forCurrentBranch(...)`

Covered flows:

- Maintenance check-ins, estimates, documents, approvals, deliveries, warranties, complaints, notifications
- Maintenance workflow records: inspections, diagnosis, technician jobs, QC, workshop board snapshots
- Workshop work orders and part consumption
- Inventory adjustments, inventory reports, stock movement reports, stock transfers
- Tenant attachments and tenant notifications with `product_key` and `branch_id`
- Maintenance reporting/export counts

Owner behavior:

- Workspace Owner keeps implicit branch visibility for all enabled active branches under subscribed products.
- Owner does not require explicit branch assignment records to pass branch-scoped queries.

Branch context behavior:

- List/report pages that are branch-context-specific use the current branch when selected.
- Broader operational lists use all allowed branches when no current branch is required.
- Branch selector and switch routes remain unchanged.

Central entity policy:

- Customers, suppliers, and employees are central entities.
- Package 17 does not delete or duplicate central entities.
- Transaction-level visibility is enforced through branch-scoped records such as work orders and check-ins.
- Broader global-versus-transaction entity policy can be expanded after audit diagnostics in Package 18.

Package boundary:

- Package 16 blocks forbidden backend actions.
- Package 17 scopes visible data inside allowed backend areas.
- Package 18 will add access audit logs and diagnostics.

## Package 18 - Access Audit Logs + Diagnostics

Package 18 adds tenant-side traceability for Access Control changes and diagnostics for product, branch, route, permission, and owner access decisions.

Audit foundation:

- tenant table: `access_audit_logs`
- model: `AccessAuditLog`
- service: `AccessAuditService`

Audited Access Control events:

- `product_access.granted`
- `product_access.revoked`
- `branch_access.granted`
- `branch_access.revoked`
- `role.assigned`
- `role.removed`
- `role.created`
- `role.updated`
- `role.deleted`
- `role.duplicated`
- `role_permissions.updated`
- `owner_access.synced`
- `forbidden_action.blocked`
- `permission.denied` as a reserved event key for future explicit permission denial records

Audit writes are safe best-effort writes. A failed audit insert is logged internally and must not break the primary access-control action. Audit metadata must not include passwords, tokens, secrets, or other sensitive values.

Diagnostics foundation:

- service: `AccessDiagnosticsService`
- UI routes:
  - `automotive.admin.access.diagnostics.index`
  - `automotive.admin.access.diagnostics.user`
  - `automotive.admin.access.diagnostics.permission`
  - `automotive.admin.access.diagnostics.route`

Diagnostics explain:

- subscription status
- product access state
- branch access state
- current branch context where relevant
- assigned product roles
- requested permission existence and grant source
- owner implicit access
- final allow/deny result
- reason code
- suggested fix

Audit UI route:

- `automotive.admin.access.audit.index`

The Access Control dashboard links to Audit Logs and Diagnostics and shows recent audit activity. The UI uses the scoped Automotive admin layout:

```blade
@extends('automotive.admin.layouts.adminLayout.mainlayout')
```

Package boundaries:

- Package 15 hides or disables UI controls for UX only.
- Package 16 enforces backend route/controller permissions.
- Package 17 scopes branch-bearing data queries.
- Package 18 records access-control changes and explains access decisions.
- Package 19 will finish UI acceptance, cleanup, docs, and production validation.

Do not run:

```bash
php artisan route:cache
```

## Package 19 - Final UI Acceptance, Cleanup, Docs, Production Validation

Package 19 closes Phase 2 Access Control UI & Enforcement as a production-ready stage.

Phase 2 package summary:

- Package 10: Access Control UI Foundation
- Package 11: Product Access & Seat Management UI
- Package 12: Branch Access UI + Branch Context
- Package 12.1: Session Isolation + Plan Limit Sync + Owner Access Improvements
- Package 13: Roles & Permission Matrix UI
- Package 14: User Access Profile + Effective Permissions
- Package 15: Menu/Button Visibility Enforcement
- Package 16: Backend Route/Controller Permission Enforcement
- Package 17: Branch-Scoped Data Filtering
- Package 18: Access Audit Logs + Diagnostics
- Package 19: Final UI Acceptance, Cleanup, Docs, Production Validation

Final user access management flow:

1. Workspace Owner opens Access Control.
2. Owner reviews product subscriptions and seat usage.
3. Owner grants product access to tenant users.
4. Owner enables product branches within branch entitlement limits.
5. Owner grants branch access to users.
6. Owner assigns one active product role per user/product.
7. Owner reviews the User Access Profile and Effective Permissions.
8. Owner validates warnings, audit logs, and diagnostics.

Owner access behavior:

- Workspace Owner has implicit product, branch, role-management, diagnostics, and audit visibility for subscribed products.
- Owner does not consume product seats by default.
- Sync Owner Access creates explicit compatibility records but does not replace implicit owner access.
- Owner self-lockout and last-owner protections remain required.

Product access and seat behavior:

- Non-owner users require active product access for product-scoped permissions.
- Seat usage excludes owner access records when `metadata.consumes_seat = false`.
- Demo/acceptance seeders respect current seat limits and skip unsafe grants if limits are exhausted.

Branch access and branch context:

- Product branches must be enabled before user branch access is granted.
- Branch enabling respects current plan/add-on branch limits.
- Branch context controls current branch workflows, while allowed-branch lists control broader operational visibility.
- Branch selector/switch routes remain available to authenticated users with eligible branches.

Roles and Permission Matrix:

- Product roles and permissions are scoped by `product_key`.
- Permission keys use explicit product/module/action naming, such as `automotive_service.work_orders.view`.
- Permission Matrix updates are audited and protected by backend permission middleware.

Effective permissions:

- Effective permissions combine product access, branch access, product roles, owner implicit access, and subscription state.
- The User Access Profile explains granted and blocked permissions.

Menu/button visibility:

- Package 15 visibility helpers hide or disable menus/actions for UX.
- UI visibility is not the security boundary.

Backend enforcement:

- Package 16 `tenant.product.permission` middleware blocks forbidden direct URLs and forged POST/PUT/DELETE requests.
- Destructive actions retain controller/service protections.

Branch-scoped data filtering:

- Package 17 `BranchScopeService` and model scopes filter branch-bearing operational data.
- Central customers, suppliers, and employees remain central; transaction-level visibility is branch-scoped.

Audit logs and diagnostics:

- Package 18 `AccessAuditService` records access-control changes and forbidden Access Control middleware denials.
- `AccessDiagnosticsService` explains product, branch, route, permission, role, and owner decisions.

Demo seeder:

```bash
php artisan db:seed --class=TenantAccessControlDemoSeeder
```

Run it inside a tenant context. The seeder creates demo branches, users, product access, branch access, and role assignments idempotently. If a tenant's branch or seat limits are lower than the demo dataset, it keeps existing data safe and only applies records allowed by current entitlements.

Acceptance command:

```bash
php artisan platform:access-control-acceptance
```

This is a lightweight static command for route, class, view, docs, migration, seeder, and deploy-workflow checks. It does not replace feature tests or browser QA.

Production verification commands:

```bash
php artisan migrate --force
php artisan tenants:migrate --force
php artisan route:clear
php artisan config:clear
php artisan view:clear
php artisan cache:clear
php artisan route:list --name=automotive.admin.access --except-vendor
```

Manual UI testing checklist:

- See `docs/access-control-ui-acceptance-checklist.md`

Known backlog after Phase 2:

- central customer/supplier/employee advanced global permission policy
- broader product-module audit logging outside Access Control
- advanced user-level permission overrides if desired later
- import/export permission hardening
- fine-grained report permissions
- performance optimization or persistent permission cache if needed
- browser/E2E tests if tooling is added
- multi-product UI replication for spareparts/accounting/inventory

No route cache policy:

- Do not run `php artisan route:cache`.
- Use `php artisan route:clear` during deployment cleanup.

## Package 12.1 Hotfix Notes

### Admin Session Isolation

Central SaaS Admin and Tenant Workspace Admin use separate guards:

- `admin`
- `automotive_admin`

Logout is scoped to the guard being logged out. Guard logout no longer invalidates the entire browser session because that removes every guard key stored in the same Laravel session cookie.

The login flow may regenerate the session id, but it must preserve the other guard's authenticated state.

### Plan Limit Source Of Truth

Product branch limits are resolved from the current active tenant product subscription and its current plan:

1. current plan `plan_limits.branch_limit`
2. current plan `plans.max_branches`
3. subscription snapshot `tenant_product_subscriptions.branch_limit` as fallback only
4. active `extra_branch` add-ons

When SaaS Admin changes a legacy subscription plan, the tenant product subscription mirror is synced immediately and its denormalized snapshots are refreshed.

### Workspace Owner Access

The tenant is not a user. The Workspace Owner is the primary tenant user, currently represented by user id `1` inside the tenant database.

Workspace Owner behavior:

- has implicit workspace management access
- can manage Access Control, users, products, branches, billing, roles, and permissions through the management layer
- does not require explicit product access records to appear as authorized in management UI
- does not consume product seats by default
- can choose any active branch that is enabled for a subscribed product

### Sync Owner Access

`Sync Owner Access` creates explicit records for compatibility flows:

- active/trialing subscribed products in `tenant_user_product_access`
- enabled active product branches in `tenant_user_product_branches`

The action is idempotent and does not enable new product branches. Owner product access records are marked with `metadata.consumes_seat = false`.

### Emergency Recovery

Use this command when a tenant owner account must be restored:

```bash
php artisan tenant:grant-owner client-1 admin@example.com --sync-access
```

If the tenant database name contains a dash, wrap it with backticks in direct MySQL checks:

```sql
USE `tenant_client-1`;
```

Never run:

```bash
php artisan route:cache
```
