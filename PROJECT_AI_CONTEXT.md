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

## 14) Current Strong Summary
If a new session starts and reads only this section, the safe understanding is:

- This is now a multi-product SaaS in transition from automotive-first to product-neutral.
- The portal is becoming the home of tenant-facing account management.
- Tenant admin is becoming the home of runtime modules only.
- Automotive runtime is the most mature product family.
- Spare parts is already viable as its own runtime family for current inventory-related workflows.
- Accounting exists architecturally but still needs deeper runtime implementation.
- Product families, modules, and integrations are being moved into a workspace manifest.
- Cross-product integration has already started for real:
  - workshop can consume spare parts
  - completed work orders can generate local accounting handoff events

## 15) Recommended Next Package
The next package should be:

### Tenant Admin Billing Surface Decommission
Scope:
- remove or demote remaining tenant-admin billing/account surfaces that conflict with the portal boundary
- keep middleware/runtime protection intact while reducing tenant confusion
- ensure tenant admin UI does not present billing/account ownership as if it lives inside runtime

Why this is next:
- portal-owned account/settings now exists
- billing already exists in the portal too
- the remaining gap is legacy tenant-admin billing exposure
- this hardens the UX boundary:
  - `Portal = account and subscription control`
  - `Tenant Admin = systems and operations`

Suggested start files:
- `app/Http/Controllers/Automotive/Admin/BillingController.php`
- `resources/views/automotive/admin/billing/*`
- `app/Http/Middleware/EnsureTenantSubscriptionIsActive.php`
- tenant admin header/breadcrumb files where billing/account language still appears

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
