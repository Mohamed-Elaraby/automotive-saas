# Automotive Maintenance Production Acceptance

This checklist closes the Automotive Maintenance / Workshop Management SaaS build-out and should be run after every deploy that changes maintenance, documents, tenant routing, integrations, or billing activation.

## Deploy Commands

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan tenants:migrate --force
php artisan config:clear
php artisan view:clear
php artisan maintenance:verify-readiness
php artisan maintenance:verify-readiness --tenant=TENANT_ID
php artisan tenancy:verify-integration-readiness --tenant=TENANT_ID
```

Never run:

```bash
php artisan route:cache
```

Tenant and product routes are resolved dynamically. Route caching can break runtime route resolution.

## Server Prerequisites

- PHP extensions required by Laravel, mPDF, image uploads, and tenant database drivers.
- `mpdf/mpdf` installed through Composer.
- Writable storage for uploaded photos, generated PDFs, snapshots, and tenant documents.
- Queue worker configured before using queued document generation.
- SSE-capable web server/proxy settings that do not buffer live event streams.
- Optional OCR: install `tesseract-ocr`; if it is absent, VIN entry remains manual and the workflow continues.

## Acceptance Checks

- Tenant subscription activates the Automotive workspace without duplicate table errors.
- `php artisan tenants:migrate --force` completes without long-index-name errors.
- Admin route names remain under `automotive.admin.maintenance.*`.
- `routes/tenant.php` remains present and loaded.
- Vehicle check-in supports customer/vehicle selection, manual VIN, camera/photo upload, condition map, signatures, and check-in reporting.
- VIN OCR is optional and never trusted without human confirmation.
- Work orders, technician jobs, inspections, QC, delivery, warranty, complaints, invoices, receipts, and payment requests use separate statuses.
- Internal notes do not appear in customer portal pages, customer-safe APIs, or customer documents.
- PDFs are generated through the central document service and mPDF renderer only.
- Customer approval, tracking, complaints, feedback, and payment request links are token based.
- Maintenance API tokens are hashed, revocable, scope-limited, and logged.
- Spare parts and accounting remain optional integration handoffs, not hard dependencies.

## Smoke Test Flow

1. Subscribe a tenant to the Automotive product.
2. Open the tenant admin workspace dashboard.
3. Create a customer and vehicle through check-in.
4. Confirm VIN manually, upload photos, and save a condition map.
5. Create an estimate, send it, and approve it from the customer link.
6. Create a work order job, start it, complete it, and send it to QC.
7. Pass QC and prepare delivery.
8. Create an invoice, generate a payment request, open the customer-safe payment link, and mark it paid.
9. Generate check-in, work order, estimate, invoice, receipt, QC, delivery, and warranty PDFs.
10. Run `php artisan maintenance:verify-readiness --tenant=TENANT_ID` again.

## Git Push Template

```bash
git status
git add .
git commit -m "Complete automotive maintenance production readiness"
git push origin main
```
