# Automotive SaaS - Project AI Context

## 1) Project Identity
- Project: `Automotive SaaS`
- Stack:
  - Laravel 10
  - PHP 8.2
  - `stancl/tenancy` v3
  - multi-database tenancy
  - central admin + front customer portal + tenant admin
  - Stripe billing
- Server:
  - app path: `/var/www/automotive`
  - deploy branch: `main`

## 2) Required Workflow
- Work locally first.
- Read current files before changing anything.
- After each real change provide:
  - full file paths
  - exact commands
  - UI test steps
- Standard git flow:
  - `git add ...`
  - `git commit -m "..."`
  - `git push origin main`

## 3) Non-Negotiable Rules
- Never assume project state from memory only.
- Always trust current repo files over old chat summaries.
- Never use:
  - `php artisan route:cache`
- Reason:
  - tenant/product routing is dynamic
  - route cache breaks route resolution in this project
- Be defensive with schema differences.
- Production may differ slightly from local in some columns:
  - example: `currency_code` vs `currency`
- Do not blindly change the base shared theme if an isolated automotive copy already exists.

## 4) High-Level Architecture

### 4.1 Main Areas
- `Central Admin`
  - central domain
  - isolated `admin` auth
  - central management for plans, tenants, subscriptions, reports, notifications, logs
- `Automotive Front Customer Portal`
  - `web` auth
  - user registers/logs in here first
  - then chooses free trial or paid subscription
- `Automotive Tenant Admin`
  - tenant domain
  - isolated `automotive_admin` auth
  - real tenant workspace/system

### 4.2 Current Architectural Direction
- User does not enter the tenant admin as the first step.
- Correct flow is:
  - register/login to automotive front
  - open customer portal
  - start trial or subscribe
  - after provisioning/payment, access tenant system

### 4.3 Layout / Namespace Separation
- Central admin views live under:
  - `resources/views/admin/*`
- Automotive front views live under:
  - `resources/views/automotive/front/*`
- Automotive portal views live under:
  - `resources/views/automotive/portal/*`
- Automotive tenant admin views live under:
  - `resources/views/automotive/admin/*`

This separation is intentional and should remain.

## 5) Critical Warnings
- `route:cache` is forbidden here.
- Production cache driver may be `file`; tenant-context cache tagging can break flows.
- This already affected impersonation and was fixed by removing cache usage there.
- Some production records may expose `currency` while other code expects `currency_code`.
- Test runs may create temporary tenant sqlite files under `database/tenant_*`.
  - Do not commit them.

## 6) Current Confirmed Core Modules

### 6.1 Central Admin
Confirmed present in code:
- plans management
- coupons CRUD
- subscriptions management
- billing reports
- tenants management
- admin notifications center
- activity logs
- system error logs
- SaaS general settings
- reference data:
  - currencies
  - countries
  - states
  - cities

Important central files:
- `app/Http/Controllers/Admin/PlanController.php`
- `app/Http/Controllers/Admin/CouponController.php`
- `app/Http/Controllers/Admin/SubscriptionController.php`
- `app/Http/Controllers/Admin/TenantController.php`
- `app/Http/Controllers/Admin/BillingReportController.php`
- `app/Http/Controllers/Admin/AdminNotificationController.php`
- `app/Http/Controllers/Admin/AdminActivityLogController.php`
- `app/Http/Controllers/Admin/SystemErrorLogController.php`

### 6.2 Automotive Front / Portal
Confirmed present in code:
- custom front login/register/reset flow
- customer portal
- start free trial
- paid checkout start
- Stripe success/cancel return
- Stripe webhook endpoint

Important front files:
- `routes/products/automotive/front.php`
- `app/Http/Controllers/Automotive/Front/Auth/LoginController.php`
- `app/Http/Controllers/Automotive/Front/Auth/RegisterController.php`
- `app/Http/Controllers/Automotive/Front/Auth/ForgotPasswordController.php`
- `app/Http/Controllers/Automotive/Front/Auth/ResetPasswordController.php`
- `app/Http/Controllers/Automotive/Front/CustomerPortalController.php`

### 6.3 Automotive Tenant Admin
Confirmed present in code:
- tenant login/logout
- dashboard
- users
- branches
- products
- stock transfers
- stock movements
- inventory reports
- billing status/change/renew/cancel/resume
- impersonation entry + stop impersonation

Important tenant admin routes file:
- `routes/products/automotive/admin.php`

## 7) Billing System Status
Confirmed in codebase:
- Stripe integration exists
- billing plan catalog exists
- Stripe subscription sync exists
- Stripe invoice history/backfill exists
- lifecycle normalization exists
- billing reports exist
- tenant billing status screen exists

Important billing services:
- `app/Services/Billing/StripeSubscriptionSyncService.php`
- `app/Services/Billing/StripeWebhookSyncService.php`
- `app/Services/Billing/StripeInvoiceHistoryService.php`
- `app/Services/Billing/StripeInvoiceLedgerBackfillService.php`
- `app/Services/Billing/SubscriptionLifecycleNormalizationService.php`
- `app/Services/Billing/TenantBillingLifecycleService.php`
- `app/Services/Billing/BillingNotificationService.php`

Lifecycle statuses in use:
- `trialing`
- `active`
- `past_due`
- `grace_period`
- `suspended`
- `canceled`
- `expired`

## 8) Notifications System Status
Confirmed done and used by billing/admin flows:
- central admin notifications index/show/unread/stream
- bulk actions
- archive/delete/read flows
- feature tests exist under:
  - `tests/Feature/Admin/Notifications/*`

## 9) Tenants Management Status
This is the most recently expanded central admin area.

### 9.1 Confirmed Existing Features
- tenants list
- tenants details page
- tenant/domain/subscription summary on details page
- suspend latest subscription
- activate latest subscription
- extend trial
- change plan
- safe delete tenant
- impersonate tenant admin
- upgraded tenants index filters and quick actions

Main files:
- `app/Http/Controllers/Admin/TenantController.php`
- `app/Services/Admin/AdminTenantLifecycleService.php`
- `app/Services/Admin/TenantImpersonationService.php`
- `resources/views/admin/tenants/index.blade.php`
- `resources/views/admin/tenants/show.blade.php`

### 9.2 Safe Tenant Deletion
Confirmed implemented:
- route:
  - `DELETE admin/tenants/{tenantId}`
- deletes linked central records:
  - `domains`
  - `tenant_users`
  - `subscriptions`
  - `coupon_redemptions`
  - tenant row
- tries tenant DB drop only when appropriate
- blocks deletion if tenant still has a live Stripe-linked subscription

### 9.3 Tenant Impersonation
Confirmed implemented:
- central admin can impersonate tenant admin from tenant details or tenants index
- token is encrypted payload based
- no cache dependency
- avoids cache-tagging failure in tenant context
- tenant-side routes:
  - `GET automotive/admin/impersonate/{token}`
  - `POST automotive/admin/stop-impersonation`
- header banner shows impersonation mode and stop button

### 9.4 Tenants Index Upgrade
Confirmed implemented:
- better search
- filters:
  - status
  - plan
  - gateway
  - has domain
  - created from
  - created to
- quick actions:
  - view
  - subscription
  - impersonate
  - suspend/activate
  - open tenant

### 9.5 Tenants Tests Present
- `tests/Feature/Admin/Tenants/AdminTenantDeletionTest.php`
- `tests/Feature/Admin/Tenants/AdminTenantImpersonationTest.php`
- `tests/Feature/Admin/Tenants/AdminTenantsIndexTest.php`

## 10) Subscriptions Management Status

### 10.1 Existing Before Latest Work
Already present before latest expansion:
- subscriptions index
- subscription details page
- sync from Stripe
- backfill Stripe invoices
- refresh local billing state
- normalize lifecycle fields

### 10.2 Latest Added: Advanced Control
Confirmed implemented now:
- service:
  - `app/Services/Admin/AdminSubscriptionControlService.php`
- new central admin actions:
  - force lifecycle state
  - local cancel
  - local resume
  - local renew
  - manual update of:
    - `trial_ends_at`
    - `grace_ends_at`
    - `ends_at`
- new routes:
  - `POST admin/subscriptions/{subscription}/manual-action`
  - `POST admin/subscriptions/{subscription}/timestamps`

### 10.3 Important Protection
- Advanced manual control is blocked for Stripe-linked subscriptions.
- Reason:
  - prevent local lifecycle drift against Stripe source of truth

### 10.4 Subscription Notifications / Logging
Manual subscription actions now generate:
- admin activity logs
- billing/admin notifications for:
  - manual lifecycle change
  - manual timestamp update

### 10.5 Subscription Tests Present
- `tests/Feature/Admin/Subscriptions/AdminSubscriptionAdvancedControlTest.php`

## 11) Portal / Front State
Current confirmed state from code:
- automotive auth routes are active:
  - `/automotive/login`
  - `/automotive/register`
  - password reset routes
- authenticated user routes:
  - `/automotive/portal`
  - `/automotive/portal/start-trial`
  - `/automotive/portal/subscribe`
  - checkout success/cancel
- Stripe webhook route:
  - `/automotive/webhooks/stripe`

Important current note:
- `resources/views/automotive/front/*` still exists for front auth/controllers
- `resources/views/automotive/portal/*` also exists as isolated portal namespace
- any future work must read current actual view wiring first before changing assumptions

## 12) Tenant Admin State
Current confirmed state from code:
- tenant admin auth routes exist
- tenant billing area exists
- subscription access middleware exists:
  - `tenant.subscription.active`
- impersonation route is active
- stop impersonation route is active

Important tenant admin route file:
- `routes/products/automotive/admin.php`

## 13) Production Issues Already Encountered And Fixed

### 13.1 Plan Currency Fallback
Real production issue:
- some plan payloads expose `currency`
- some code expected `currency_code`

Fix applied:
- tenant details page now safely falls back to:
  - `currency_code ?? currency ?? ''`

### 13.2 Impersonation Cache Tagging Failure
Real production issue:
- `This cache store does not support tagging`

Cause:
- tenant-context cache tagging + production cache store limitations

Fix applied:
- impersonation no longer uses cache-backed tokens
- now uses encrypted short-lived token payload

### 13.3 Impersonation Banner Position
Real UI issue:
- banner started visually under sidebar/logo instead of inside header

Fix applied:
- banner moved inline into tenant admin header partial

## 14) Known Operating Constraints
- Production mail delivery for some admin auth/reset flows may still need real SMTP/Mailgun verification.
- Roles & permissions with Laratrust are not yet confirmed as implemented in the current codebase.
- SSE real-time notifications are not yet confirmed as implemented.
- Full SaaS automation jobs for delayed suspend/delete are not yet confirmed as implemented.

## 15) Current Priority State

### 15.1 Confirmed Done
- billing foundation
- billing reports
- admin notifications center
- activity log foundation/UI
- coupons CRUD and billing integration
- central tenants management major controls
- tenant impersonation
- subscriptions advanced control

### 15.2 Next Logical Priorities
If continuing from current state, the most natural next options are:
- improve subscriptions index timeline and quick actions
- implement roles & permissions for central admin
- implement real-time notifications via SSE
- implement automation jobs for lifecycle suspend/delete policies

## 16) Validation / Testing Notes
- Do not rely only on code inspection for UI-heavy changes.
- After any future admin change, test:
  - source page
  - success path
  - blocked/error path
  - sidebar/header visibility
  - redirect/session behavior
  - related list screen
  - notifications/activity logs if applicable

Useful local validation commands:
```powershell
php -l <file>
php artisan route:list --name=admin.tenants
php artisan route:list --name=admin.subscriptions
php artisan route:list --name=automotive.admin
php artisan view:clear
php artisan view:cache
```

For current feature tests, sqlite in-memory is the reliable local path:
```powershell
$env:DB_CONNECTION='sqlite'
$env:DB_DATABASE=':memory:'
php artisan test tests\Feature\Admin\Tenants
php artisan test tests\Feature\Admin\Subscriptions\AdminSubscriptionAdvancedControlTest.php
php artisan test tests\Feature\Admin\Notifications
```

## 17) Most Important Files Right Now

### Central Admin
- `app/Http/Controllers/Admin/TenantController.php`
- `app/Http/Controllers/Admin/SubscriptionController.php`
- `app/Services/Admin/AdminTenantLifecycleService.php`
- `app/Services/Admin/TenantImpersonationService.php`
- `app/Services/Admin/AdminSubscriptionControlService.php`
- `resources/views/admin/tenants/index.blade.php`
- `resources/views/admin/tenants/show.blade.php`
- `resources/views/admin/subscriptions/index.blade.php`
- `resources/views/admin/subscriptions/show.blade.php`
- `resources/views/admin/layouts/centralLayout/partials/sidebar.blade.php`

### Automotive Front / Portal
- `routes/products/automotive/front.php`
- `app/Http/Controllers/Automotive/Front/CustomerPortalController.php`
- `resources/views/automotive/front/*`
- `resources/views/automotive/portal/*`

### Automotive Tenant Admin
- `routes/products/automotive/admin.php`
- `app/Http/Controllers/Automotive/Admin/Auth/AuthController.php`
- `resources/views/automotive/admin/layouts/adminLayout/partials/header.blade.php`

## 18) Bottom Line
If a new session starts from this file only, the safest current summary is:

- Central admin is broadly functional and now includes strong tenant/subscription operations.
- Tenants management is substantially advanced, including deletion and impersonation.
- Subscriptions management now includes advanced local controls for non-Stripe subscriptions.
- Notifications are present and tested.
- The codebase has multiple isolated UI namespaces; keep them separated.
- Do not use `route:cache`.
- Always verify current files before assuming older flow descriptions are still correct.
