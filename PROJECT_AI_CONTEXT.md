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
- products catalog management
- plans management
- coupons CRUD
- subscriptions management
- billing reports
- tenants management
- admin notifications center
- activity logs
- system error logs
- SaaS general settings
- product enablement requests monitoring
- reference data:
  - currencies
  - countries
  - states
  - cities

Important central files:
- `app/Http/Controllers/Admin/ProductController.php`
- `app/Http/Controllers/Admin/PlanController.php`
- `app/Http/Controllers/Admin/CouponController.php`
- `app/Http/Controllers/Admin/SubscriptionController.php`
- `app/Http/Controllers/Admin/ProductEnablementRequestController.php`
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
- admin topbar notifications already use SSE live updates
- customer portal now has its own central `customer_portal_notifications` feed
- portal header now includes a live notifications dropdown
- portal notifications support:
  - unread summary
  - SSE stream
  - mark read
- product enablement flow notifications now behave as follows:
  - when customer submits a new enablement request:
    - admin receives a notification
  - when admin approves or rejects:
    - customer portal receives the notification
  - admin does not receive a redundant notification for their own approve/reject action
- feature tests exist under:
  - `tests/Feature/Admin/Notifications/*`

Important portal notification files:
- `app/Http/Controllers/Automotive/Front/CustomerPortalNotificationController.php`
- `app/Models/CustomerPortalNotification.php`
- `app/Services/Notifications/CustomerPortalNotificationService.php`
- `database/migrations/2026_04_07_120000_create_customer_portal_notifications_table.php`
- `resources/views/automotive/portal/layouts/portalLayout/partials/header.blade.php`

## 9) Tenants Management Status
This is the most recently expanded central admin area.

### 9.1 Confirmed Existing Features
- tenants list
- tenants details page
- tenant/domain/subscription summary on details page
- tenant details page now also shows `tenant_product_subscriptions` with:
  - product
  - plan
  - status
  - gateway / Stripe IDs
  - lifecycle timestamps
  - diagnostics count
- central admin now also has a dedicated `tenant_product_subscriptions` index screen with filters for:
  - tenant
  - product
  - status
  - gateway
- central admin now also has a dedicated details page for each `tenant_product_subscription`
  - overview
  - lifecycle timeline
  - tenant snapshot
  - diagnostics
  - latest local invoice snapshot
  - health hints based on Stripe/billing data completeness
  - direct `Sync From Stripe` action
  - persisted last-sync metadata:
    - `last_synced_from_stripe_at`
    - `last_sync_status`
    - `last_sync_error`
- the central `tenant_product_subscriptions` index now supports:
  - filtering by `last_sync_status`
  - filtering by sync freshness:
    - never synced
    - last 24h
    - older than 7 days
  - quick `Sync From Stripe` action from the table itself
  - bulk operations:
    - `Sync Selected`
    - `Sync All Filtered`
    - `Retry Failed Only`
  - `Sync Selected` remains immediate
  - `Sync All Filtered` and `Retry Failed Only` are now queue-driven jobs
  - bulk sync returns a persisted summary via flash + activity log
  - the screen now surfaces recent bulk operations directly from `admin_activity_logs`
  - CSV export of the current filtered result set
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

### 10.6 Latest Subscription/Admin Billing Work
Confirmed implemented after the above:
- subscriptions index improved with:
  - lifecycle timeline summary
  - quick actions directly from list
  - redirect back to index after actions
- local admin subscription control fixed to always reload Eloquent model safely
  - previous `stdClass::fresh()` failure fixed
- Stripe-link blocking logic tightened
  - local controls are blocked only for real Stripe-linked subscriptions
  - `gateway_customer_id` alone no longer blocks local actions
- subscription show page now includes diagnostics for:
  - gateway value
  - has customer id
  - has subscription id
  - has checkout session id
  - local controls blocked yes/no
- admin Stripe actions added on subscription show:
  - cancel at period end on Stripe
  - cancel immediately on Stripe
  - resume on Stripe
  - change plan on Stripe
- invalid Stripe actions are now guarded:
  - cannot resume a terminal cancelled Stripe subscription
  - cannot cancel immediately twice on a terminal cancelled Stripe subscription
  - admin UI shows unavailable state instead of misleading enabled buttons

Important files:
- `app/Http/Controllers/Admin/SubscriptionController.php`
- `app/Services/Admin/AdminSubscriptionControlService.php`
- `app/Services/Billing/StripeSubscriptionManagementService.php`
- `app/Services/Billing/StripeSubscriptionPlanChangeService.php`
- `resources/views/admin/subscriptions/index.blade.php`
- `resources/views/admin/subscriptions/show.blade.php`
- `tests/Feature/Admin/Subscriptions/AdminSubscriptionsIndexTest.php`
- `tests/Feature/Admin/Subscriptions/AdminStripeSubscriptionActionsTest.php`

### 10.7 Latest Billing Automation / Policy Hardening
Confirmed implemented after the above:
- `billing:run-lifecycle` now handles:
  - trial ending soon notifications
  - `past_due -> suspended`
  - `canceled -> expired` when `ends_at` is reached
- tenant cleanup policy is now explicit and safer:
  - automatic cleanup is limited to expired trial tenants
  - tenants with Stripe/billing linkage are skipped
  - expired paid tenants are not auto-deleted by cleanup
- cleanup eligibility logic is extracted to a dedicated service:
  - `app/Services/Billing/TenantCleanupEligibilityService.php`
- command-level tests now exist for lifecycle automation and cleanup policy

Important files:
- `app/Console/Commands/Billing/RunBillingLifecycleCommand.php`
- `app/Console/Commands/TenantsCleanup.php`
- `app/Services/Billing/TenantBillingLifecycleService.php`
- `app/Services/Billing/TenantCleanupEligibilityService.php`
- `tests/Feature/Billing/BillingLifecycleCommandsTest.php`
- `tests/Feature/Billing/TenantCleanupEligibilityServiceTest.php`

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

### 11.1 Latest Portal/Billing Flow Fixes
Confirmed implemented:
- after terminal Stripe cancellation, customer portal can start a new paid checkout again
- portal no longer treats terminal/expired Stripe subscriptions as live subscriptions
- restart paid checkout after terminal cancellation now:
  - reuses subscription row safely
  - clears terminal Stripe linkage where needed
  - updates selected `plan_id`
  - updates `gateway_price_id`
  - uses `past_due` as pending checkout state instead of invalid `null`
- `StartPaidCheckoutService` now sends a flat plan audit payload to Stripe billing audit
  - this fixed false mismatch errors like:
    - `The selected plan pricing does not match the linked Stripe price...`
- portal status messaging improved:
  - expired subscription now invites customer to start a new Stripe checkout
  - instead of only warning that workspace access is blocked
- paid plans UI in portal was redesigned to match project theme more closely
- paid plans cards now show:
  - real price
  - real limits
  - real selected features
  - limits merged into `What you get`
  - no separate ugly boxed limits block
- customer portal now supports non-automotive product focus via:
  - `?product=<slug>`
- non-automotive products can submit `product enablement requests` from the portal
- portal now distinguishes enablement request states correctly:
  - `pending`
  - `approved`
  - `rejected`
- after admin rejection, portal no longer shows stale `pending`
- rejected requests now show a warning and allow re-submission
- inactive products now show `Product Coming Soon`
  - and do not show misleading request buttons
- admin notifications for new enablement requests now deep-link to:
  - `Product Enablement Requests`
  - filtered by:
    - pending
    - product
    - tenant search
- customer portal now has live notifications in the header for enablement decisions
- portal `checkout success/cancel` now preserves selected product context in the redirect

Important files:
- `app/Http/Controllers/Automotive/Front/CustomerPortalController.php`
- `app/Services/Automotive/StartPaidCheckoutService.php`
- `app/Services/Billing/BillingPlanCatalogService.php`
- `resources/views/automotive/portal/index.blade.php`
- `tests/Feature/Automotive/Portal/CustomerPortalBillingOptionsTest.php`

### 11.2 Latest Product Enablement Admin Workflow
Confirmed implemented:
- admin now has a dedicated request details screen for `product_enablement_requests`
- request details page shows:
  - product
  - tenant
  - portal user
  - requested/approved/rejected timestamps
  - current latest `tenant_product_subscription`
- admin can approve or reject from the request details page
- admin can now store decision notes on approve/reject
- decision notes are saved to `product_enablement_requests.notes`
- customer portal decision notification now includes the admin note when present
- approve now creates or activates the matching `tenant_product_subscription`
- reject keeps the request rejected and informs the customer only

Important files:
- `app/Http/Controllers/Admin/ProductEnablementRequestController.php`
- `app/Services/Admin/ProductEnablementApprovalService.php`
- `resources/views/admin/product-enablement-requests/index.blade.php`
- `resources/views/admin/product-enablement-requests/show.blade.php`
- `tests/Feature/Admin/ProductEnablementRequestsIndexTest.php`

### 11.3 Latest Additional Product Checkout Foundation
Confirmed implemented:
- approved non-automotive products can now start a paid checkout from the customer portal
- this new checkout path works on the same tenant/workspace
- it uses `tenant_product_subscriptions` as the operational record for the additional product
- new service added:
  - `app/Services/Automotive/StartAdditionalProductCheckoutService.php`
- Stripe checkout metadata now includes:
  - `tenant_product_subscription_id`
  - `product_scope`
- Stripe `checkout.session.completed` now supports updating `tenant_product_subscriptions`
  - without requiring a legacy `subscriptions` row for the additional product flow
- this is currently a foundation step:
  - checkout start is implemented
  - checkout session completion mirror/update is implemented
  - full Stripe lifecycle parity for additional products still remains a follow-up item

Important files:
- `app/Services/Automotive/StartAdditionalProductCheckoutService.php`
- `app/Services/Billing/Gateways/StripePaymentGateway.php`
- `app/Services/Billing/StripeWebhookSyncService.php`
- `resources/views/automotive/portal/index.blade.php`
- `tests/Feature/Automotive/Portal/CustomerPortalBillingOptionsTest.php`
- `tests/Feature/Billing/StripeWebhookSyncServiceTest.php`

### 11.4 SaaS Flow Validation State
Confirmed from latest code/tests:
- webhook checkout completion calls tenant workspace provisioning service
- tenant admin access middleware is covered for:
  - active access allowed
  - blocked tenant redirected to billing
  - billing routes remain accessible while blocked
- trial provisioning rollback is covered:
  - if `tenants:migrate` fails during trial setup, central records are rolled back safely

Important test files:
- `tests/Feature/Billing/StripeWebhookSyncServiceTest.php`
- `tests/Feature/Automotive/Admin/EnsureTenantSubscriptionIsActiveMiddlewareTest.php`
- `tests/Feature/Automotive/Portal/StartTrialServiceTest.php`

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

### 13.4 Tenant Identification Noise On Server IP
Real production/runtime behavior identified:
- requests hitting server IP directly like `216.128.148.123` can trigger tenancy identification failure
- tenant routes use `InitializeTenancyByDomain`
- if host is not a known tenant domain and not in `tenancy.central_domains`, tenancy throws
- exception handler then stores:
  - `system_error_logs`
  - `admin_notifications`
- this is why repeated `SYSTEM ERROR: Tenant could not be identified on domain ...` notifications can appear

Important note:
- this is usually external traffic/bots or health checks on raw IP
- not necessarily a billing bug
- if central app must be served on raw IP, add the IP to `config/tenancy.php`
- otherwise better ignore/filter this exception from notifications in future hardening work

### 13.5 Tenant Identification Noise Filtering
Confirmed implemented after the issue above:
- raw IP / clearly invalid host tenancy identification noise is now ignored by exception logging/notification flow
- this prevents repeated admin notifications and system error logs for:
  - direct server IP traffic
  - obviously invalid hosts
- real hostname-based tenancy identification failures are still recorded

Important files:
- `app/Exceptions/Handler.php`
- `tests/Feature/Tenancy/TenantIdentificationNoiseFilteringTest.php`

## 14) Known Operating Constraints
- Production mail delivery for some admin auth/reset flows may still need real SMTP/Mailgun verification.
- Roles & permissions with Laratrust are not yet confirmed as implemented in the current codebase.
- SSE real-time notifications are confirmed for:
  - central admin topbar notifications
  - customer portal topbar notifications
- SSE is not yet confirmed as implemented for tenant admin.
- lifecycle automation exists via scheduled commands/services, but is not built as a full jobs/queue workflow yet.
- `StartTrialService` rollback-on-failure is covered, but full success-path trial provisioning coverage is still a remaining validation item.

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
- subscriptions index quick actions
- admin Stripe subscription controls
- restart checkout flow after terminal Stripe cancellation
- portal paid plans redesign
- plan features refactor to shared catalog
- live plan preview in admin plan form
- tenancy exception noise filtering
- billing lifecycle scheduled automation baseline
- tenant cleanup policy hardening
- billing-features search/filter and usage drilldown
- plans index filtering and plan usage drilldown
- admin plan limits semantics guidance
- webhook provisioning invocation coverage
- tenant subscription access middleware coverage
- trial provisioning rollback coverage
- trial onboarding success-path coverage
- paid onboarding success-path coverage
- tenant admin login/access end-to-end coverage
- tenant product routes loading hardened by replacing `require_once` with `require` inside `routes/tenant.php`
- central `products` catalog added
- central admin `products` CRUD UI is now live:
  - index
  - create
  - edit
  - safe delete when product is unused
- `plans.product_id` added and current automotive plans attached to `automotive_service`
- `tenant_product_subscriptions` table/model added with legacy backfill
- onboarding and billing flows now mirror legacy `subscriptions` into `tenant_product_subscriptions`
- automotive billing catalog and portal became product-aware
- tenant/portal read path now prefers `tenant_product_subscriptions` with legacy fallback
- customer portal now shows a visible `Products Catalog` section in UI
- non-automotive products can now be selected from the portal and use a dedicated enablement panel
- `product_enablement_requests` flow is now live end-to-end at the UI layer:
  - customer can request enablement from portal
  - central admin can list/filter requests
  - central admin can approve or reject requests
  - portal reflects request status correctly after admin action
- `product_enablement_requests` admin workflow is now more operational:
  - request details page
  - decision notes
  - customer-facing decision notification with note
- portal notification system is now live:
  - `customer_portal_notifications`
  - portal header dropdown
  - unread summary
  - SSE stream
  - mark read
- admin notification for new product enablement requests now opens the filtered request list directly
- Stripe admin sync can now recover missing `gateway_subscription_id` from checkout session or customer lookup
- Stripe sync now keeps `tenant_product_subscriptions` mirrored
- Stripe cancellation sync logic now respects `current_period_end`:
  - future period end => `cancelled`
  - past period end => `expired`
- additional product paid checkout foundation is now live:
  - approved non-automotive product can start checkout in portal
  - checkout metadata includes `tenant_product_subscription_id`
  - webhook completion updates `tenant_product_subscriptions`
- tenant details page now exposes `tenant_product_subscriptions` operationally in central admin
- central admin now has a first dedicated browsing/filtering screen for `tenant_product_subscriptions`
- central admin can now open a single `tenant_product_subscription` record directly for diagnostics

### 15.2 Next Logical Priorities
If continuing from current state, the most natural next options are:
- extend the new additional-product checkout foundation into full lifecycle coverage:
  - `customer.subscription.updated`
  - `customer.subscription.deleted`
  - invoice/payment events
  - keep `tenant_product_subscriptions` fully in sync for non-automotive products
- decide whether additional products should remain `tenant_product_subscription`-only
  - or whether a hybrid legacy `subscriptions` mirror is still required for some central admin screens
- expand central admin visibility beyond a single tenant details page:
  - add richer reporting and troubleshooting actions on top of the new list/filter screen
- move from SaaS foundation validation into tenant product MVP work once billing consistency is confirmed
- implement roles & permissions for central admin
- real-time notifications via SSE are already active for admin + portal topbars; expand only if tenant admin also needs them
- convert lifecycle automation to queue/jobs only if truly needed later

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
php artisan test tests\Feature\Admin\Plans
php artisan test tests\Feature\Automotive\Portal\CustomerPortalBillingOptionsTest.php
php artisan test tests\Feature\Billing\BillingLifecycleCommandsTest.php
php artisan test tests\Feature\Billing\TenantCleanupEligibilityServiceTest.php
php artisan test tests\Feature\Billing\StripeWebhookSyncServiceTest.php
php artisan test tests\Feature\Automotive\Admin\EnsureTenantSubscriptionIsActiveMiddlewareTest.php
php artisan test tests\Feature\Automotive\Portal\StartTrialServiceTest.php
```

Important migration note after latest plan features refactor:
- running latest code now requires central migrations for:
  - `billing_features`
  - `billing_feature_plan`
  - dropping old `plan_features`
- deploy flow must include:
```powershell
git pull origin main
php artisan migrate --force
php artisan view:clear
php artisan view:cache
```

## 17) Most Important Files Right Now

### Central Admin
- `app/Http/Controllers/Admin/TenantController.php`
- `app/Http/Controllers/Admin/SubscriptionController.php`
- `app/Http/Controllers/Admin/PlanController.php`
- `app/Http/Controllers/Admin/ProductEnablementRequestController.php`
- `app/Http/Controllers/Admin/BillingFeatureController.php`
- `app/Services/Admin/AdminTenantLifecycleService.php`
- `app/Services/Admin/TenantImpersonationService.php`
- `app/Services/Admin/AdminSubscriptionControlService.php`
- `app/Services/Admin/ProductEnablementApprovalService.php`
- `app/Console/Commands/Billing/RunBillingLifecycleCommand.php`
- `app/Console/Commands/TenantsCleanup.php`
- `app/Services/Billing/StripeSubscriptionManagementService.php`
- `app/Services/Billing/StripeSubscriptionPlanChangeService.php`
- `app/Services/Billing/TenantCleanupEligibilityService.php`
- `resources/views/admin/tenants/index.blade.php`
- `resources/views/admin/tenants/show.blade.php`
- `resources/views/admin/subscriptions/index.blade.php`
- `resources/views/admin/subscriptions/show.blade.php`
- `resources/views/admin/plans/index.blade.php`
- `resources/views/admin/plans/_form.blade.php`
- `resources/views/admin/billing-features/*`
- `resources/views/admin/product-enablement-requests/*`
- `resources/views/admin/layouts/centralLayout/partials/sidebar.blade.php`

### Automotive Front / Portal
- `routes/products/automotive/front.php`
- `app/Http/Controllers/Automotive/Front/CustomerPortalController.php`
- `app/Http/Controllers/Automotive/Front/CustomerPortalNotificationController.php`
- `app/Services/Automotive/StartPaidCheckoutService.php`
- `app/Services/Automotive/StartAdditionalProductCheckoutService.php`
- `app/Services/Automotive/StartTrialService.php`
- `app/Services/Automotive/ProvisionTenantWorkspaceService.php`
- `app/Services/Billing/BillingPlanCatalogService.php`
- `app/Services/Notifications/CustomerPortalNotificationService.php`
- `resources/views/automotive/front/*`
- `resources/views/automotive/portal/*`

### Automotive Tenant Admin
- `routes/products/automotive/admin.php`
- `app/Http/Controllers/Automotive/Admin/Auth/AuthController.php`
- `resources/views/automotive/admin/layouts/adminLayout/partials/header.blade.php`
- `tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`

## 18) Multi-Product SaaS Direction

### 18.1 Target Model
The system should move from:
- `one tenant = one automotive product workspace`

To:
- `one tenant = one customer company/workspace`
- `one tenant can subscribe to one or more products`
- products can be activated later without creating a new tenant

Examples:
- automotive service management
- parts/inventory management
- accounting
- future modules such as rental management

### 18.2 Architectural Rule
Do **not** create a separate tenant per product.

Correct direction:
- single tenant per customer
- shared tenant users, branches, customers, suppliers, currencies, taxes, settings
- separate product modules on top of the same tenant context

This is required for:
- automatic cross-product integration
- shared customer/supplier master data
- unified reporting
- unified authentication and permissions
- easier upsell from one product to another later

### 18.3 New Central Concepts Required
Add a central `products` catalog table, for example:
- `id`
- `code`
- `name`
- `slug`
- `description`
- `is_active`
- `sort_order`

Add central `tenant_product_subscriptions`, for example:
- `id`
- `tenant_id`
- `product_id`
- `plan_id`
- `status`
- `gateway`
- `gateway_customer_id`
- `gateway_subscription_id`
- `gateway_checkout_session_id`
- `gateway_price_id`
- lifecycle timestamps similar to current `subscriptions`

Likely evolution path:
- current `plans` become product-aware via `product_id`
- current `subscriptions` should either:
  - evolve into `tenant_product_subscriptions`
  - or remain as legacy base records temporarily during migration

### 18.4 Shared Core vs Product Modules
Shared tenant core should hold things like:
- tenant users
- branches
- customers
- suppliers
- company settings
- currencies / taxes

Product modules should hold their own operational data:
- automotive service:
  - vehicles
  - job cards
  - repair orders
  - inspections
- parts/inventory:
  - warehouses
  - stock lots
  - purchasing
  - sales invoices
- accounting:
  - chart of accounts
  - journals
  - vouchers
  - ledgers

### 18.5 Integration Rule Between Products
Products should integrate through clear application/domain events, not by tightly coupling tables ad hoc.

Examples:
- service invoice posted -> accounting journal entry
- spare parts consumed in repair order -> stock movement + accounting cost entry
- sales invoice posted in parts module -> customer balance + accounting posting

### 18.6 Current Single-Product Assumptions That Must Be Refactored
Current code still assumes `automotive` as the only product in several areas:
- `routes/products/automotive/*`
- `app/Http/Controllers/Automotive/Front/CustomerPortalController.php`
- `app/Services/Automotive/StartTrialService.php`
- `app/Services/Automotive/StartPaidCheckoutService.php`
- `app/Services/Automotive/ProvisionTenantWorkspaceService.php`
- portal views under `resources/views/automotive/portal/*`
- tenant admin entry URLs like `/automotive/admin/login`
- plans/subscriptions currently have no `product_id`

### 18.7 Recommended Migration Strategy
Use staged migration, not a big-bang rewrite.

Stage 1:
- add `products` catalog
- seed first product = `automotive-service`
- add `product_id` to `plans`
- keep current billing behavior working for the first product

Stage 2:
- introduce `tenant_product_subscriptions`
- update portal and checkout services to subscribe per product
- show available products in the customer portal

Stage 3:
- extract common onboarding/billing flow into product-aware services
- examples:
  - `StartProductTrialService`
  - `StartProductCheckoutService`
  - `ProvisionTenantProductService`

Stage 4:
- keep one shared tenant admin shell
- load menus/modules depending on active subscribed products

Stage 5:
- implement second product without creating a second tenant
- verify automatic shared-data integration

### 18.8 Immediate Next Execution Step
Current progress in this staged migration:
- Stage 1 is done:
  - central `products` exists
  - first product `automotive_service` exists
  - `plans` are product-aware
- Stage 2 is partially done:
  - `tenant_product_subscriptions` exists
  - legacy onboarding/billing flows mirror into it
  - portal and tenant read path already prefer it
- portal UI now exposes a visible products catalog

The next execution step is:
- validate real Stripe/live-record consistency after the new sync recovery logic
- then add product-level purchase/enablement flow for the second product on the same tenant

Operational support now added for this step:
- artisan command:
  - `php artisan billing:review-stripe-consistency`
  - supports:
    - `--sync`
    - `--only-issues`
    - `--format=table|json|csv`
    - `--output=/path/to/file`
- mirror repair command:
  - `php artisan billing:repair-product-subscription-mirrors`
  - supports:
    - `--apply`
    - `--only-missing`
    - `--tenant=...`
    - `--subscription=...`
- runbook:
  - `STRIPE_CONSISTENCY_REVIEW_RUNBOOK.md`

Portal foundation now added for the next multi-product step:
- customer portal supports selecting a catalog product via query/CTA
- non-automotive products now open a product-specific enablement/plans panel
- direct checkout remains intentionally limited to automotive for now
- this is a safe UI foundation before implementing second-product purchase flow

### 18.9 Current Multi-Product Implementation Status
Already implemented:
- `app/Models/Product.php`
- `app/Models/TenantProductSubscription.php`
- `database/migrations/2026_04_04_120000_create_products_table.php`
- `database/migrations/2026_04_04_121000_add_product_id_to_plans_table.php`
- `database/migrations/2026_04_04_122000_create_tenant_product_subscriptions_table.php`
- `database/seeders/ProductSeeder.php`
- product-aware billing catalog reads
- product-aware automotive portal paid plans
- product-aware tenant/portal read path
- portal `Products Catalog` UI block

Important implementation note:
- current runtime still keeps legacy `subscriptions` as the operational base record in many admin/billing areas
- `tenant_product_subscriptions` is now populated and read in the tenant-facing path, but central admin screens are still primarily legacy-subscription based
- migration is therefore in a safe hybrid state, not yet full cutover

### 18.10 Stripe Consistency Lessons From Real Data
Recent real-record review exposed two important production-relevant behaviors:
- some local Stripe-linked subscriptions may have:
  - `gateway_customer_id`
  - `gateway_checkout_session_id`
  - but missing `gateway_subscription_id`
- local plan/status may be stale before Stripe sync

Fixes now implemented:
- admin `Sync From Stripe` can recover missing `gateway_subscription_id`
  - first from Stripe checkout session
  - then from customer subscriptions if the lookup is unambiguous
- Stripe sync now mirrors lifecycle/identity updates back into `tenant_product_subscriptions`
- Stripe sync no longer maps `canceled` blindly to `expired`
  - it now checks `current_period_end`

Important operator note:
- if a local Stripe-linked subscription looks inconsistent in admin vs portal, run admin `Sync From Stripe` again after the latest fixes
- after sync:
  - plan should match the actual Stripe `price_id`
  - status should reflect Stripe cancellation timing more accurately

Important cleanup note:
- local tests generate temporary tenant sqlite files under `database/tenant_*`
- these files were accidentally committed once during manual git flow
- they should remain ignored and never be committed again

### 18.11 Additional Product Portal Delivery Pass
The additional-product portal flow received a production-readiness pass focused on finishing delivery, not adding more admin tooling.

Portal behavior now clarified:
- non-automotive products still require enablement approval before checkout becomes available
- once approved, checkout is live from the portal for product-specific plans
- the portal now shows product-scoped billing state messages for additional products:
  - pending Stripe checkout
  - active live billing already attached to the workspace
  - `past_due` / `suspended`
  - `expired` / `canceled`

Important implementation note:
- the generic top-level billing banners were not enough because they were mostly primary-product focused
- product-specific state is now computed per selected product and rendered inside the selected product plan section

Test coverage added/updated:
- outdated portal expectation text was updated to match the real enabled flow
- additional product pending checkout state is covered
- additional product live billing managed-in-system state is covered
- portal product-catalog labels are now more dynamic:
  - active products with paid plans show `AVAILABLE NOW` and `Browse Product Plans`
  - active products without paid plans still show enablement-oriented copy
- misleading `coupon_code` values that look like email addresses are now hidden from the portal badge UI
- the same coupon sanitization now also applies before `Start Free Trial`, so invalid email-like coupon values no longer block trial creation

### 18.12 Admin Plan Form Product Awareness
Admin plan creation/edit is now operationally aligned with the multi-product model:
- `product_id` is selected directly in the plan form and validated on save
- the plan preview in admin continues to be live and now also reflects:
  - selected product
  - live description changes
- the portal preview binding bug was fixed:
  - the preview now targets the actual plan form by explicit form id
  - it no longer accidentally binds to another form in the admin layout
- plan index/show screens now surface the linked product explicitly
- plan index now supports filtering by product
- products index links each plan count directly to the filtered plans view
- the empty filtered-plans state no longer boots DataTables incorrectly:
  - this fixed the `Incorrect column count` warning when opening a product with zero linked plans

### 18.13 Tenant Workspace Product Context
The tenant workspace foundation is no longer treated purely as a single-product automotive shell.

What changed:
- tenant admin sidebar now loads the tenant's attached workspace products from `tenant_product_subscriptions`
- tenant dashboard now shows a `Workspace Products` card for the same tenant
- this makes the shared workspace aware of:
  - the primary automotive product
  - any additional subscribed products such as accounting or spare parts

Important scope note:
- this does **not** yet add separate routes/modules for non-automotive products
- it is the required foundation before product capabilities/modules can be attached and surfaced inside the same workspace

### 18.14 Product Capabilities / Modules Foundation
Product-level capabilities/modules now exist as a central admin-managed concept separate from plans.

What exists now:
- `product_capabilities` table
- central admin CRUD per product
- product index shows capability counts and links into capability management
- tenant workspace dashboard now surfaces active capabilities for attached products
- a dedicated seeder now creates one default capability per product code:
  - `automotive_service` -> `Workshop Operations`
  - `parts_inventory` -> `Supplier Catalog`
  - `accounting` -> `General Ledger`
  - unknown products receive a fallback core capability
- the customer portal also now surfaces included product capabilities for the currently selected product

Important architectural note:
- capabilities are currently metadata and visibility signals
- they do **not** yet create tenant routes/controllers/pages per non-automotive product
- this is the right sequence:
  1. attach products to the tenant workspace
  2. define product capabilities centrally
  3. surface those capabilities in the shared workspace
  4. later implement each capability/module runtime

Important operator note:
- to create pricing for a non-automotive product such as accounting or spare parts, create the product first, then create plans attached to that product from the plans form

### 18.15 Workspace Product Switcher Foundation
The shared tenant workspace can now focus on a selected attached product inside the same tenant domain.

What changed:
- tenant dashboard accepts `workspace_product` as a focused workspace context selector
- sidebar workspace-product entries now link back into the same dashboard with product focus preserved
- dashboard now shows:
  - focused workspace product
  - the focused product's capabilities
  - quick switching buttons between attached products

Important scope note:
- this is still a context/navigation layer, not full module runtime routing
- it is the correct step before implementing product-specific module pages/controllers under the shared workspace

### 18.16 Product-Aligned Tenant Runtime Reorganization
The tenant admin workspace has now been reorganized so the automotive product no longer implicitly owns inventory and transfer modules.

What changed:
- introduced a central workspace module catalog service to define which tenant-admin modules belong to each product focus
- automotive focus now shows only service-oriented runtime entry:
  - `Workshop Operations`
- spare parts focus now owns inventory-related runtime modules:
  - `Supplier Catalog`
  - `Stock Items`
  - `Inventory Adjustments`
  - `Stock Transfers`
  - `Inventory Report`
  - `Stock Movement Report`
- accounting focus now has its own runtime entry:
  - `General Ledger`
- added product-aware tenant middleware so product-owned routes are blocked when the tenant does not actually have that product attached
- tenant admin sidebar, header shortcuts, and dashboard are now product-focus aware
- tenant inventory product UI has been relabeled from generic `Products` to `Stock Items`
- fixed a pre-existing bug in `StockTransferController` where `ValidationException` was caught without being imported

Important scope note:
- this is the first real runtime separation step between products inside the shared tenant workspace
- the runtime entry pages for workshop, supplier catalog, and general ledger are currently operational module landing pages, not full business submodules yet
- old inventory pages still exist, but they now belong to the `parts_inventory` product path conceptually and operationally

### 18.17 Shared Workspace Modules and Product Family Inference
The tenant workspace runtime is now explicitly split into:
- shared/core workspace modules
- product-specific modules

What changed:
- `Users`, `Branches`, and `Plans & Billing` are now treated as shared workspace modules
- they are rendered once at workspace level, regardless of how many subscribed products the tenant has
- product-specific sections now only render product-owned modules:
  - automotive service -> `Workshop Operations`
  - spare parts -> inventory-related modules
  - accounting -> `General Ledger`
- the module catalog now deduplicates by stable module key instead of relying on route text or product count

Important architectural improvement:
- runtime behavior no longer depends only on exact hardcoded product codes such as `accounting` or `parts_inventory`
- a product-family inference layer now resolves the workspace behavior from product code / slug / name
- this makes the shared workspace more resilient to future products such as:
  - accounting suite
  - spare parts pro
  - verticalized products for other industries

Important scope note:
- this is the correct direction for a multi-product and multi-industry SaaS shell:
  - one shared workspace
  - one set of shared/core modules
  - one set of product modules per attached product family
  - future cross-product integrations can later be layered on top without duplicating shared navigation

### 18.18 Cross-Product Integration Rules Foundation
The tenant workspace now has an explicit integration layer between product families instead of only separate navigation.

What changed:
- introduced a dedicated product-family resolver service:
  - family inference no longer lives only inside the module catalog
  - route access and runtime behavior can now resolve from product code / slug / name consistently
- introduced a workspace integration catalog service
- dashboard and module landing pages now surface `Cross-Product Integrations` / `Connected Product Integrations`
- current integration rules now communicate how product families work together:
  - automotive service can consume spare-parts inventory
  - automotive service can later hand off financial events to accounting
  - spare parts can feed workshop operations
  - spare parts can later hand valuation and purchasing into accounting
  - accounting can later receive service-side and inventory-side events

Important operator note:
- this does not yet implement real business posting between the products
- it establishes the runtime integration map and navigation flow first
- this is the right intermediate step before building actual:
  - workshop parts consumption
  - accounting journal posting
  - inventory valuation handoff

### 18.19 First Real Cross-Product Business Flow: Workshop Consumes Spare Parts
The first real business integration between products now exists inside the tenant workspace.

What changed:
- `Workshop Operations` now shows live spare-parts stock when the tenant also has a connected spare-parts product
- the workshop module now allows consuming a stock item directly from the connected spare-parts inventory
- successful consumption:
  - decrements tenant inventory
  - records a stock movement
  - tags the movement with a typed reference
  - surfaces the result in a `Recent Workshop Consumptions` panel

### 18.20 Workshop Work Orders Foundation
The workshop integration is no longer only a generic stock-consumption action. It now has a basic work-order layer.

What changed:
- added a tenant-level `work_orders` table and `WorkOrder` model
- `Workshop Operations` now allows creating real work orders inside the tenant workspace
- spare-parts consumption from workshop operations now requires selecting a valid work order first
- stock movements created by workshop consumption are now linked to:
  - `reference_type = App\\Models\\WorkOrder`
  - `reference_id = work_order.id`
- the module page now shows:
  - `Create Work Order`
  - `Recent Work Orders`
  - `Recent Workshop Consumptions` linked to work-order context

Implementation note:
- stock movement type is still stored as `adjustment_out` for speed and backward compatibility
- the important improvement is that the movement is now tied to a real workshop business record instead of a generic string reference
- this creates the correct base for later:
  - service/job lifecycle
  - parts usage history per job
  - future accounting handoff from workshop operations

### 18.21 Work Order Lifecycle and Consumption History
The workshop work-order layer now has a usable lifecycle instead of only creation plus stock consumption.

What changed:
- work orders now support operational status transitions:
  - `open`
  - `in_progress`
  - `completed`
- consuming spare parts on a work order automatically moves it from `open` to `in_progress`
- completed work orders are now treated as closed business records and carry `closed_at`
- the tenant workspace now has a dedicated work-order details screen
- the details screen exposes:
  - work-order overview
  - status update action
  - consumed spare-parts history tied to that exact work order

Important scope note:
- this is still a lightweight workshop lifecycle, not a full service-management engine yet
- there is still no customer/vehicle/job-line model
- but the system now has the correct business spine for:
  - job-level parts usage
  - job completion state
  - later accounting and service-order expansion

### 18.22 Workshop Customer and Vehicle Foundation
Workshop operations now include the minimum service context needed before expanding into a full service engine.

What changed:
- added tenant-level `vehicles` table plus `Customer` / `Vehicle` models
- work orders can now be linked to:
  - `customer_id`
  - `vehicle_id`
- `Workshop Operations` now exposes lightweight creation forms for:
  - workshop customers
  - workshop vehicles
  - work orders linked to those records
- the work-order details screen now shows:
  - linked customer
  - linked vehicle
  - parts consumption history for that specific service record

Why this matters:
- the workshop runtime is no longer a generic stock-consumption demo
- it now has the minimum domain objects needed for future service workflows:
  - customer history
  - vehicle history
  - service records
  - later service lines / labor / accounting posting

### 18.23 Work Order Service Lines and Financial Summary
Work orders now include billable/service structure instead of only metadata plus parts consumption history.

What changed:
- added tenant-level `work_order_lines`
- work-order lines now support at least two practical line types:
  - `labor`
  - `part`
- labor lines can now be added manually from the work-order details page
- part lines are now created automatically when workshop stock is consumed from Spare Parts
- the work-order details page now exposes:
  - labor/service line entry
  - unified work-order lines list
  - financial summary:
    - labor subtotal
    - parts subtotal
    - grand total

Important scope note:
- this is still a lightweight pricing model
- part lines are currently valued from tenant product `sale_price`
- there is still no dedicated tax/discount/invoice model for workshop jobs yet
- but the system now has the correct base for:
  - service pricing
  - job-level totals
  - future accounting handoff when a work order is completed

### 18.24 Product-Aware Plan Seeding and Checkout Stripe Price Recovery
Paid plan setup and checkout resilience have been improved for the current multi-product phase.

What changed:
- the old static automotive-only plan seeder with hardcoded Stripe price ids was replaced
- the plan seeder now creates product-aware default plans for every active product:
  - automotive gets a trial plus paid plans
  - all active products get paid plans
- seeded paid plans attempt Stripe sync automatically when Stripe is configured
- checkout now attempts automatic Stripe plan recovery before failing when:
  - a paid plan has no `stripe_price_id`
  - the linked Stripe price is inactive
  - the linked Stripe price no longer matches the local plan
- this recovery is now used in both:
  - primary automotive checkout
  - additional product checkout

Important operator note:
- if the runtime environment has working Stripe credentials, checkout can now self-heal stale plan mappings in many cases
- if the central database connection is unavailable, the seeder cannot be executed from that environment until DB connectivity is restored

### 18.25 Local Accounting Handoff for Completed Work Orders
Completed workshop jobs can now create a local accounting handoff record instead of leaving pricing totals as display-only information.

What changed:
- added tenant-level `accounting_events`
- when a `work_order` is moved to `completed` and the tenant has an active accounting product attached:
  - a local accounting event is posted automatically
  - the event stores:
    - labor subtotal
    - parts subtotal
    - grand total
    - lightweight payload with work-order/customer/vehicle context
- the work-order details screen now shows `Accounting Handoff`
- the accounting runtime entry now shows an `Accounting Events Ledger` list

Important scope note:
- this is still a local posting/event layer, not a full double-entry accounting engine
- no journal accounts, no debit/credit balancing, and no invoice integration yet
- this is the correct intermediate stage before implementing deeper accounting posting

### 18.26 Tenant Admin Sidebar Cleanup and Automotive Runtime Restructure
The tenant admin navigation is now more operational and less ambiguous.

What changed:
- the automotive service section now exposes separate sidebar/table entries for:
  - `Workshop Operations`
  - `Work Orders`
  - `Customers`
  - `Vehicles`
- the accounting section now surfaces ledger/event visibility more clearly
- the work-order details page now activates the work-order section properly in sidebar state

Why this matters:
- the tenant admin no longer relies on one oversized workshop page for every service action
- key runtime tables can now be reached directly from navigation
- this is closer to how future products and industries should plug into the shared workspace shell

### 18.27 Workshop Operations UI Reorganized Around Real Workflow
The workshop runtime screen has been restructured around the actual sequence of work.

What changed:
- `Workshop Operations` now highlights the flow as:
  - create customer
  - register vehicle
  - create work order
  - consume spare parts
- the page now exposes quick table links for:
  - customers
  - vehicles
  - work orders
  - accounting events
- the page now surfaces operational counters for:
  - customers
  - vehicles
  - open work orders
  - accounting handoffs
- the screen also shows:
  - recent work orders
  - available spare-parts stock
  - recent accounting handoffs
  - recent workshop consumptions

Operator note:
- the workshop page is still a compact operator screen, not a polished final ERP workspace yet
- but it is now significantly more readable and aligned with the real workflow than the earlier mixed form layout

### 18.28 Product-Agnostic First Subscription Flow
The customer portal no longer assumes that `automotive_service` must always be the first subscribed product.

What changed:
- the portal now passes the selected `product_id` into:
  - `start trial`
  - `start paid checkout`
- `StartTrialService` now resolves the active trial plan for the selected product instead of using a global `slug = trial` lookup
- `StartPaidCheckoutService` now resolves the selected product from:
  - explicit `product_id`, or
  - the chosen plan's `product_id`
- first paid checkout can now start for a non-automotive product when no workspace exists yet
- first free-trial provisioning can now also start for a non-automotive product when that product has an active trial plan
- the portal trial button is now product-aware:
  - it includes the selected product
  - it only appears when the selected product actually has an active trial plan

Behavioral impact:
- a user can now begin from `Accounting`, `Spare Parts`, or any future product that has:
  - an active product record
  - an active trial plan or paid plan
- the same reserved subdomain/tenant bootstrap path is reused
- once the first product is provisioned, later products can still attach to the same tenant workspace

Current limitation:
- the public onboarding/auth shell is still branded and routed under the automotive portal namespace
- this package removed the biggest billing/onboarding blocker, but it did not yet rename the workspace shell into a fully product-neutral portal

### 18.29 Product-Neutral Portal Shell
The public portal shell is now significantly less automotive-specific even though the route namespace still remains under `automotive/*` for compatibility.

What changed:
- portal auth branding now says `Shared SaaS Portal` instead of `Automotive Customer Portal`
- portal labels now prefer workspace-neutral wording such as:
  - `Open My Workspace`
  - `Go to My Workspace`
  - `Open Workspace Login`
  - `Open Product Workspace`
- customer-facing success messaging was neutralized from:
  - `Your free trial system is ready now`
  - to `Your workspace trial is ready now`
- the portal footer now uses `Shared Workspace SaaS`

Service-layer impact:
- `TenantPlanService` now still prefers the automotive product subscription when present for backward compatibility
- but if no automotive product subscription exists, it can now fall back to another valid product subscription for the tenant
- `TenantSubscriptionService` now follows the same fallback behavior

Why this matters:
- a tenant created from `Accounting` or `Spare Parts` no longer depends on having an automotive product subscription just to resolve current plan/subscription state
- this is the necessary read-path complement to the earlier product-agnostic first-checkout work

Current limitation:
- route names and URL paths are still under the automotive namespace for compatibility
- the shell is now product-neutral in behavior and copy, but not yet fully renamed at the route structure level

### 18.30 Product Manifest / Module Manifest Foundation
Workspace product families, modules, actions, and integrations are no longer defined only through hardcoded `match` blocks inside services.

What changed:
- added a dedicated manifest config:
  - `config/workspace_products.php`
- added `WorkspaceManifestService` as the access layer for:
  - shared workspace modules
  - family aliases
  - product sidebar sections
  - dashboard actions
  - quick-create actions
  - cross-product integration definitions
- `WorkspaceProductFamilyResolver` now resolves product family from manifest aliases instead of fixed keyword logic only
- `WorkspaceModuleCatalogService` now hydrates sidebar/actions/experience from the manifest
- `WorkspaceIntegrationCatalogService` now hydrates integrations from the manifest and injects the connected target product automatically

Why this matters:
- adding a new product family now requires much less service-layer editing
- new industries or verticals can define:
  - aliases
  - module navigation
  - dashboard actions
  - integration links
  from one config location instead of touching multiple catalog services

Test coverage:
- added a dedicated manifest test proving that a new family such as `retail_commerce` can be added by config and immediately resolved/rendered by the workspace services
- existing tenant admin access/runtime tests still pass after the refactor

### 18.31 Manifest-Driven Runtime Module Pages
The runtime module pages themselves no longer depend on large hardcoded title/description/link blocks inside `WorkspaceModuleController`.

What changed:
- added `runtime_modules` definitions to the workspace manifest for:
  - `workshop-operations`
  - `workshop-customers`
  - `workshop-vehicles`
  - `workshop-work-orders`
  - `supplier-catalog`
  - `general-ledger`
- `WorkspaceManifestService` can now resolve a runtime module by key
- `WorkspaceModuleController` now loads page metadata from the manifest:
  - focus family / focus code
  - title
  - description
  - quick links

Why this matters:
- adding or reshaping a module page no longer requires a large controller edit for basic metadata
- workspace runtime screens are now aligned with the same manifest that already drives:
  - sidebar sections
  - dashboard actions
  - integrations

Scope note:
- the actual business data resolvers for each runtime module still live in code
- this package moves navigation/page-definition metadata into the manifest, not the full business workflows

### 18.32 Manifest-Driven Module Access Alignment
Route-level access for workspace modules is now better aligned with the same manifest layer that defines the modules themselves.

What changed:
- `EnsureTenantHasWorkspaceProduct` can now accept either:
  - a product family key
  - or a runtime module key
- the middleware resolves the owning family from the manifest when a runtime module key is used
- runtime module routes such as:
  - `workshop-operations`
  - `workshop-customers`
  - `workshop-vehicles`
  - `workshop-work-orders`
  - `supplier-catalog`
  - `general-ledger`
  can now be guarded by their manifest module keys instead of family-only hardcoding
- `WorkspaceManifestService` now exposes:
  - owner-family resolution for module keys
  - focus-code resolution for redirects
  - accessible-family helper logic for tenant workspace products

Service alignment:
- `WorkshopPartsIntegrationService` now checks connected spare-parts availability through the manifest helper
- `WorkOrderAccountingHandoffService` now checks accounting availability through the manifest helper

Why this matters:
- manifest ownership now influences:
  - sidebar rendering
  - dashboard actions
  - integration cards
  - runtime page metadata
  - route access checks
- this reduces the remaining places where module ownership is hardcoded separately from the manifest

### 18.33 Manifest-Driven Workspace Focus and Family Metrics
Default workspace focus and dashboard runtime decisions are now less dependent on hardcoded product codes.

What changed:
- `TenantWorkspaceProductService` now enriches workspace products with:
  - `product_family`
  - manifest-aware `is_primary_workspace_product`
- primary workspace detection now follows the manifest default family instead of only checking `product_code === automotive_service`
- when no primary-family product exists, focus now falls back to:
  - first accessible product
  - then first available product
- `DashboardController` now decides whether to show parts/inventory runtime metrics by resolved family, not by an exact product code match

Test coverage:
- added coverage proving that an alias product such as `Inventory Hub` can still drive the Spare Parts dashboard experience through family resolution

Why this matters:
- future products do not need to reuse exact legacy codes like `parts_inventory` just to unlock the correct workspace behavior
- this makes the manifest/family layer much more useful for new industries and renamed products

## 18.34) Tenant Billing Read Path and Billing Screen Alignment
Status: completed

What changed:
- `TenantPlanService` and `TenantSubscriptionService` no longer prefer `products.code = automotive_service` directly
- both services now:
  - inspect joined `products.code / slug / name`
  - resolve the owning family through `WorkspaceManifestService`
  - prefer the manifest default family first
  - then fall back to the best active/trialing/past-due/canceled product subscription
- `BillingController` now resolves the primary billing product identity from:
  - the focused workspace product when present
  - else the current plan's product
  - else the current subscription's product
  - else the manifest default family experience
- tenant admin billing status now keeps `workspace_product` across billing action forms
- inline payment-method management is now explicitly disabled for non-primary attached products in this screen

UI effect:
- if the tenant's primary accessible workspace product is not literally `automotive_service`, the billing page now loads:
  - the correct product title
  - the correct paid plan catalog
  - the correct selected plan context
- attached non-primary product billing remains read-only here, without exposing misleading inline billing controls

Test coverage:
- added read-path coverage proving manifest default family is preferred over unrelated attached product subscriptions
- added billing page coverage proving:
  - attached non-primary accounting billing stays read-only
  - a primary alias product such as `Inventory Hub` loads its own billing plan catalog correctly

Why this matters:
- the workspace can now present a more accurate billing experience when the tenant starts from a non-legacy product code
- this closes another remaining assumption that the default billable product must always be identified by the exact `automotive_service` code

## 18.35) Non-Primary Product Billing Write Path in Tenant Admin
Status: completed

What changed:
- tenant admin billing is no longer read-only for attached non-primary products
- attached product billing now supports:
  - renewal checkout start from the tenant admin billing screen
  - in-place Stripe plan changes on `tenant_product_subscriptions`
  - Stripe customer portal access
  - cancel at period end
  - resume subscription
- inline payment-method update remains intentionally limited to the primary billing flow for now

Implementation details:
- added `StripeTenantProductSubscriptionPlanChangeService`
- `BillingController` now:
  - routes attached-product renewals through `tenant_product_subscription_id`
  - updates `tenant_product_subscriptions.gateway_checkout_session_id` for new checkout sessions
  - uses the new product-subscription Stripe plan-change service for attached products
  - no longer blocks portal/cancel/resume actions just because the product is non-primary
- tenant admin billing UI copy now reflects that attached-product billing actions are supported in this screen

Test coverage:
- added billing feature coverage proving:
  - attached product billing page now shows actionable product-scoped billing controls
  - attached product plan change uses the product-subscription plan-change service
  - attached product renew starts checkout on the correct `tenant_product_subscription`

Why this matters:
- tenant admins can now manage billing for additional subscribed products from inside the workspace instead of being forced back to the separate portal flow
- this closes another major blocker in the move from “automotive-first workspace” to true multi-product tenant billing

## 18.36) Product-Scoped Payment Method Management in Tenant Billing
Status: completed

What changed:
- tenant admin billing payment-method endpoints now resolve the same focused billing context as the main billing page
- `createSetupIntent` now creates the setup intent for the focused product subscription's Stripe customer, not just the primary subscription
- `saveDefaultPaymentMethod` now saves the default payment method against:
  - the primary legacy `subscriptions` row when the billing context is primary
  - the focused `tenant_product_subscription` when the billing context is an attached product
- the inline payment form now sends `workspace_product` in its AJAX payloads so the correct product billing context is used end-to-end
- `StripePaymentMethodManagementService` was widened from `Subscription` only to a generic Stripe-linked subscription object so it can work with `TenantProductSubscription` too

Test coverage:
- added billing feature coverage proving an attached product can:
  - create its own setup intent for the correct Stripe customer
  - save its own default payment method through the focused billing context

Why this matters:
- attached products are now much closer to first-class billing citizens inside the tenant workspace
- this removes another remaining primary-product-only assumption from tenant admin billing operations

## 18.37) Product-Scoped Billing UX for Invoice History and Portal Controls
Status: completed

What changed:
- tenant admin billing UI now labels actions and invoice sections using the focused billing product name
- attached-product billing subtitle and guidance copy were updated to reflect that the screen now supports:
  - checkout
  - plan change
  - payment method management
  - invoice history
  - Stripe lifecycle actions
- inline payment-method updates are now enabled for any focused Stripe-linked product subscription, not only the primary billing product
- billing portal actions now remain product-scoped in both behavior and visible labeling
- invoice history headings and empty-state messages now clearly reference the focused billing product

Test coverage:
- extended billing page coverage proving:
  - attached-product billing shows product-scoped payment-method and portal labels
  - attached-product billing portal uses the attached product's Stripe customer context

Why this matters:
- tenant admins now see a much clearer multi-product billing experience instead of a page that still feels primary-product-centric
- this reduces operational confusion once multiple products are attached to the same tenant workspace

## 18.38) First Runtime Module Outside Automotive: Supplier Catalog
Status: completed

What changed:
- `Spare Parts` now has its first true runtime module beyond inventory reports and stock tables:
  - `Supplier Catalog`
- added tenant-side `suppliers` table
- added `Supplier` model and `SupplierCatalogService`
- `WorkspaceModuleController::supplierCatalog()` now loads real supplier data instead of rendering a shell only
- added `storeSupplier()` endpoint and route under the tenant workspace
- `supplier-catalog` module UI now includes:
  - supplier summary cards
  - create supplier form
  - supplier table with active/inactive state

Test coverage:
- extended `TenantAdminAccessFlowTest` to prove:
  - supplier catalog page renders inside the Spare Parts workspace
  - a supplier can be created from the tenant workspace
  - the created supplier appears back in the runtime module UI

Why this matters:
- the project now has its first non-automotive runtime module with actual CRUD-like behavior inside the shared workspace
- this provides the concrete pattern to repeat for:
  - accounting modules
  - future industrial/trading modules
  - additional product families declared through the workspace manifest

## 18.39) Tenant Billing Control Moved Into Customer Portal
Status: completed

What changed:
- introduced a dedicated portal-side billing controller:
  - `App\Http\Controllers\Automotive\Front\PortalBillingController`
- added portal billing routes under:
  - `/automotive/portal/billing`
  - renew
  - change-plan
  - payment-method setup/default
  - Stripe customer portal
  - cancel/resume
  - success/cancel return handlers
- added a dedicated portal billing screen:
  - `resources/views/automotive/portal/billing/status.blade.php`
- portal overview and portal topbar now link to the new workspace billing screen
- tenant admin navigation was cleaned so billing is no longer presented as a normal workspace module entry:
  - removed `Plans & Billing` from shared workspace sidebar manifest
  - removed the upgrade CTA card from tenant admin sidebar
  - removed billing shortcuts from tenant admin profile dropdown
- kept tenant admin billing route as a compatibility/fallback path for existing access/redirect behavior, but normal tenant-facing billing control is now portal-first

Behavior now:
- tenant owners manage subscription changes, payment method, invoices, cancellation, resume, and Stripe portal access from the customer portal
- tenant admin area is now visually focused on subscribed runtime systems/modules instead of tenant profile/billing management
- portal billing is product-scoped:
  - switch between subscribed workspace products
  - inspect selected plan audit
  - renew
  - change plan
  - update default payment method
  - open Stripe portal
  - cancel/resume
  - inspect invoice history

Test coverage:
- extended portal feature coverage proving the new billing page renders and manages an attached product subscription
- extended tenant admin access flow coverage proving `Plans & Billing` no longer appears in the main tenant admin dashboard navigation

Why this matters:
- this is the first concrete step toward making the customer portal the tenant's real account/profile control surface
- it separates:
  - tenant profile and billing control in the portal
  - product runtime operations in tenant admin
- this is the correct foundation before expanding the SaaS to more industries and product families

## 18.40) Seeders Now Activate All Current Products and Create Plans for Each
Status: completed

What changed:
- `ProductSeeder` no longer leaves `parts_inventory` and `accounting` inactive by default
- all current seeded products are now active out of the box:
  - `automotive_service`
  - `parts_inventory`
  - `accounting`
- `PlanSeeder` now creates a full seeded catalog for every active product, not only automotive-style paid plans:
  - trial
  - starter monthly
  - growth monthly
  - pro yearly
- this means every currently seeded product now has ready-to-test plans immediately after seeding

Test coverage:
- updated `PlanSeederTest` to assert:
  - trial plans exist for all current products
  - total seeded plans count reflects all products
- updated `ProductPlanCatalogBootstrapTest` to assert:
  - all current seeded products are active
  - each current seeded product gets its own seeded trial plan

Why this matters:
- local/demo/test environments now match the intended multi-product SaaS behavior better
- portal and billing flows can be tested across current systems without manually activating products or creating plans first

## 18.41) Product-Specific Trial Control and Less Automotive-Biased Portal Entry
Status: completed

What changed:
- added `trial_days` to `plans`
- trial duration is now controlled from the admin plan form on each trial plan instead of being hardcoded to `14` days only
- `StartTrialService` now provisions `trial_ends_at` from the selected trial plan's `trial_days`
- `PlanSeeder` now differentiates seeded product pricing/limits instead of cloning the same numbers across all products:
  - automotive service
  - parts inventory
  - accounting
- each current product now gets:
  - its own trial plan
  - its own trial duration
  - product-differentiated paid pricing and limits
- customer portal copy was adjusted so the first view is less automotive-biased:
  - the section title is now generic product subscription wording
  - the standalone profile-level trial CTA was removed
  - product trial CTA is now shown inside the selected product section
  - trial CTA only appears once the user explicitly focuses a product in the catalog

Test coverage:
- extended admin plan form coverage to assert `Trial Days` is present
- extended `StartTrialServiceTest` to prove non-automotive trials can use custom `trial_days`
- updated portal UI expectations for the more neutral product subscription wording

Why this matters:
- trial behavior is now product-aware and administratively controllable
- the public portal no longer feels as if automotive is the only real first-class product on first open
- seeded plans now look more credible during demos and testing because pricing/limits differ by system

## 19) Bottom Line
If a new session starts from this file only, the safest current summary is:

- Central admin is broadly functional and now includes strong tenant/subscription operations.
- Tenants management is substantially advanced, including deletion and impersonation.
- Admin plan and billing-feature management is now much more operationally usable:
  - billing-features filtering/search
  - billing-feature usage drilldown
  - plans filtering
  - plan usage drilldown
  - clearer limits semantics in plan form/index
- Subscriptions management now includes:
  - advanced local controls for non-Stripe subscriptions
  - Stripe-native admin actions for real Stripe subscriptions
  - better diagnostics and safer action gating
- Billing lifecycle automation exists and is tested at command level:
  - trial ending notifications
  - overdue suspension
  - cancelled-to-expired transition
  - safer tenant cleanup policy for expired trials only
- Notifications are present and tested.
- Customer portal can restart paid checkout correctly after terminal Stripe cancellation.
- Webhook-driven provisioning invocation and tenant access gating are covered by tests.
- Trial provisioning rollback and success path are covered by tests.
- Paid onboarding success path is covered from checkout start through webhook subscription bootstrap/provisioning trigger.
- Tenant admin login/access is covered end-to-end after provisioning.
- Paid plans now read real shared feature catalog data and real limits.
- Plan features are no longer ad hoc text; they are managed via a shared billing features catalog with admin CRUD.
- Admin plan form now includes a live customer-portal preview for price, limits, and selected features.
- Multi-product architecture work is already underway in code:
  - products catalog
  - plan/product linkage
  - tenant product subscriptions
  - tenant-facing read path migration
  - visible portal products catalog UI
- Additional product delivery is now materially more complete:
  - enablement request flow
  - approval-driven attach flow
  - portal checkout start for approved products
  - webhook sync for product subscriptions
  - product-scoped portal billing state messaging
- First-product onboarding is now materially closer to product-neutral behavior:
  - first paid checkout can start from a non-automotive product
  - first free trial can start from a non-automotive product when a trial plan exists
  - portal trial UI now respects the selected product instead of assuming automotive
- Public portal shell is now more product-neutral:
  - auth branding is workspace-oriented
  - customer copy no longer assumes automotive-only onboarding
  - tenant subscription read path can resolve non-automotive first-product subscriptions
- Workspace product architecture is now more configurable:
  - product-family aliases, modules, dashboard actions, and integrations can be declared through a manifest config
  - adding a future product family now needs far less hardcoded service editing
- Runtime module screens are now partially manifest-driven as well:
  - page titles, descriptions, and quick links for major runtime modules are no longer controller-hardcoded
- Route-level module access is now also closer to the manifest:
  - middleware can guard by runtime module key and derive the owning family from the manifest
- Default workspace focus and dashboard family behavior are now also less hardcoded:
  - alias-based products can drive the correct runtime experience without exact legacy product codes
- Stripe sync is now significantly safer:
  - missing subscription id recovery
  - product-subscription mirror updates
  - better cancellation-period mapping
- The project is now in a hybrid transition state:
  - central admin still largely legacy-subscription based
  - tenant/portal path is already partially product-subscription based
- The codebase has multiple isolated UI namespaces; keep them separated.
- Do not use `route:cache`.
- Always verify current files before assuming older flow descriptions are still correct.
