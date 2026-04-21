# Integration Verification Checklist

Run this after deploying the system app and before starting new product feature work.

## 1) Deploy Code

```bash
cd /var/www/automotive
git pull origin main
composer install --no-dev --optimize-autoloader
```

## 2) Run Migrations

Run central migrations first, then tenant migrations.

```bash
php artisan migrate --force
php artisan tenants:migrate --force
```

Do not run `php artisan route:cache`.

## 3) Clear Runtime Caches

```bash
php artisan optimize:clear
php artisan config:cache
php artisan view:cache
php artisan event:cache
php artisan queue:restart
sudo systemctl restart php8.2-fpm
```

## 4) Verify Integration Readiness

Run the contract-only check:

```bash
php artisan tenancy:verify-integration-readiness
```

Run the full tenant check for a tenant that has Automotive, Parts Inventory, and Accounting active:

```bash
php artisan tenancy:verify-integration-readiness --tenant=TENANT_ID
```

Expected result:

```text
Workspace integration readiness verification passed.
```

## 5) UI Smoke Test

Use a real tenant workspace.

- Open `/workspace/admin/dashboard`
- Open Workshop Operations
- Open General Ledger
- Open Spare Parts stock screens
- Create or open a work order
- Add a labor line with a positive amount
- Complete the work order
- Confirm General Ledger shows an `automotive-accounting` handoff
- Post the accounting event to journal
- Post one valued inventory movement and confirm `parts-accounting` handoff appears

If any handoff is `failed` or `skipped`, fix the missing product activation/runtime issue and retry from General Ledger diagnostics.
