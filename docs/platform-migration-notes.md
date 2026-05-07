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
