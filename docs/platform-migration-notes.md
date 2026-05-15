# Platform Migration Notes

## Purpose
These notes document the safe migration path from the existing Automotive-heavy SaaS codebase to the product-scoped multi-product platform foundation.

The current policy is compatibility first: do not delete legacy tables, routes, or services until replacement paths are proven and protected by tests.

## Legacy Areas Kept Intentionally

### legacy Automotive users and permissions
Legacy columns such as `maintenance_role`, `maintenance_permissions`, `accounting_role`, and `accounting_permissions` remain in place.

Migration path:
- Use `tenant_user_product_access` for product access.
- Use `tenant_user_product_roles`, `product_roles`, and `product_permissions` for future enforcement.
- Keep old columns as fallback until each UI path is migrated.

### legacy branches
The central `branches` table remains the source of branch records.

Migration path:
- Use `tenant_product_branches` for product activation.
- Use `tenant_user_product_branches` for user branch access.
- Do not create product-specific branch duplicates.

### legacy Automotive customers and suppliers
Existing customer and supplier flows remain active.

Migration path:
- Create/reuse central `customers` and `suppliers`.
- Store product-specific fields in product profile tables.
- Keep old Automotive read paths working while creation paths move through central services.

### legacy PDF and document logic
Automotive document routes remain stable.

Migration path:
- Continue routing legacy document actions through existing controllers.
- Use the central mPDF engine and `numbering_sequences` behind the scenes.
- Keep product document templates under `resources/views/documents/products/*`.

### legacy attachment logic
`maintenance_attachments` remains for current Automotive screens.

Migration path:
- New product-aware metadata is stored in `tenant_attachments`.
- `MaintenanceAttachmentService` bridges new uploads into the central attachment service and then writes the legacy record.
- Existing routes and views are not removed.

### legacy notification logic
`maintenance_notifications`, `admin_notifications`, and portal notifications remain separate compatibility surfaces.

Migration path:
- Product tenant notifications use `tenant_notifications`.
- Product notification templates use `notification_templates`.
- `MaintenanceNotificationService` bridges Automotive events into the central notification foundation while preserving legacy records.
- Admin Notifications remain central-admin scoped and are not merged into tenant notifications.

## Admin Notifications Test Stabilization
The previous Admin Notifications failures were caused by two legacy test-environment issues:

1. `APP_URL` generated routes on `system.seven-scapital.com`, while `CanonicalizeWorkspaceHost` correctly redirects that host to the canonical root `seven-scapital.com` with status `308`.
2. Authenticated Admin Notification tests used the `web` guard, while the central admin routes use `auth:admin`.

Production canonical redirects were not disabled or weakened.

The test fix is limited to the Admin Notification test concern and test files:
- force the test root URL to `https://seven-scapital.com`
- authenticate protected requests through the `admin` guard

## Deployment Migration Note
Because this project uses `stancl/tenancy` with tenant migrations under `database/migrations/tenant`, deployment must run:

```bash
php artisan migrate --force
php artisan tenants:migrate --force
```

Full `php artisan test` is not a mandatory deploy step. Run targeted foundation tests before or after deployment until the broader legacy suite is made fully deterministic.

Never run:

```bash
php artisan route:cache
```

## Package 13 Access Migration Notes

Product-scoped roles and permissions are now managed through the Package 13 UI and services:

- `product_roles`
- `product_permissions`
- `product_role_permission`
- `tenant_user_product_roles`
- `ProductPermissionService`
- `ProductRoleManagementService`
- `ProductPermissionCatalogService`

Permission keys must remain explicit and product-scoped:

```text
{product_key}.{module}.{action}
```

Examples:

```text
automotive_service.work_orders.view
automotive_service.invoices.approve
automotive_service.access.roles.manage
```

Do not migrate legacy product-specific role columns by deleting them yet. Keep existing legacy user fields as compatibility data until Package 14 introduces User Access Profile + Effective Permissions.

The Package 13 catalog seeder is idempotent and safe to rerun inside tenant context:

```bash
php artisan db:seed --class=TenantProductPermissionCatalogSeeder
```

Package 14 should consume these role and permission assignments to show per-user effective access across owner access, product access, branch access, role permissions, and branch context.

## Package 14 Access Migration Notes

The User Access Profile is now the read model for a tenant user's access state:

- `EffectiveUserAccessService` calculates product access, branch access, roles, effective permissions, permission explanations, and warnings.
- `UserRoleAssignmentService` updates tenant user product-role assignments transactionally.
- `UserAccessProfileController` owns profile display and role assignment routes.

Role assignment policy for this stage:

- one active role per user per product
- no role assignment without product access
- no cross-product role assignment
- Workspace Owner must retain access-management capability

This is a UI/read-model package. It does not replace route/controller enforcement yet.

Next enforcement packages:

- Package 15: Menu/Button Visibility Enforcement
- Package 16: Backend Route/Controller Permission Enforcement
- Package 17: Branch-Scoped Data Filtering

## Package 15 Access Migration Notes

Menu and button visibility now uses `AccessVisibilityService` instead of ad hoc Blade checks.

Migration rules:

- use product-scoped permission keys in UI checks
- prefer `@productCan('automotive_service.module.action', 'automotive_service')`
- use `@branchCan` only for branch-scoped action checks with a known/current branch
- do not treat hidden UI as security enforcement
- keep backend protection work for Package 16

The service uses request-level caches only. Do not add route cache or persistent permission visibility cache until invalidation is designed.

Workspace Owner remains an implicit full-access user for visibility. Owner Sync and owner-only actions must stay guarded by owner visibility, not by ordinary role assignment.

## Package 16 Access Migration Notes

Access Control routes now use backend permission enforcement through the `tenant.product.permission` middleware.

Migration rules:

- apply product-scoped permission keys to new protected tenant admin routes
- use `automotive_service.access.users.manage` for product-access user management
- use `automotive_service.access.branches.manage` for branch assignment and product branch activation
- use `automotive_service.access.roles.manage` for role CRUD, user role assignment, and Permission Matrix updates
- keep branch-context selector/switch routes usable for authenticated users that need branch selection
- keep owner-only actions, such as Sync Owner Access, protected by owner checks in addition to ordinary middleware

Example:

```php
Route::post('/roles', [ProductRoleController::class, 'store'])
    ->middleware('tenant.product.permission:automotive_service,automotive_service.access.roles.manage');
```

For branch-required checks, pass the current-branch mode:

```php
tenant.product.permission:automotive_service,automotive_service.work_orders.view,current_branch
```

Package 16 does not replace Package 15 UI visibility. It adds server-side protection against direct URLs and forged POST/PUT/DELETE requests.

Package 17 must continue this migration by applying branch-scoped data filtering to operational records and queries.

## Package 17 Access Migration Notes

Branch-scoped data filtering now uses `BranchScopeService`.

Migration rules:

- use `BranchScopeService` for branch assertions and query filtering
- use `visibleToUser(...)` on branch-bearing models that include `HasBranchScope`
- use `visibleToUserOrGlobal(...)` for records where `branch_id = null` means global product-level visibility, such as product-wide notifications
- use current branch context for branch-specific dashboards/lists
- use all allowed branches for operational lists that are intended to show a user's full accessible workload
- do not branch-filter central entities directly unless the business policy is explicit

Covered branch-scoped records include:

- work orders
- vehicle check-ins
- estimates
- inspections
- diagnosis records
- QC records
- delivery/warranty/complaint records
- maintenance documents
- maintenance and tenant attachments
- maintenance and tenant notifications
- stock movements, inventory rows, and stock transfers

Central entity policy:

- customers, suppliers, and employees remain central
- transaction-level visibility is enforced through branch-scoped records
- Package 18 should add audit/diagnostic visibility for denied branch access and suspicious direct-record attempts

Do not use route cache during this migration.

## Package 12.1 Access Hotfix Notes

### Session isolation
Central SaaS Admin and Tenant Workspace Admin share the browser session cookie, but they must not invalidate the whole session when only one guard logs out.

Migration rule:
- use guard-scoped logout for `admin`, `automotive_admin`, and portal `web`
- do not call `$request->session()->invalidate()` from scoped logout flows
- regenerating the CSRF token is allowed

### Plan limit sync
The final branch limit source is:

1. current plan `plan_limits.branch_limit`
2. current plan `plans.max_branches`
3. subscription snapshot fallback
4. active `extra_branch` add-ons

When SaaS Admin changes a legacy plan, `TenantProductSubscriptionSyncService` refreshes the product subscription mirror and `TenantProductSubscriptionLimitSyncService` updates `included_seats` and `branch_limit` snapshots.

### Workspace Owner
The Workspace Owner has implicit management access and does not consume product seats by default. Use `Sync Owner Access` when explicit compatibility records are needed.

Emergency recovery:

```bash
php artisan tenant:grant-owner {tenant} {email} --sync-access
```

## Post-Package Backlog
- Migrate remaining Automotive user management screens to product access and product roles as the primary write path.
- Move branch UI to show central branch records plus product activation state.
- Add central audit and approval services in a dedicated package before replacing existing product-specific approval/audit records.
- Add report/search/timeline/import-export registries when those workflows are actively implemented.
- Review legacy views/controllers only after route and browser QA proves replacement paths.
