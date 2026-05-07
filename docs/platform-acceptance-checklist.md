# Platform Acceptance Checklist

## Scope
This checklist is the production acceptance reference for the product-scoped multi-product platform foundation completed through Package 9.

It covers the shared platform foundation only. It does not replace browser QA for Automotive maintenance workflows.

## Completed Foundations
- Product catalog and product-key entitlement compatibility.
- Product subscription limits and add-ons.
- Tenant user product access and product seat enforcement.
- Product branch activation and user product branch access.
- Product-scoped roles and permissions.
- Central customers, suppliers, and employees.
- Central mPDF document engine and product document templates.
- Central numbering sequences.
- Central attachments and storage limits.
- Central tenant notifications and product-aware notification templates.
- Legacy Automotive maintenance attachment and notification bridge.

## Deployment Checks
The deployment workflow must run central migrations and tenant migrations:

```bash
php artisan migrate --force
php artisan tenants:migrate --force
```

The deployment workflow must not run the full test suite as a mandatory deployment step while legacy or environment-specific tests are being stabilized.

The deployment workflow must never run:

```bash
php artisan route:cache
```

Allowed cache commands:

```bash
php artisan optimize:clear
php artisan route:clear
php artisan config:clear
php artisan view:clear
php artisan config:cache
php artisan view:cache || true
php artisan event:cache || true
```

## Runtime Storage Checks
Tenant runtime storage directories such as `storage/tenantclient-1/` are runtime data and must not be committed.

`.gitignore` must include:

```text
/storage/tenant*/
```

## Server Verification Commands
Run these after deploy:

```bash
cd /var/www/automotive

git log --oneline -n 5

php artisan migrate --force
php artisan tenants:migrate --force

php artisan route:clear
php artisan config:clear
php artisan view:clear
php artisan cache:clear

php artisan test tests/Feature/Tenancy/ProductEntitlementServiceTest.php tests/Feature/Tenancy/TenantUserProductAccessServiceTest.php tests/Feature/Tenancy/ProductBranchAccessServiceTest.php tests/Feature/Tenancy/ProductPermissionServiceTest.php tests/Feature/Tenancy/CentralBusinessEntitiesTest.php tests/Feature/Tenancy/DocumentEngineAndNumberingTest.php tests/Feature/Tenancy/AttachmentAndNotificationFoundationTest.php tests/Feature/Tenancy/PlatformProductionAcceptanceTest.php

php artisan route:list --name=automotive.admin --except-vendor
```

## Tenant Database Verification
Tenant database names may contain dashes. Use backticks in MySQL:

```sql
USE `tenant_client-1`;
```

Example table checks:

```bash
mysql -e "USE \`tenant_client-1\`; SHOW TABLES LIKE 'tenant_attachments'; SHOW TABLES LIKE 'tenant_notifications'; SHOW TABLES LIKE 'notification_templates'; SHOW TABLES LIKE 'numbering_sequences'; SHOW TABLES LIKE 'employees'; SHOW TABLES LIKE 'product_roles'; SHOW TABLES LIKE 'tenant_user_product_access';"
```

## Foundation Seeder Safety
Foundation/demo seeders must be run only in the intended tenant context and are written to be idempotent through `updateOrCreate` or `firstOrCreate`.

Relevant seeders:
- `TenantBranchDemoSeeder`
- `TenantBusinessEntityDemoSeeder`
- `TenantDocumentFoundationSeeder`
- `TenantNotificationFoundationSeeder`

## Acceptance Status
- `automotive.admin.*` route names must remain present.
- `routes/tenant.php` must remain present.
- Central services must not contain Automotive-only storage paths or notification event assumptions.
- Legacy Automotive tables remain available until a dedicated migration path replaces each read/write path safely.
