# Automotive SaaS - AI Working Context

## 1) What This File Is
This file is the single operational reference for new AI sessions.

It must answer these questions clearly:
- what this project is
- how the codebase is split
- what was built already
- what works right now
- what is still incomplete
- what the next package should be
- how the AI is expected to work in this repo

This file is intentionally explicit. It is not a changelog dump. It is a working brief.

## 2) Project Identity
- Project name: `Automotive SaaS`
- Stack:
  - Laravel 10
  - PHP 8.2
  - `stancl/tenancy` v3
  - multi-database tenancy
  - Stripe billing
- Branch used for deployment: `main`
- Local workspace path:
  - `/home/mohamed/Projects/automotive-saas`

The project started as an automotive-oriented SaaS, but current work has been pushing it toward a real multi-product SaaS where multiple systems can live inside the same tenant workspace.

## 3) Working Method Required In This Repo
- Always inspect the current code before changing anything.
- Never trust old summaries over current files.
- After every real code change, always provide:
  - what changed
  - how to test it in the UI
  - exact git commands:
    - `git add ...`
    - `git commit -m "..."`
    - `git push origin main`
- Always update this file after meaningful work.
- Prefer real implementation over brainstorming when the user clearly asked to "start" or "fix".

## 4) Non-Negotiable Technical Rules
- Never use:
  - `php artisan route:cache`
- Reason:
  - route resolution is dynamic across tenant/product contexts
  - route cache can break this project
- Be defensive with schema differences between environments.
- Do not assume one billing column naming convention everywhere.
  - examples encountered:
    - `currency`
    - `currency_code`
- Do not blindly refactor shared theme/layout code if an isolated automotive copy already exists.
- Do not revert user changes outside the scope of the task.

## 5) Main Application Areas

### 5.1 Central Admin
Purpose:
- manage products
- manage plans
- manage tenants
- manage subscriptions
- monitor billing
- review enablement requests
- inspect notifications/logs

Main code areas:
- `app/Http/Controllers/Admin/*`
- `resources/views/admin/*`
- `routes/web.php`
- `routes/admin/*`

### 5.2 Customer Portal
Purpose:
- public/customer-facing area
- registration/login
- onboarding
- free trial
- paid subscription checkout
- product browsing
- product expansion on same workspace
- tenant-controlled billing area

Main code areas:
- `app/Http/Controllers/Automotive/Front/*`
- `resources/views/automotive/portal/*`
- `routes/products/automotive/front.php`

### 5.3 Tenant Admin
Purpose:
- the real tenant workspace
- operational modules for subscribed products
- shared workspace modules
- runtime workflows

Main code areas:
- `app/Http/Controllers/Automotive/Admin/*`
- `resources/views/automotive/admin/*`
- `routes/products/automotive/admin.php`

## 6) Architectural Direction
The project is no longer supposed to behave like:
- one tenant = one automotive system only

The correct target direction is:
- one tenant = one shared workspace
- the tenant may subscribe to:
  - automotive service
  - spare parts / inventory
  - accounting
  - future systems later
- shared workspace modules appear once
- product modules appear only for subscribed products
- products can integrate with each other inside the same workspace

### 6.1 Shared Workspace Model
Current intended model:
- `Customer Portal` handles tenant-facing account concerns
- `Tenant Admin` handles subscribed runtime systems
- `Central Admin` handles platform operations

### 6.2 Shared vs Product Modules
Current rule:
- Shared modules:
  - `Users`
  - `Branches`
  - tenant-level billing/account controls were being moved to portal
- Product modules:
  - automotive:
    - `Workshop Operations`
    - `Work Orders`
    - `Customers`
    - `Vehicles`
  - spare parts:
    - `Supplier Catalog`
    - stock/inventory modules
  - accounting:
    - `General Ledger`

## 7) Files That Matter Most Right Now

### Portal / Front
- `app/Http/Controllers/Automotive/Front/CustomerPortalController.php`
- `app/Http/Controllers/Automotive/Front/PortalBillingController.php`
- `app/Http/Controllers/Automotive/Front/CustomerPortalNotificationController.php`
- `app/Services/Automotive/StartTrialService.php`
- `app/Services/Automotive/StartPaidCheckoutService.php`
- `app/Services/Automotive/StartAdditionalProductCheckoutService.php`
- `resources/views/automotive/portal/index.blade.php`
- `resources/views/automotive/portal/billing/status.blade.php`
- `resources/views/automotive/portal/layouts/portalLayout/partials/header.blade.php`
- `routes/products/automotive/front.php`

### Tenant Runtime
- `app/Http/Controllers/Automotive/Admin/WorkspaceModuleController.php`
- `app/Http/Controllers/Automotive/Admin/DashboardController.php`
- `app/Http/Middleware/EnsureTenantHasWorkspaceProduct.php`
- `app/Services/Tenancy/TenantWorkspaceProductService.php`
- `app/Services/Tenancy/WorkspaceProductActivationService.php`
- `app/Services/Tenancy/WorkspaceManifestService.php`
- `app/Services/Tenancy/WorkspaceModuleCatalogService.php`
- `app/Services/Tenancy/WorkspaceIntegrationCatalogService.php`
- `app/Services/Tenancy/WorkspaceProductFamilyResolver.php`
- `config/workspace_products.php`
- `resources/views/automotive/admin/modules/show.blade.php`
- `resources/views/automotive/admin/modules/work-order-show.blade.php`
- `resources/views/automotive/admin/dashboard/index.blade.php`
- `resources/views/automotive/admin/layouts/adminLayout/partials/sidebar.blade.php`

### Cross-Product / Runtime Services
- `app/Services/Automotive/WorkshopPartsIntegrationService.php`
- `app/Services/Automotive/WorkshopWorkOrderService.php`
- `app/Services/Automotive/WorkOrderAccountingHandoffService.php`
- `app/Services/Automotive/SupplierCatalogService.php`

### Central Admin / Plans / Products
- `app/Http/Controllers/Admin/ProductController.php`
- `app/Http/Controllers/Admin/PlanController.php`
- `app/Http/Controllers/Admin/ProductCapabilityController.php`
- `resources/views/admin/products/index.blade.php`
- `resources/views/admin/plans/_form.blade.php`
- `resources/views/admin/plans/index.blade.php`
- `resources/views/admin/product-capabilities/*`

### Billing / Multi-Product Billing
- `app/Http/Controllers/Automotive/Admin/BillingController.php`
- `app/Services/Tenancy/TenantPlanService.php`
- `app/Services/Tenancy/TenantSubscriptionService.php`
- `app/Services/Billing/StripeWebhookSyncService.php`
- `app/Services/Billing/StripePaymentMethodManagementService.php`
- `app/Services/Billing/StripeTenantProductSubscriptionPlanChangeService.php`
- `app/Services/Billing/AdminTenantProductSubscriptionStripeSyncService.php`

## 8) What Is Already Built

### 8.1 Central Admin Foundation
Confirmed in code:
- products CRUD
- plans CRUD
- coupons CRUD
- subscriptions operations
- tenants management
- billing reports
- admin notifications
- activity logs
- system error logs
- SaaS settings
- product enablement requests

### 8.2 Plan / Product Model
Confirmed:
- plans are product-aware
- admin plan form supports:
  - product selection
  - billing features
  - limits
  - live preview
  - trial days
- products index links to product-specific plans
- products index links to product capabilities

### 8.3 Product Capabilities Foundation
Confirmed:
- `product_capabilities` exists
- central admin can CRUD capabilities per product
- default seeder creates one starter capability per product family
- capabilities appear in portal and workspace context

Main files:
- `app/Models/ProductCapability.php`
- `app/Http/Controllers/Admin/ProductCapabilityController.php`
- `database/seeders/ProductCapabilitiesSeeder.php`

### 8.4 Seeders
Current seed behavior:
- all current products are active by default
- each current product gets plans
- plans are not all identical anymore
- each product also gets a trial plan

Current seeded product families:
- `automotive_service`
- `parts_inventory`
- `accounting`

Important files:
- `database/seeders/ProductSeeder.php`
- `database/seeders/PlanSeeder.php`
- `database/seeders/ProductCapabilitiesSeeder.php`

### 8.5 Portal Product Selection and Subscription Behavior
Confirmed:
- portal no longer assumes `automotive` as the only first-class product
- first paid subscription can start from a non-automotive product
- first free trial can start from a non-automotive product
- trial CTA only appears for the explicitly selected product
- trial length is controlled by the selected plan's `trial_days`
- portal no longer blindly defaults to automotive after refresh
- the last explicit portal product focus is remembered in session
- if a customer has multiple products and no explicit focus, the portal stays neutral and asks the user to choose a product

Important files:
- `app/Http/Controllers/Automotive/Front/CustomerPortalController.php`
- `app/Services/Automotive/StartTrialService.php`
- `resources/views/automotive/portal/index.blade.php`

### 8.6 Portal Catalog CTA Logic
This was recently corrected.

Current correct behavior:
- `Browse Product Plans` is shown only when direct checkout is a valid next step
- for additional products on an existing workspace, unsubscribed non-primary products now show:
  - `Explore Enablement`
- this avoids the old contradiction:
  - card said `Browse Product Plans`
  - plan panel said `Approval Required Before Checkout`

### 8.7 Enablement Workflow
Confirmed flow:
- customer can request enablement for extra products
- admin receives notification
- admin can review list and details page
- admin can approve/reject
- admin can add decision notes
- approval attaches or reactivates `tenant_product_subscription`
- portal customer receives decision notification

Main files:
- `app/Http/Controllers/Admin/ProductEnablementRequestController.php`
- `app/Services/Admin/ProductEnablementApprovalService.php`
- `resources/views/admin/product-enablement-requests/*`

### 8.8 Notifications
Confirmed:
- admin notifications are live via SSE
- customer portal notifications are live via SSE
- portal header has notifications dropdown
- enablement workflow uses notifications for both sides appropriately

### 8.9 Tenant Product Subscriptions
Confirmed:
- `tenant_product_subscriptions` is a first-class concept now
- central admin has:
  - index
  - filters
  - details page
  - diagnostics
  - stripe sync action
  - bulk sync
  - queue-backed sync for filtered/retry flows
  - CSV export
- sync metadata exists on the record:
  - `last_synced_from_stripe_at`
  - `last_sync_status`
  - `last_sync_error`

### 8.10 Stripe / Billing Sync
Confirmed:
- webhook sync supports product-subscription flows
- checkout completion can update `tenant_product_subscriptions`
- lifecycle sync exists for:
  - `invoice.paid`
  - `invoice.payment_failed`
  - `customer.subscription.updated`
  - `customer.subscription.deleted`
- recovery was added for missing/inactive Stripe price state during checkout

### 8.11 Portal-Owned Billing
This is an important architectural shift.

Current direction:
- tenant billing controls are being moved out of tenant admin into the customer portal
- the portal now has billing pages and product-scoped billing controls
- tenant admin billing is no longer the intended long-term home for tenant account management

Portal billing currently supports:
- invoice history
- plan change
- renew
- cancel / resume
- payment method updates
- Stripe portal access
- product-scoped billing context

Main files:
- `app/Http/Controllers/Automotive/Front/PortalBillingController.php`
- `resources/views/automotive/portal/billing/status.blade.php`

### 8.12 Workspace Manifest Foundation
This is one of the most important architectural changes.

Current state:
- workspace families, modules, aliases, quick links, integrations, and runtime metadata are being moved into:
  - `config/workspace_products.php`
- supporting services read from that manifest instead of hardcoded `match` blocks

Main files:
- `config/workspace_products.php`
- `app/Services/Tenancy/WorkspaceManifestService.php`
- `app/Services/Tenancy/WorkspaceProductFamilyResolver.php`
- `app/Services/Tenancy/WorkspaceModuleCatalogService.php`
- `app/Services/Tenancy/WorkspaceIntegrationCatalogService.php`

Why this matters:
- adding future product families should require less service-level hardcoding

### 8.13 Shared Workspace Module Reorganization
Recent major shift:
- `Users`, `Branches`, and similar shared concerns should not be duplicated under every product
- tenant runtime navigation was reorganized into:
  - shared/core modules
  - product modules
- product-family inference was added
- cross-product integration cards were added to workspace runtime screens

### 8.14 Automotive Runtime: Real Workflow Exists
Automotive is currently the most mature runtime family.

Confirmed runtime pieces:
- `Workshop Operations`
- `Customers`
- `Vehicles`
- `Work Orders`
- parts consumption from spare parts
- lifecycle:
  - `open`
  - `in_progress`
  - `completed`
- work order lines:
  - labor / service lines
  - parts lines
- financial summary
- local accounting handoff event on completed work order

Main files:
- `app/Models/WorkOrder.php`
- `app/Models/WorkOrderLine.php`
- `app/Models/Customer.php`
- `app/Models/Vehicle.php`
- `app/Models/AccountingEvent.php`
- `app/Services/Automotive/WorkshopWorkOrderService.php`
- `app/Services/Automotive/WorkshopPartsIntegrationService.php`
- `app/Services/Automotive/WorkOrderAccountingHandoffService.php`

Tenant migrations:
- `database/migrations/tenant/2026_04_11_160000_create_work_orders_table.php`
- `database/migrations/tenant/2026_04_11_170000_create_vehicles_table.php`
- `database/migrations/tenant/2026_04_11_171000_add_customer_and_vehicle_to_work_orders_table.php`
- `database/migrations/tenant/2026_04_11_180000_create_work_order_lines_table.php`
- `database/migrations/tenant/2026_04_11_190000_create_accounting_events_table.php`

### 8.15 Spare Parts Runtime
Spare parts is no longer just metadata.

Confirmed:
- supplier catalog runtime module now exists
- spare parts inventory is already the natural owner of:
  - stock items
  - inventory adjustments
  - stock transfers
  - inventory reports

Main files:
- `app/Models/Supplier.php`
- `app/Services/Automotive/SupplierCatalogService.php`
- `database/migrations/tenant/2026_04_12_120000_create_suppliers_table.php`

### 8.16 Accounting Runtime
Accounting exists as a family and runtime entry, but it is less mature than automotive.

Current confirmed pieces:
- `General Ledger` module entry exists
- local accounting handoff events exist from completed work orders

What is still missing:
- true journal-style runtime depth
- posting groups / richer accounting tables
- deeper end-to-end accounting workflows

## 9) What Works Right Now

### 9.1 Standalone Product Entry
Current answer:
- a customer can now start from a non-automotive product
- portal first-subscription flow is product-aware
- trial flow is product-aware
- paid flow is product-aware

### 9.2 Standalone Spare Parts Use Case
Current practical answer:
- yes, `Spare Parts / Inventory` can now be used as a standalone starting product for current inventory-oriented workflows
- it does not require the customer to also subscribe to automotive first

However:
- "fully complete for all possible future business use" is too strong
- the architecture is now suitable
- but each future system still needs its own runtime depth and integrations

### 9.3 Adding More Products Later
Current state:
- much easier than before
- not fully automatic yet

What already helps:
- product-aware plans
- product capabilities
- tenant product subscriptions
- workspace manifest
- product family resolver
- shared vs product module split

What still limits full plug-and-play:
- some runtime behavior is still family-specific
- future systems still need:
  - manifest entries
  - runtime pages/controllers
  - domain rules
  - integration rules

## 10) Gaps Still Open

### 10.1 Portal / Tenant Boundary
Billing is already moving into portal.

Still desirable:
- move tenant-owned profile/settings fully into portal
- keep tenant admin runtime-only

### 10.2 Accounting Depth
Accounting is not yet as mature as automotive.

Still missing:
- richer accounting runtime screens
- deeper posting model
- more complete accounting workflows

### 10.3 Future Product Plug-In Model
The manifest foundation exists, but future products are not yet "zero-code".

Still needed over time:
- stronger product/module manifest model
- cleaner policy/permission pattern
- more config-driven runtime assembly

## 11) Testing State
Important active test areas:
- `tests/Feature/Automotive/Portal/*`
- `tests/Feature/Automotive/Admin/*`
- `tests/Feature/Tenancy/*`
- `tests/Feature/Admin/*`
- `tests/Feature/Billing/*`

Frequently used tests during recent work:
- `tests/Feature/Automotive/Portal/CustomerPortalBillingOptionsTest.php`
- `tests/Feature/Automotive/Portal/CustomerPortalSettingsTest.php`
- `tests/Feature/Automotive/Portal/StartTrialServiceTest.php`
- `tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`
- `tests/Feature/Automotive/Admin/BillingPageTest.php`
- `tests/Feature/Tenancy/TenantSubscriptionReadPathTest.php`
- `tests/Feature/Tenancy/WorkspaceManifestServiceTest.php`
- `tests/Feature/Admin/PlanSeederTest.php`
- `tests/Feature/Billing/ProductPlanCatalogBootstrapTest.php`

## 12) Known Cautions
- `resources/views/automotive/admin/billing/partials/plan-selector.blade.php`
  - this file has had separate edits in the working tree before
  - do not assume it belongs to the current task unless explicitly reviewed
- temporary tenant sqlite files may appear during tests
- do not commit generated tenant DB files

## 13) UI Behavior That Is Intentionally Correct Now
- portal no longer defaults to automotive on every refresh
- portal catalog CTA now matches real next step:
  - direct checkout only when direct checkout is actually valid
  - otherwise enablement path
- trial CTA belongs to the explicitly selected product
- plans are no longer seeded with identical pricing across all products
- seeded products are all active by default
- portal now owns tenant-facing profile/settings management via:
  - `GET /automotive/portal/settings`
  - `PUT /automotive/portal/settings/profile`
  - `PUT /automotive/portal/settings/security`
- portal settings now centralize:
  - tenant account profile editing
  - company/workspace name editing
  - portal password change
  - domain/subdomain snapshot visibility
  - linked workspace directory snapshot
- portal profile changes now sync linked tenant snapshot fields:
  - `company_name`
  - `owner_email`
- portal navigation/header now expose `Account & Settings` directly
- when a workspace already exists, non-automotive product cards that have active paid plans now show:
  - `Browse Product Plans`
  - instead of incorrectly defaulting to `Explore Enablement`
- product card CTA availability must be derived by canonical `product code`, not only raw `product_id` counts:
  - the catalog card and the paid-plan panel must use the same billing-catalog source
  - subscribing to `Accounting` must not cause `Parts Inventory Management` to fall back to `Explore Enablement`
- additional products with active paid plans are now direct-billed in the portal:
  - they must show `Select & Continue` instead of `Approval Required Before Checkout`
  - enablement workflow remains only for products that do not yet have direct paid checkout configured
- portal must distinguish between:
  - the primary/default product subscription (`automotive_service`)
  - any other workspace product subscription
- a legacy `subscriptions` row for `Accounting` or another non-primary product must not be treated as the automotive/default subscription:
  - it must not make `Automotive Service Management` appear live-billed
  - it must not hide paid plans for other products
  - it must not re-enable first-workspace trial UI once a workspace already exists
- `StartPaidCheckoutService` must scope its “live paid subscription already exists” guard to the primary/default product only:
  - a live non-automotive legacy subscription must not block starting automotive checkout later
  - when automotive checkout starts after another product was first, it should create its own primary legacy subscription row for the same tenant and continue to Stripe
- portal billing gates are now product-scoped:
  - a live Stripe subscription on one product must not block paid plans for a different product
  - example: active `Accounting` billing must not block `Automotive` plan selection
- portal base route is now intentionally neutral:
  - opening `/automotive/portal` without an explicit `?product=...` must not auto-focus any product
  - the portal must not remember a previous product selection on the base route
  - `Product Subscription Options` stays hidden until the customer explicitly chooses a product
  - generic portal CTAs now point to `Products Catalog` first when no product is selected
- tenant admin billing surface is now decommissioned into a transition page:
  - `automotive/admin/billing` remains only as a runtime-access landing page
  - it no longer presents billing/account ownership as a tenant-admin responsibility
  - billing mutations inside tenant admin now redirect back with a portal handoff message
  - payment method JSON endpoints now return `410` with a portal-only message
- tenant admin header/breadcrumb wording now uses `Subscription Access` instead of `Plans & Billing`

## 14) Current Strong Summary
If a new session starts and reads only this section, the safe understanding is:

- This is now a multi-product SaaS in transition from automotive-first to product-neutral.
- The portal is becoming the home of tenant-facing account management.
- Tenant admin is becoming the home of runtime modules only.
- Tenant-admin billing now exists only as a transition/access state surface, not as the billing control plane.
- Automotive runtime is the most mature product family.
- Spare parts is already viable as its own runtime family for current inventory-related workflows.
- Accounting exists architecturally but still needs deeper runtime implementation.
- Product families, modules, and integrations are being moved into a workspace manifest.
- Cross-product integration has already started for real:
  - workshop can consume spare parts
  - completed work orders can generate local accounting handoff events

## 15) Recently Completed Package
This package was completed in the current work cycle:

### Provisioning And Activation Flow
Status:
- completed

Scope:
- complete the post-checkout/post-approval path from "product is paid/approved" to "product is active and usable in the tenant workspace"
- make product activation explicit, observable, and safe for every product family
- create or update workspace product activation records after:
  - Stripe checkout success/webhook sync
  - product enablement approval
  - central admin manual activation where needed
- expose provisioning status clearly in:
  - customer portal
  - central admin product/subscription context
  - tenant workspace access decisions
- ensure product runtime visibility depends on activation state, not only on plan/payment metadata

Why this was needed:
- product lifecycle UI now exists through:
  - product builder
  - capabilities
  - plans
  - workspace experience
  - runtime modules
  - integrations
  - portal publication
  - manifest sync/writeback package
  - apply queue
  - lifecycle validation/audit
- runtime wiring can now consume approved writeback packages
- integrations now render inside tenant runtime
- the remaining gap is the activation/provisioning layer that turns billing/approval outcomes into reliable workspace availability
- this prevents cases where payment succeeds but the portal still says workspace access is finalizing forever

Files inspected/used:
- `app/Http/Controllers/Automotive/Front/CustomerPortalController.php`
- `app/Http/Controllers/Automotive/Webhooks/StripeWebhookController.php`
- `app/Services/Automotive/ProvisionTenantWorkspaceService.php`
- `app/Services/Automotive/StartAdditionalProductCheckoutService.php`
- `app/Services/Automotive/StartPaidCheckoutService.php`
- `app/Services/Tenancy/TenantWorkspaceProductService.php`
- `app/Http/Controllers/Admin/ProductEnablementRequestController.php`
- `app/Http/Controllers/Admin/SubscriptionController.php`
- `resources/views/automotive/portal/index.blade.php`
- `resources/views/admin/subscriptions/*`
- `tests/Feature/Automotive/Portal/CustomerPortalBillingOptionsTest.php`
- `tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`

Acceptance criteria:
- after a successful direct paid checkout, the relevant product becomes visible and usable in the tenant workspace without manual database repair
- after enablement approval, the approved product becomes an active workspace product with a clear activation state
- portal must show a deterministic status:
  - payment pending webhook
  - provisioning in progress
  - active and ready
  - provisioning failed with admin-facing diagnostic
- tenant workspace runtime must only show products/modules whose activation state allows runtime access
- activation must be product-scoped, so activating Accounting must not falsely activate Automotive or Parts Inventory
- tests must cover at least:
  - first paid product activation
  - additional paid product activation
  - enablement approval activation
  - failed/missing provisioning status in portal
  - tenant runtime visibility based on activation state

Completed behavior:
- `tenant_product_subscriptions` now carries explicit activation/provisioning state:
  - `activation_status`
  - `provisioning_status`
  - `provisioning_started_at`
  - `provisioning_completed_at`
  - `provisioning_failed_at`
  - `activated_at`
  - `activation_error`
  - `activation_source`
- successful Stripe checkout completion now marks the product subscription as provisioning, provisions the tenant workspace, then marks the product active
- failed provisioning is recorded on the product subscription instead of silently leaving the portal in an ambiguous state
- additional direct-billed products use the same activation path as first paid products
- product enablement approval marks the attached product subscription active and provisioned
- tenant runtime module access now requires product activation/provisioning state to allow runtime access, not billing status alone
- customer portal product cards and selected product panels show deterministic provisioning states:
  - `Payment Pending Webhook`
  - `Provisioning In Progress`
  - `Active And Ready`
  - `Provisioning Failed`
- central admin tenant/product subscription screens expose activation/provisioning state and diagnostics

Important files added/changed:
- `database/migrations/2026_04_19_054718_add_activation_state_to_tenant_product_subscriptions_table.php`
- `app/Services/Tenancy/WorkspaceProductActivationService.php`
- `app/Services/Tenancy/TenantWorkspaceProductService.php`
- `app/Services/Billing/StripeWebhookSyncService.php`
- `app/Services/Admin/ProductEnablementApprovalService.php`
- `app/Services/Automotive/StartPaidCheckoutService.php`
- `app/Services/Automotive/StartAdditionalProductCheckoutService.php`
- `app/Http/Controllers/Automotive/Front/CustomerPortalController.php`
- `resources/views/automotive/portal/index.blade.php`
- `resources/views/admin/tenants/product-subscription-show.blade.php`
- `resources/views/admin/tenants/product-subscriptions.blade.php`
- `resources/views/admin/tenants/show.blade.php`
- `tests/Feature/Billing/StripeWebhookSyncServiceTest.php`
- `tests/Feature/Admin/ProductEnablementRequestsIndexTest.php`
- `tests/Feature/Automotive/Portal/CustomerPortalBillingOptionsTest.php`
- `tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`

Verification:
- default `php artisan test ...` attempted but the local default database connection tried to reach unavailable MySQL database `automotive_local`
- targeted suite passed with SQLite override:
  - `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Billing/StripeWebhookSyncServiceTest.php tests/Feature/Admin/ProductEnablementRequestsIndexTest.php tests/Feature/Automotive/Portal/CustomerPortalBillingOptionsTest.php tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`
  - result: 63 passed, 428 assertions

## 15.1) Recently Completed Package
This package was completed after the provisioning/activation package:

### Accounting Runtime Depth
Status:
- completed

Completed scope:
- deepen Accounting Runtime beyond the current General Ledger entry and accounting handoff events
- journal entry runtime screens
- posting groups
- accounting event review/posting workflow
- tests for accounting workspace access and work-order handoff review

Current behavior:
- tenant accounting runtime now has real tenant tables for:
  - `accounting_posting_groups`
  - `journal_entries`
  - `journal_entry_lines`
- General Ledger can now:
  - show posting group count
  - show accounting events waiting for journal posting
  - show recent journal entries and their debit/credit lines
  - create posting groups from the runtime UI
  - post an accounting event into a balanced journal entry
- posting a workshop accounting event creates:
  - debit line to receivables
  - credit line to labor revenue when labor amount exists
  - credit line to parts revenue when parts amount exists
  - balanced debit/credit totals
- accounting events keep the existing handoff behavior and move to `journal_posted` after journal posting

Important files added/changed:
- `database/migrations/tenant/2026_04_19_171447_create_accounting_runtime_tables.php`
- `app/Models/AccountingPostingGroup.php`
- `app/Models/JournalEntry.php`
- `app/Models/JournalEntryLine.php`
- `app/Services/Automotive/AccountingRuntimeService.php`
- `app/Http/Controllers/Automotive/Admin/WorkspaceModuleController.php`
- `resources/views/automotive/admin/modules/show.blade.php`
- `routes/products/automotive/admin.php`
- `tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`

Verification:
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter=accounting_runtime`
  - result: 1 passed, 26 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`
  - result: 12 passed, 196 assertions

## 15.2) Recommended Next Package
When a new AI session starts from this file, the next package to start immediately is:

### Accounting Runtime Hardening And Exports
Recommended scope:
- add CSV/PDF export for journal entries, trial balance, and revenue summary
- add stronger account catalog controls instead of free-text accounts only
- add period close/lock guards for journal posting and reversal
- add configurable inventory posting accounts per product/accounting policy
- add audit timeline for journal posting, manual journal creation, and reversals

## 15.3) Spare Parts Stock Item Model Correction
Status:
- completed

Problem fixed:
- tenant `Stock Items` screen was showing central SaaS products such as:
  - `Accounting System`
  - `Automotive Service Management`
  - `Parts Inventory Management`
- root cause:
  - `App\Models\Product` is intentionally bound to the central connection for SaaS product families
  - tenant spare-parts stock also uses a tenant table named `products`
  - automotive admin inventory screens were using the central `Product` model instead of a tenant stock-item model

Current behavior:
- tenant spare-parts stock now uses `App\Models\StockItem`
- inventory relationships now point to tenant stock items:
  - `Inventory`
  - `StockMovement`
  - `StockTransferItem`
  - `WorkOrderLine`
- Stock Items, inventory adjustment, stock transfer, stock movement filters, and parts dashboard counts now read tenant stock items
- central SaaS product catalog remains on `App\Models\Product`

Demo data:
- added `Database\Seeders\TenantSparePartsDemoSeeder`
- seeds demo tenant stock items and opening inventory into the tenant database:
  - `Engine Oil 5W-30`
  - `Oil Filter Toyota`
  - `Front Brake Pads Set`
  - `Air Filter Generic`
- also creates `Main Branch` when missing

Seeder command:
- for one tenant:
  - `php artisan tenants:seed --class=Database\\Seeders\\TenantSparePartsDemoSeeder --tenants=TENANT_ID`
- production should include:
  - `--force`

Important files:
- `app/Models/StockItem.php`
- `app/Models/Inventory.php`
- `app/Models/StockMovement.php`
- `app/Models/StockTransferItem.php`
- `app/Models/WorkOrderLine.php`
- `app/Http/Controllers/Automotive/Admin/ProductController.php`
- `app/Http/Controllers/Automotive/Admin/InventoryAdjustmentController.php`
- `app/Http/Controllers/Automotive/Admin/StockTransferController.php`
- `app/Http/Controllers/Automotive/Admin/StockMovementReportController.php`
- `app/Http/Controllers/Automotive/Admin/DashboardController.php`
- `database/seeders/TenantSparePartsDemoSeeder.php`
- `tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`

Verification:
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter=stock_items_use_tenant_spare_parts`
  - result: 1 passed, 12 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`
  - result: 13 passed, 208 assertions

## 15.4) Accounting Runtime Expansion
Status:
- completed

Completed scope:
- journal entry detail pages
- journal entry filters by status, date range, and search term
- journal reversal workflow
- manual journal entry creation
- accounting reports inside General Ledger:
  - Trial Balance
  - Revenue Summary
- inventory valuation review/posting from Spare Parts stock movements into accounting journals

Current behavior:
- General Ledger now shows journal filters and uses them for:
  - journal list
  - trial balance
  - revenue summary
- manual journal entries can be posted from General Ledger when debit and credit totals balance
- each journal entry has a detail page showing:
  - status
  - date
  - totals
  - source
  - posting group
  - journal lines
- posted non-reversal journals can be reversed from the detail page
- reversal creates a new `REV-*` journal entry with debit/credit lines swapped and marks the original journal as `reversed`
- Spare Parts inventory movements with positive valuation can be reviewed from General Ledger and posted into accounting:
  - `opening` and `adjustment_in` debit `1300 Inventory Asset` and credit `3900 Inventory Adjustment Offset`
  - `adjustment_out` credits `1300 Inventory Asset`
  - workshop-linked `adjustment_out` debits `5000 Cost Of Goods Sold`
  - non-workshop `adjustment_out` debits `5100 Inventory Adjustment Expense`

Important files changed:
- `app/Services/Automotive/AccountingRuntimeService.php`
- `app/Http/Controllers/Automotive/Admin/WorkspaceModuleController.php`
- `routes/products/automotive/admin.php`
- `resources/views/automotive/admin/modules/show.blade.php`
- `resources/views/automotive/admin/modules/journal-entry-show.blade.php`
- `tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`

Verification:
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter=accounting_runtime`
  - result: 3 passed, 66 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`
  - result: 15 passed, 248 assertions
- syntax checks passed for:
  - `app/Services/Automotive/AccountingRuntimeService.php`
  - `app/Http/Controllers/Automotive/Admin/WorkspaceModuleController.php`
  - `routes/products/automotive/admin.php`
  - `tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`

## 15.5) Workspace Integration Contract Hardening
Status:
- completed

Why this package was needed:
- the current product integrations were functionally working, but they were still too service-specific
- before adding new systems, the workspace needed a reusable integration contract layer so a future product can declare:
  - what events it emits
  - what target product/family it integrates with
  - what capabilities are required
  - what payload shape is expected
  - how handoff status is tracked and diagnosed

Completed scope:
- added a tenant-level `workspace_integration_handoffs` table to record integration attempts
- added a reusable `WorkspaceIntegrationHandoff` model
- added `WorkspaceIntegrationContractService` to read declared integration contracts from `config/workspace_products.php`
- added `WorkspaceIntegrationHandoffService` for:
  - idempotency key generation
  - pending/posted/skipped/failed statuses
  - attempt count
  - source and target references
  - diagnostic error messages
- extended manifest integration definitions with:
  - event names
  - source capabilities
  - target capabilities
  - payload schema hints
- work-order completion now records an `automotive-accounting` handoff for `work_order.completed`
- if Accounting is not active, work-order completion records a `skipped` handoff instead of failing silently
- inventory valuation posting now records a `parts-accounting` handoff for `stock_movement.valued`
- General Ledger now exposes:
  - Integration Contracts
  - Integration Handoff Diagnostics

Current integration contracts:
- `automotive-parts`
  - event: `work_order.consume_part`
  - source: `automotive_service`
  - target: `parts_inventory`
- `automotive-accounting`
  - event: `work_order.completed`
  - source: `automotive_service`
  - target: `accounting`
- `parts-accounting`
  - event: `stock_movement.valued`
  - source: `parts_inventory`
  - target: `accounting`

Important files added/changed:
- `database/migrations/tenant/2026_04_21_010000_create_workspace_integration_handoffs_table.php`
- `app/Models/WorkspaceIntegrationHandoff.php`
- `app/Services/Tenancy/WorkspaceIntegrationContractService.php`
- `app/Services/Tenancy/WorkspaceIntegrationHandoffService.php`
- `app/Services/Automotive/WorkOrderAccountingHandoffService.php`
- `app/Services/Automotive/AccountingRuntimeService.php`
- `app/Http/Controllers/Automotive/Admin/WorkspaceModuleController.php`
- `config/workspace_products.php`
- `resources/views/automotive/admin/modules/show.blade.php`
- `tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`

Verification:
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter='accounting_runtime|skipped_handoff|workshop_operations_can_create_work_order'`
  - result: 5 passed, 158 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`
  - result: 16 passed, 272 assertions

Integration phase status:
- the foundational linking layer is now closed enough to safely onboard future systems through declared contracts instead of invisible custom service coupling
- next integration work should focus on governance and admin validation, not adding product-specific runtime features

## 15.6) Integration Contract Governance And Product Onboarding
Status:
- completed

Why this package was needed:
- after adding the tenant handoff log and runtime integration contracts, new products still needed central-admin governance before being approved for manifest sync/apply
- the goal was to prevent future products from entering the runtime with vague integration notes that cannot be safely connected to existing systems

Completed scope:
- added `ProductIntegrationGovernanceService`
- central product integration drafts now capture structured contract fields:
  - events
  - source capabilities
  - target capabilities
  - payload schema
- integration drafts can still describe navigation, but manifest approval now validates runtime contract readiness
- manifest sync approval is blocked when:
  - integration intent exists in the experience draft but no structured contract exists
  - a contract has no stable key
  - a contract has no target product
  - the target product does not exist
  - a contract has no event names
- manifest sync preview now shows an `Integration Governance` panel with:
  - contract count
  - event count
  - blocker count
  - warning count
  - contract/event details
- manifest apply queue now shows integration governance readiness before execution
- generated manifest payload now includes contract fields under `integrations`
- failed/skipped tenant integration handoffs can now be retried from General Ledger diagnostics
- retry handlers currently cover:
  - `automotive-accounting` / `work_order.completed`
  - `parts-accounting` / `stock_movement.valued`

Current behavior:
- a new product can no longer be approved for manifest sync if it declares integration intent without executable contract data
- central admin can see why the product is blocked before writeback/apply
- tenant admins can retry failed/skipped integration handoffs after fixing the missing target product activation or runtime issue

Important files added/changed:
- `app/Services/Admin/ProductIntegrationGovernanceService.php`
- `app/Services/Admin/ProductLifecycleService.php`
- `app/Http/Controllers/Admin/ProductIntegrationController.php`
- `app/Http/Controllers/Admin/ProductManifestSyncController.php`
- `app/Http/Controllers/Admin/ProductManifestApplyQueueController.php`
- `resources/views/admin/products/integrations.blade.php`
- `resources/views/admin/products/manifest-sync.blade.php`
- `resources/views/admin/products/manifest-apply-queue.blade.php`
- `app/Http/Controllers/Automotive/Admin/WorkspaceModuleController.php`
- `resources/views/automotive/admin/modules/show.blade.php`
- `routes/products/automotive/admin.php`
- `tests/Feature/Admin/ProductCrudTest.php`
- `tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`

Verification:
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Admin/ProductCrudTest.php --filter='integration|manifest_sync|manifest_apply'`
  - result: 8 passed, 90 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter='skipped_handoff|workshop_operations_can_create_work_order|accounting_runtime'`
  - result: 5 passed, 166 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Admin/ProductCrudTest.php tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`
  - result: 32 passed, 441 assertions

## 16) How Future AI Sessions Should Work
When starting from this file:
1. read this file first
2. inspect the real files that match the requested package
3. do not re-analyze the whole repo unless necessary
4. continue from `Recommended Next Package` unless the user gives a different direction
5. after finishing:
   - update this file
   - give UI test steps
   - give exact git commands

## 17) Workspace URL Canonicalization
Status:
- completed
- canonical public and tenant runtime URLs now use `/workspace`
- legacy `/automotive/*` paths are preserved as compatibility aliases where needed

Current rules:
- customer portal and auth named routes now generate `/workspace/*`
- tenant entry is `/workspace`
- tenant runtime admin routes are `/workspace/admin/*`
- legacy `/automotive/portal`, `/automotive/login`, and `/automotive/admin/*` paths still resolve
- legacy tenant-admin billing path redirects to canonical `/workspace/admin/billing`
- trial bootstrap API is canonical at `/api/workspace/start-trial` with legacy `/api/automotive/start-trial` preserved
- product cards in the shared portal must render one primary CTA only; subscribed automotive uses `Open Product Workspace`, subscribed attached products use `Manage Product`

Guardrail:
- any new controller redirect, builder, or Blade link should use named routes or the workspace URL builder
- do not add new hard-coded `/automotive/...` URLs except explicit legacy aliases

## 18) Central Admin Product Builder UI
Status:
- started
- the central admin product catalog now has a `Product Builder` page that acts as the first UI lifecycle surface for new systems/products

Current behavior:
- creating a product now redirects to the product builder instead of only returning to the index
- updating a product now returns to the builder as well
- the builder page shows:
  - readiness checklist
  - lifecycle snapshot
  - plan/capability/subscription counts
  - workspace manifest mapping status
  - next-step actions for capabilities and plans
- `Add Plan` from the builder preselects the product in the central plan form

Important files:
- `app/Http/Controllers/Admin/ProductController.php`
- `app/Http/Controllers/Admin/PlanController.php`
- `resources/views/admin/products/show.blade.php`
- `resources/views/admin/products/index.blade.php`
- `routes/web.php`
- `tests/Feature/Admin/ProductCrudTest.php`

## 19) Workspace Experience Builder UI
Status:
- started
- each central product can now store a workspace experience draft from the admin UI

Current behavior:
- `Product Builder` links to a dedicated `Workspace Experience Builder`
- the experience draft is stored centrally in `app_settings` as JSON under `workspace_products.experience.{product_code}`
- the draft currently captures:
  - family key
  - aliases
  - portal copy
  - sidebar title
  - dashboard action ideas
  - runtime module ideas
  - integration ideas
  - implementation notes
- `Product Builder` now reflects whether an experience draft exists

Important files:
- `app/Http/Controllers/Admin/ProductExperienceController.php`
- `resources/views/admin/products/experience.blade.php`
- `app/Http/Controllers/Admin/ProductController.php`
- `resources/views/admin/products/show.blade.php`
- `tests/Feature/Admin/ProductCrudTest.php`

## 20) Portal Publication Checklist UI
Status:
- started
- central admin now has a dedicated portal publication screen for each product

Current behavior:
- each product has a `Portal Publication Checklist` screen linked from `Product Builder`
- the screen shows:
  - publication blockers
  - readiness status
  - portal card preview
  - active plan preview
- central admin can now:
  - publish a ready product to the customer portal
  - hide a product from the customer portal
- publication currently requires:
  - at least one active capability
  - at least one active plan
  - a saved workspace experience draft

Important files:
- `app/Http/Controllers/Admin/ProductPortalPublicationController.php`
- `resources/views/admin/products/portal-publication.blade.php`
- `routes/web.php`
- `resources/views/admin/products/show.blade.php`
- `tests/Feature/Admin/ProductCrudTest.php`

## 21) Runtime Module Builder UI
Status:
- started
- each product can now store structured runtime module drafts from the central admin UI

Current behavior:
- `Product Builder` now links to a dedicated `Runtime Module Builder`
- runtime modules are stored centrally in `app_settings` as JSON under `workspace_products.runtime_modules.{product_code}`
- each module draft currently captures:
  - key
  - title
  - focus code
  - route slug
  - icon
  - description
- `Product Builder` reflects the current runtime module draft count

Important files:
- `app/Http/Controllers/Admin/ProductRuntimeModuleController.php`
- `resources/views/admin/products/runtime-modules.blade.php`
- `app/Http/Controllers/Admin/ProductController.php`
- `resources/views/admin/products/show.blade.php`
- `tests/Feature/Admin/ProductCrudTest.php`

## 22) Integration Builder UI
Status:
- started
- each product can now store structured cross-product integration drafts from the central admin UI

Current behavior:
- `Product Builder` now links to a dedicated `Integration Builder`
- integrations are stored centrally in `app_settings` as JSON under `workspace_products.integrations.{product_code}`
- each integration draft currently captures:
  - key
  - target product code
  - title
  - description
  - target label
  - target route slug
- `Product Builder` reflects the current integration draft count

Important files:
- `app/Http/Controllers/Admin/ProductIntegrationController.php`
- `resources/views/admin/products/integrations.blade.php`
- `app/Http/Controllers/Admin/ProductController.php`
- `resources/views/admin/products/show.blade.php`
- `tests/Feature/Admin/ProductCrudTest.php`

## 23) Manifest Sync Preview UI
Status:
- started
- product drafts can now be reviewed as a derived workspace manifest payload from the central admin UI

Current behavior:
- `Product Builder` now links to `Manifest Sync Preview`
- the preview combines:
  - experience draft
  - runtime module draft
  - integration draft
- the screen shows:
  - sync checklist
  - target family key
  - derived manifest payload using `var_export`
- the screen now also stores workflow state:
  - `draft`
  - `approved`
  - `applied`
- workflow notes and last reviewed timestamp are stored centrally in `app_settings`
- approved/applied workflow changes now also capture a payload snapshot in `app_settings`
- export routes are now available for:
  - JSON payload
  - PHP payload
  - family snippet payload
- the manifest screen now also includes a `Code Writeback Assistant` section with:
  - writeback checklist
  - patch outline
  - suggested git commands
- this is still a review/sync-prep screen, not a write-back editor for `config/workspace_products.php`

Important files:
- `app/Http/Controllers/Admin/ProductManifestSyncController.php`
- `resources/views/admin/products/manifest-sync.blade.php`
- `routes/web.php`
- `resources/views/admin/products/show.blade.php`
- `tests/Feature/Admin/ProductCrudTest.php`

## 24) Manifest Apply Queue UI
Status:
- started
- approved manifest payloads can now move into a tracked execution queue from the central admin UI

Current behavior:
- `Product Builder` now links to a dedicated `Manifest Apply Queue`
- the queue is stored centrally in `app_settings` as JSON under `workspace_products.manifest_apply_queue.{product_code}`
- the queue currently tracks:
  - status
    - `queued`
    - `in_progress`
    - `blocked`
    - `done`
  - owner name
  - owner contact
  - blocking reason
  - implementation notes
  - deployment notes
  - queued/start/completed timestamps
- the queue screen also surfaces:
  - manifest workflow status
  - latest approved snapshot
  - readiness checks before code/runtime execution
- `Manifest Sync Preview` now links directly into this queue once review is complete

Important files:
- `app/Http/Controllers/Admin/ProductManifestApplyQueueController.php`
- `resources/views/admin/products/manifest-apply-queue.blade.php`
- `app/Http/Controllers/Admin/ProductController.php`
- `app/Http/Controllers/Admin/ProductManifestSyncController.php`
- `resources/views/admin/products/show.blade.php`
- `resources/views/admin/products/manifest-sync.blade.php`
- `routes/web.php`
- `tests/Feature/Admin/ProductCrudTest.php`

## 25) Lifecycle Validation And Audit
Status:
- started
- product lifecycle decisions now use shared validation rules and record audit entries for key setup actions

Current behavior:
- shared lifecycle validation now checks:
  - portal publication readiness
  - manifest sync approval readiness
  - manifest apply execution readiness
- validation is now enforced before:
  - `Publish To Portal`
  - moving manifest sync to `approved` or `applied`
  - moving manifest apply queue to `in_progress` or `done`
- a product lifecycle audit trail is now stored centrally in `app_settings` under:
  - `workspace_products.audit_trail.{product_code}`
- audit entries are currently recorded for:
  - product create/update
  - workspace experience save
  - runtime modules save
  - integrations save
  - portal publish/hide
  - manifest sync workflow updates
  - manifest apply queue updates
- `Product Builder` now shows:
  - lifecycle validation summary
  - lifecycle audit trail

Important files:
- `app/Services/Admin/ProductLifecycleService.php`
- `app/Http/Controllers/Admin/ProductController.php`
- `app/Http/Controllers/Admin/ProductExperienceController.php`
- `app/Http/Controllers/Admin/ProductRuntimeModuleController.php`
- `app/Http/Controllers/Admin/ProductIntegrationController.php`
- `app/Http/Controllers/Admin/ProductPortalPublicationController.php`
- `app/Http/Controllers/Admin/ProductManifestSyncController.php`
- `app/Http/Controllers/Admin/ProductManifestApplyQueueController.php`
- `resources/views/admin/products/show.blade.php`
- `resources/views/admin/products/manifest-sync.blade.php`
- `resources/views/admin/products/manifest-apply-queue.blade.php`
- `tests/Feature/Admin/ProductCrudTest.php`

## 26) Manifest Writeback Execution Package
Status:
- started
- approved manifest payloads now produce a saved writeback package for real code handoff

Current behavior:
- when manifest workflow moves to `approved` or `applied`, the system now stores:
  - `workspace_products.manifest_writeback_package.{product_code}`
- the writeback package includes:
  - target file
  - config path
  - family key
  - add/update mode
  - active payload sections
  - family payload
  - family snippet
  - verification commands
  - git commands
- `Manifest Sync Preview` now exposes two new exports:
  - `Execution JSON`
  - `Execution PHP`
- the screen now also shows the latest saved writeback package summary

Important files:
- `app/Http/Controllers/Admin/ProductManifestSyncController.php`
- `resources/views/admin/products/manifest-sync.blade.php`
- `tests/Feature/Admin/ProductCrudTest.php`

## 27) Runtime Wiring Consumption
Status:
- started
- tenancy runtime can now consume approved writeback packages even before manual config writeback lands

Current behavior:
- `WorkspaceManifestService` now overlays runtime family definitions from:
  - `workspace_products.manifest_writeback_package.{product_code}`
- this means approved writeback packages can supply:
  - family aliases
  - experience
  - sidebar section
  - dashboard actions
  - integrations
  - runtime modules
- the module and integration catalog services now benefit automatically because they already consume `WorkspaceManifestService`
- this provides a real runtime-consumption bridge between:
  - central admin product lifecycle UI
  - tenant workspace runtime behavior

Important files:
- `app/Services/Admin/AppSettingsService.php`
- `app/Services/Tenancy/WorkspaceManifestService.php`
- `tests/Feature/Tenancy/WorkspaceRuntimeConsumptionTest.php`

## 28) Canonical Tenant Host Structure
Status:
- started
- tenant workspace hosts now normalize to the root domain instead of product-prefixed central hosts

Current behavior:
- tenant domains now canonicalize around:
  - `demo.seven-scapital.com`
- product-prefixed central hosts like:
  - `automotive.seven-scapital.com`
  - `spareparts.seven-scapital.com`
  are now treated as legacy aliases for central access only
- incoming requests on those legacy hosts must redirect back to the canonical root-domain host while preserving path and query string
- host normalization now runs through a shared resolver used by:
  - registration
  - trial provisioning
  - tenant workspace provisioning
  - portal fallback workspace URLs
  - tenant URL building
- legacy stored `base_host` values that include `automotive.` now resolve back to the root host automatically

Important files:
- `app/Services/Tenancy/WorkspaceHostResolver.php`
- `app/Services/Automotive/TenantUrlBuilder.php`
- `app/Services/Automotive/StartTrialService.php`
- `app/Services/Automotive/ProvisionTenantWorkspaceService.php`
- `app/Http/Controllers/Automotive/Front/Auth/RegisterController.php`
- `app/Http/Controllers/Automotive/Front/CustomerPortalController.php`
- `app/Http/Middleware/CanonicalizeWorkspaceHost.php`
- `config/tenancy.php`
- `deploy/nginx/seven-scapital.conf.example`
- `deploy/apache/seven-scapital.conf.example`
- `deploy/WORKSPACE_ROUTING_CHECKLIST.md`
- `.env.example`
- `app/Console/Commands/Tenancy/DiagnoseWorkspaceRoutingCommand.php`
- `tests/Feature/Tenancy/CanonicalizeWorkspaceHostMiddlewareTest.php`
- `tests/Feature/Tenancy/DiagnoseWorkspaceRoutingCommandTest.php`
- `tests/Feature/Tenancy/WorkspaceHostResolverTest.php`
- `tests/Feature/Automotive/Portal/StartTrialServiceTest.php`
- `tests/Feature/Automotive/Portal/CustomerPortalBillingOptionsTest.php`

Deployment notes:
- production is split into separate Laravel projects:
  - `seven-scapital.com` / `www.seven-scapital.com` -> `/etc/nginx/sites-available/saas` -> `/var/www/saas/public`
  - system app -> `/etc/nginx/sites-available/automotive` -> `/var/www/automotive/public`
  - `spareparts.seven-scapital.com` -> `/etc/nginx/sites-available/spareparts` -> `/var/www/spareparts/public`
- the Nginx file name `automotive` is legacy server naming only; it hosts the multi-product system app, not an automotive-only product
- do not bind `*.seven-scapital.com` to the `saas` vhost; tenant workspace subdomains must reach the system app unless a more specific standalone vhost exists
- Laravel production env for the system app should use the real system hostname, with:
  - `SESSION_DOMAIN=.seven-scapital.com`
  - `SESSION_SECURE_COOKIE=true`

## 29) Integration Runtime Rendering
Status:
- completed
- workspace integrations now render consistently in tenant runtime screens

Current behavior:
- `WorkspaceIntegrationCatalogService` resolves integration targets from:
  - manifest `requires_family`
  - manifest/admin `target_family`
  - admin `target_product_code`
- integration cards only become available when the target product is active and accessible in the same tenant workspace
- rendered integration cards now include:
  - title
  - description
  - target product name
  - target status badge
  - action button to the target runtime route with `workspace_product` context
- dashboard, module pages, and work-order pages share one Blade partial for integration cards
- invalid route rendering is guarded at the Blade layer so bad draft routes do not break the whole screen

Important files:
- `app/Services/Tenancy/WorkspaceIntegrationCatalogService.php`
- `resources/views/automotive/admin/partials/workspace-integrations.blade.php`
- `resources/views/automotive/admin/dashboard/index.blade.php`
- `resources/views/automotive/admin/modules/show.blade.php`
- `resources/views/automotive/admin/modules/work-order-show.blade.php`
- `tests/Feature/Tenancy/WorkspaceRuntimeConsumptionTest.php`
- `tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`

## 30) Production Routing And Tenant Auth Corrections
Status:
- completed
- production routing expectations were clarified after separating the public frontend, system app, and standalone spare-parts app

Current behavior:
- public website/frontend pages are served by the separate `saas` Laravel app:
  - host: `seven-scapital.com` / `www.seven-scapital.com`
  - Nginx file: `/etc/nginx/sites-available/saas`
  - server root: `/var/www/saas/public`
- the multi-product system app is served by the Laravel project in `/var/www/automotive`
  - Nginx file: `/etc/nginx/sites-available/automotive`
  - server root: `/var/www/automotive/public`
  - the `automotive` file/project name is legacy naming and must not imply an automotive-only runtime
  - `system.seven-scapital.com` is a central system host and is listed in `config/tenancy.php`
  - tenant workspace wildcard hosts should route here unless a more specific standalone vhost exists
- the standalone spare-parts app is separate:
  - host: `spareparts.seven-scapital.com`
  - Nginx file: `/etc/nginx/sites-available/spareparts`
  - server root: `/var/www/spareparts/public`
- production Nginx must not bind `*.seven-scapital.com` to the `saas` vhost
- wildcard SSL is valid and should cover:
  - `seven-scapital.com`
  - `*.seven-scapital.com`
- system app theme assets should be requested from the system app hostname, not from the frontend-only `seven-scapital.com` host
- if a page on `https://seven-scapital.com` requests `/theme/...`, those files must exist in `/var/www/saas/public/theme` or the `saas` app must deliberately alias/copy the shared theme assets
- `WorkspaceHostResolver` strips `system.`, `automotive.`, and `spareparts.` product/system host segments when building canonical tenant root domains

Tenant workspace auth behavior:
- `/workspace` on a tenant host is now only an entry point
- if logged out:
  - redirect to `/workspace/admin/login`
- if logged in:
  - redirect to `/workspace/admin/dashboard`
- the logged-out workspace entry must never render tenant sidebar/header

Important files:
- `app/Http/Controllers/Automotive/Admin/Auth/AuthController.php`
- `app/Services/Tenancy/WorkspaceHostResolver.php`
- `config/tenancy.php`
- `.env.example`
- `deploy/nginx/seven-scapital.conf.example`
- `deploy/WORKSPACE_ROUTING_CHECKLIST.md`
- `resources/views/automotive/admin/layouts/adminLayout/mainlayout.blade.php`
- `tests/Feature/Tenancy/WorkspaceHostResolverTest.php`
- `tests/Feature/Tenancy/DiagnoseWorkspaceRoutingCommandTest.php`
- `tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`
- `routes/web.php`
- `tests/Feature/ExampleTest.php`

Production Nginx note:
- `/etc/nginx/sites-available/saas` should use only `server_name seven-scapital.com www.seven-scapital.com`
- `/etc/nginx/sites-available/automotive` should use the real system hostname plus `*.seven-scapital.com` if tenant subdomains are served by this app
- `/etc/nginx/sites-available/spareparts` should use the exact `spareparts.seven-scapital.com` host
- if browser requests for system pages load `/theme/...` from `https://seven-scapital.com`, the system app `.env`/asset host is wrong for the three-project split
- if browser requests for frontend pages on `https://seven-scapital.com` load `/theme/...` and return `404`, fix the `saas` project assets because that host is not served by the system app
- see `deploy/nginx/seven-scapital.conf.example` and `deploy/WORKSPACE_ROUTING_CHECKLIST.md`
