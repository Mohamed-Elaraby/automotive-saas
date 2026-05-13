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
