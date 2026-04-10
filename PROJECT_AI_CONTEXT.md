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
  - quick `Sync From Stripe` action from the table itself
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
