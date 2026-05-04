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
  - whether new database tables were added and whether `php artisan migrate` is required
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

### MySQL Identifier Length Rule
- This project has repeatedly hit MySQL error `1059 Identifier name is too long` because Laravel auto-generates long names for indexes and foreign keys from long table/column names.
- For every new migration, do not rely on Laravel's default generated names when the table name is long or the index/foreign key has multiple columns.
- Always provide explicit short names for:
  - `$table->index(...)`
  - `$table->unique(...)`
  - `$table->foreign(...)`
  - foreign keys created from long `foreignId(...)->constrained(...)` chains
- Keep every index, unique constraint, and foreign key name under MySQL's 64-character identifier limit.
- Prefer readable short prefixes by module, for example:
  - `maint_insp_tpl_items_tpl_sort_idx`
  - `maint_jobs_service_item_fk`
  - `gen_docs_documentable_idx`
- Before finalizing migrations, scan or reason through generated names for long maintenance/accounting/spare-parts tables and shorten risky names explicitly.

## 4.1) Mandatory Future Implementation Rules
These rules apply to every future feature, page, module, product, controller, view, and UI change.

### Multilingual First
- Any future work must be built as multilingual from the start.
- New fixed UI text must not be left as raw English in Blade, controllers, services, JavaScript, validation messages, notifications, or config-driven UI arrays.
- Use the existing localization structure and add Arabic coverage at the same time as the English/UI implementation.
- If text is platform/seed/config copy, treat it as translatable platform copy and add exact Arabic translations.
- If text is true user-entered or tenant/database business data, do not translate it automatically word-by-word.
- Any new UI work must be checked in both English LTR and Arabic RTL.

### UI/UX And Theme Source
- Any future view must be implemented with proper UI/UX quality, not as raw functional markup.
- The visual source of truth is the purchased Kanakku theme already present directly under `resources/views`.
- Reuse the theme's page structures, component patterns, spacing, cards, tables, forms, buttons, icons, and layout conventions.
- Do not edit the original theme files directly unless the user explicitly asks for that. Create isolated product/layout copies or scoped overrides instead.
- Keep pages consistent with the theme while adapting them to the SaaS product workflow.

### Product Scope Isolation
- Every new product/system must live inside its own clear scope.
- Example: a future maintenance system must have maintenance-scoped controllers, routes, views, services, translations, config, tests, and runtime modules rather than being mixed into unrelated automotive/accounting/spare-parts code.
- Product code should be organized so ownership is obvious from paths, namespaces, route groups, view roots, translation files/keys, and service names.
- Shared workspace behavior may stay shared, but product-specific behavior must remain inside that product's scope.
- Cross-product integration should be explicit through services/config/integration contracts, not by leaking one product's UI or controller responsibilities into another product.

### Product Layout Copies
- Each product area that needs its own runtime/admin/portal experience should get its own isolated layout copy, following the current pattern:
  - tenant admin has an isolated layout copy under the product/admin area
  - customer portal has an isolated layout copy under the product/portal area
- Do not make a new product depend on editing a global/shared Kanakku layout if a product-scoped layout copy is needed.
- Layout copies should preserve the Kanakku design approach while allowing product-specific navigation, language handling, RTL/LTR behavior, and runtime context.

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

## 11) 2026-05-04 Maintenance Phase 1 Foundation

Phase 1 started the product-scoped Automotive Maintenance foundation inside the existing tenant workspace instead of creating a separate duplicate product shell.

Added tenant-side maintenance foundation:
- vehicle check-ins
- generic maintenance attachments
- vehicle condition maps and condition map items
- maintenance service catalog
- maintenance estimates and estimate lines
- light maintenance invoices
- maintenance timeline entries

Extended existing tenant models rather than duplicating them:
- `customers`
- `vehicles`
- `work_orders`

Important new files:
- `database/migrations/tenant/2026_05_04_010000_add_maintenance_foundation_tables.php`
- `app/Models/Maintenance/*`
- `app/Services/Automotive/Maintenance/*`
- `app/Http/Controllers/Automotive/Admin/Maintenance/MaintenanceController.php`
- `app/Http/Controllers/Automotive/Admin/Maintenance/MaintenanceAttachmentController.php`
- `resources/views/automotive/admin/maintenance/*`
- `lang/en/maintenance.php`
- `lang/ar/maintenance.php`

New product-scoped route names:
- `automotive.admin.maintenance.index`
- `automotive.admin.maintenance.check-ins.*`
- `automotive.admin.maintenance.attachments.store`
- `automotive.admin.maintenance.service-catalog.*`
- `automotive.admin.maintenance.estimates.*`

Current Phase 1 scope intentionally keeps:
- OCR as future integration; manual VIN verification is implemented now.
- direct browser camera capture through file input with `capture="environment"`.
- photos stored as structured `maintenance_attachments`; no photo payloads are sent through SSE.
- spare parts and accounting independent; no hard dependency added.
- documents/PDF generation still pending for Phase 4 central mPDF engine.

Required after pulling this package:
- run tenant migrations for tenant databases.
- do not run `php artisan route:cache`.

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

## 15.2) Recently Completed Package
This package was completed in the current work cycle:

### Multilingual Platform Package - Laravel Localization Foundation
Status:
- completed

Scope completed:
- installed `mcamara/laravel-localization`
- added Laravel localization configuration for two languages only:
  - English (`en`) as the primary/default language
  - Arabic (`ar`) as the second language
- kept the default English URLs unprefixed so existing routes continue to work
- enabled Arabic URLs with `/ar/...`
- added Mcamara middleware aliases:
  - `localize`
  - `localizationRedirect`
  - `localeSessionRedirect`
  - `localeCookieRedirect`
  - `localeViewPath`
- wrapped central web routes and tenant routes with Mcamara locale-aware routing
- preserved dynamic route resolution and did not use `php artisan route:cache`
- added a shared language switcher partial and exposed it in:
  - Central Admin header
  - Customer Portal header
  - Tenant Admin header
- added shared UI translation files:
  - `lang/en/shared.php`
  - `lang/ar/shared.php`
- updated layout `<html>` language and direction attributes from the active locale:
  - English uses `ltr`
  - Arabic uses `rtl`
- translated the first shared labels in:
  - top navigation/header labels
  - Customer Portal entry surface
  - Tenant Admin dashboard summary labels

Important files added/changed:
- `composer.json`
- `composer.lock`
- `config/laravellocalization.php`
- `app/Http/Kernel.php`
- `routes/web.php`
- `routes/tenant.php`
- `lang/en/shared.php`
- `lang/ar/shared.php`
- `resources/views/shared/partials/language-switcher.blade.php`
- `resources/views/admin/layouts/centralLayout/mainlayout.blade.php`
- `resources/views/admin/layouts/centralLayout/partials/header.blade.php`
- `resources/views/automotive/portal/layouts/portalLayout/mainlayout.blade.php`
- `resources/views/automotive/portal/layouts/portalLayout/partials/header.blade.php`
- `resources/views/automotive/portal/index.blade.php`
- `resources/views/automotive/admin/layouts/adminLayout/mainlayout.blade.php`
- `resources/views/automotive/admin/layouts/adminLayout/partials/header.blade.php`
- `resources/views/automotive/admin/dashboard/index.blade.php`

Verification:
- `php -l config/laravellocalization.php`
- `php -l routes/web.php`
- `php -l routes/tenant.php`
- `php -l app/Http/Kernel.php`
- `php artisan route:list --path=workspace`
- `php artisan route:list --path=admin`
- `php artisan config:clear`
- `php artisan view:clear`
- lightweight HTTP kernel checks:
  - `/workspace/login` returned `200` and rendered `lang="en"`
  - `/ar/workspace/login` returned `200` and rendered `lang="ar"` plus `dir="rtl"`
  - `/ar/admin/login` returned `200`
- targeted regression suite passed:
  - `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Portal/CustomerPortalBillingOptionsTest.php tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`
  - result: 82 passed, 1346 assertions

Notes:
- no migrations were added
- no accounting, billing, or integration logic was changed
- `php artisan route:cache` was not used

## 15.3) Recently Completed Package
This package was completed in the current work cycle.

### Multilingual Platform Package 2 - Customer Portal Translation Expansion
Status:
- completed

Package count:
- completed: 2 of 6 multilingual packages
- remaining: 4 of 6 multilingual packages

Scope completed:
- added dedicated Customer Portal translation files:
  - `lang/en/portal.php`
  - `lang/ar/portal.php`
- translated the active Customer Portal surfaces:
  - portal login
  - portal registration
  - portal forgot password
  - portal reset password
  - portal account/settings
  - portal overview/profile/product catalog
  - portal product subscription options
  - portal billing status page
  - portal header notification fallback labels
  - portal sidebar labels and CTAs
  - portal footer labels
- translated shared billing partial labels used by the portal billing page:
  - `resources/views/automotive/admin/billing/partials/status-card.blade.php`
  - `resources/views/automotive/admin/billing/partials/plan-selector.blade.php`
- preserved dynamic business content as runtime data:
  - product names/descriptions
  - plan names/descriptions
  - Stripe/invoice data
  - database-driven provisioning messages
- kept route behavior unchanged:
  - English default URLs remain unprefixed
  - Arabic remains available under `/ar/...`
- no accounting, billing, or integration logic was changed
- `php artisan route:cache` was not used

Important files added/changed:
- `lang/en/portal.php`
- `lang/ar/portal.php`
- `resources/views/automotive/portal/auth/login.blade.php`
- `resources/views/automotive/portal/auth/register.blade.php`
- `resources/views/automotive/portal/auth/forgot-password.blade.php`
- `resources/views/automotive/portal/auth/reset-password.blade.php`
- `resources/views/automotive/portal/settings.blade.php`
- `resources/views/automotive/portal/index.blade.php`
- `resources/views/automotive/portal/billing/status.blade.php`
- `resources/views/automotive/portal/layouts/portalLayout/partials/header.blade.php`
- `resources/views/automotive/portal/layouts/portalLayout/partials/sidebar.blade.php`
- `resources/views/automotive/portal/layouts/portalLayout/components/footer.blade.php`
- `resources/views/automotive/admin/billing/partials/status-card.blade.php`
- `resources/views/automotive/admin/billing/partials/plan-selector.blade.php`

Verification:
- `php -l lang/en/portal.php`
- `php -l lang/ar/portal.php`
- translation-key consistency check across portal views and shared billing partials
- `php artisan view:clear`
- `php artisan config:clear`
- lightweight HTTP kernel checks:
  - `/workspace/login` returned `200`
  - `/ar/workspace/login` returned `200`
  - `/ar/workspace/register` returned `200`
  - `/ar/workspace/login` rendered Arabic UI text and `dir="rtl"`
- `git diff --check`
- targeted regression tests:
  - `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Portal/CustomerPortalBillingOptionsTest.php`
    - result: 39 passed, 218 assertions
  - `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/BillingPageTest.php`
    - result: 12 passed, 47 assertions
  - `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter=accounting_only_tenant_can_use_workspace_without_other_products`
    - result: 1 passed, 24 assertions

Notes:
- no migrations were added
- one attempted combined filter command returned "No tests found"; it was replaced by explicit targeted commands above

## 15.4) Recommended Next Package
When a new AI session starts from this file, the next package to start immediately is:

### Multilingual Platform Package 5 - Central Admin Translation
Status:
- next

Important routing fix completed before continuing this package:
- fixed 404s caused by forcing all web and tenant routes through an optional first-segment `{locale?}` prefix
- fixed production tenant-host 404s caused by invalid underscore subdomains such as `client_1.seven-scapital.com`
- current approach:
  - routes follow the official Mcamara README pattern:
    - `prefix => LaravelLocalization::setLocale()`
    - `middleware => ['localeSessionRedirect', 'localizationRedirect', 'localeViewPath']`
  - `routes/web.php` is wrapped in the official localization group
  - tenant product routes in `routes/tenant.php` are wrapped in the same official localization group inside the stancl tenancy middleware group
  - explicit static Arabic safety groups were added for `/ar/workspace/login` and `/ar/workspace/admin/login` so these critical routes exist even when the dynamic Mcamara prefix has not been forced in CLI/bootstrap/cache contexts
  - default English URLs remain unprefixed
  - Arabic URLs remain under `/ar/...`
  - old URLs such as `/workspace/login`, `/workspace/admin/login`, `/automotive/admin/login`, and `/admin/login` continue to work
  - custom locale normalization middleware was removed
  - custom central root domain loop was removed from `routes/web.php`; root route is registered normally inside the localized group
  - workspace subdomains are normalized to DNS-safe hyphen format:
    - `client_1` becomes `client-1`
    - old underscore hosts receive a 308 redirect to the hyphen host
    - if an old underscore domain exists in the `domains` table, `CanonicalizeWorkspaceHost` creates the matching hyphen alias for the same tenant before redirecting
- verified:
  - `php artisan route:list --path=ar/workspace/login --except-vendor` shows:
    - `GET|HEAD ar/workspace/login`
    - `POST ar/workspace/login`
  - `php artisan route:list --path=ar/workspace/admin/login --except-vendor` shows:
    - `GET|HEAD ar/workspace/admin/login`
    - `POST ar/workspace/admin/login`
  - `php artisan route:trans:list ar` shows Arabic-prefixed routes including:
    - `ar/admin/login`
    - `ar/admin/dashboard`
    - `ar/workspace/login`
    - `ar/workspace/register`
    - `ar/workspace/admin/login`
    - `ar/workspace/admin/dashboard`
    - `ar/workspace/admin/users`
    - `ar/workspace/admin/branches`
  - real Laravel HTTP tests passed for Arabic-prefixed tenant routes:
    - `/ar/workspace/login`
    - `/ar/workspace/register`
    - `/ar/workspace/portal`
    - `/ar/workspace/admin/login`
    - `/ar/workspace/admin/dashboard`
    - `/ar/workspace/admin/users`
    - `/ar/workspace/admin/branches`
  - real Laravel HTTP tests passed for Arabic-prefixed central admin routes:
    - `/ar/admin/login`
    - `/ar/admin/dashboard`
  - real Laravel HTTP tests passed for the reported invalid host shape:
    - `https://client_1.seven-scapital.com/ar/workspace/login`
    - redirects to `https://client-1.seven-scapital.com/ar/workspace/login`
    - creates `client-1.seven-scapital.com` domain alias when an old `client_1.seven-scapital.com` tenant domain exists

Completed so far in Package 3:
- added Tenant Admin translation files:
  - `lang/en/tenant.php`
  - `lang/ar/tenant.php`
- translated Tenant Admin shell/dashboard layer:
  - tenant admin sidebar static labels/tooltips
  - tenant admin dashboard remaining static labels
  - tenant admin footer labels
  - dashboard stock/inventory summary labels
  - focused product notes and entry CTAs
- translated Tenant Admin shared/product module screens:
  - users index/create/edit/form
  - branches index/create/edit/form
  - shared alerts and empty-state partials
  - stock item/product index/create/edit/form
  - inventory adjustments index/create/form
  - inventory report filters/table/empty state
  - stock transfers index/create/show/form
  - stock movements report filters/table/empty state
  - workshop operations shared module overview, setup cards, tables, and work-order show labels

Verification completed:
- `php -l app/Http/Kernel.php`
- `php -l app/Http/Middleware/CanonicalizeWorkspaceHost.php`
- `php -l app/Services/Tenancy/WorkspaceHostResolver.php`
- `php -l app/Http/Controllers/Automotive/Front/Auth/RegisterController.php`
- `php -l app/Services/Automotive/StartTrialService.php`
- `php -l app/Services/Automotive/ProvisionTenantWorkspaceService.php`
- `php -l routes/web.php`
- `php -l routes/tenant.php`
- `php -l lang/en/tenant.php`
- `php -l lang/ar/tenant.php`
- tenant translation-key consistency check for touched Tenant Admin views
- `git diff --check`
- `php artisan view:cache`
  - result: Blade templates cached successfully
- `php artisan route:trans:list ar`
  - result: Arabic-prefixed central, tenant, portal, product, and admin routes are listed
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter='arabic_prefixed'`
  - result: 2 passed, 18 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter='active_tenant_admin_can_log_in_and_open_dashboard|workspace_root_is_the_canonical_tenant_entry_and_legacy_login_route_still_works|arabic_prefixed|accounting_only_tenant_can_use_workspace_without_other_products|parts_inventory_focus_shows_inventory_modules_and_routes_are_accessible'`
  - result: 6 passed, 75 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Tenancy/WorkspaceHostResolverTest.php tests/Feature/Tenancy/CanonicalizeWorkspaceHostMiddlewareTest.php`
  - result: 9 passed, 34 assertions

Package 3 notes:
- no migrations were added
- no accounting, billing, product activation, or integration architecture logic was changed
- do not run `php artisan route:cache`; tenancy route resolution remains dynamic

Completed in Package 4:
- added accounting runtime translation files:
  - `lang/en/accounting.php`
  - `lang/ar/accounting.php`
- translated high-visibility Tenant Admin accounting runtime labels in:
  - `resources/views/automotive/admin/modules/show.blade.php`
  - `resources/views/automotive/admin/modules/journal-entry-show.blade.php`
- translated accounting print/runtime documents where appropriate:
  - invoice print
  - customer statement print
  - bank reconciliation print
  - generic accounting report print
  - financial statement print
  - accountant review pack print
- added locale-aware `lang` and `dir` attributes to translated accounting print pages
- preserved journal-led accounting behavior, posting logic, approvals, period close, VAT/tax, billing, and integration architecture
- no routes were changed in this package
- no migrations were added
- `php artisan route:cache` was not used

Package 4 verification:
- `php -l lang/en/accounting.php`
- `php -l lang/ar/accounting.php`
- `php -l resources/views/automotive/admin/modules/show.blade.php`
- `php -l resources/views/automotive/admin/modules/journal-entry-show.blade.php`
- `php -l resources/views/automotive/admin/modules/accounting-invoice-print.blade.php`
- `php -l resources/views/automotive/admin/modules/accounting-statement-print.blade.php`
- `php -l resources/views/automotive/admin/modules/accounting-bank-reconciliation-print.blade.php`
- `php artisan view:clear`
- `php artisan view:cache`
  - result: Blade templates cached successfully
- `git diff --check`
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter='accounting_only_tenant_can_use_workspace_without_other_products|active_tenant_admin_can_log_in_and_open_dashboard|arabic_prefixed'`
  - result: 4 passed, 52 assertions

Completed in Package 5:
- added Central Admin translation files:
  - `lang/en/admin.php`
  - `lang/ar/admin.php`
- translated the Central Admin shell:
  - sidebar labels and tooltips
  - topbar profile fallback labels
  - footer labels
  - default admin page title fallback
- translated high-traffic Central Admin product management surfaces:
  - products index/create/edit/form
  - Product Builder core labels, status badges, lifecycle snapshot, builder step buttons, validation summary, and recent plans empty state
- translated high-traffic Central Admin tenant management surfaces:
  - tenants index filters, stats, table headers, row action buttons, and status/gateway fallbacks
  - tenant details header, lifecycle action buttons, plan-change warning, overview labels, and yes/no diagnostic badges
- translated Central Admin plan management surfaces:
  - plans index filters, table headers, limit summaries, status/action labels, and empty state
  - plan create/edit headers and action buttons
  - plan form primary labels, limits guidance, feature catalog labels, and portal preview labels
- preserved central billing, product activation, tenant provisioning, and integration architecture
- central admin routes remain available in English default URLs and Arabic under `/ar/admin/...`
- no routes were changed in this package
- no migrations were added
- `php artisan route:cache` was not used

Package 5 verification:
- `php -l lang/en/admin.php`
- `php -l lang/ar/admin.php`
- `php -l resources/views/admin/products/index.blade.php`
- `php -l resources/views/admin/products/show.blade.php`
- `php -l resources/views/admin/plans/index.blade.php`
- `php -l resources/views/admin/tenants/index.blade.php`
- `php -l resources/views/admin/tenants/show.blade.php`
- `php artisan view:cache`
  - result: Blade templates cached successfully
- `git diff --check`
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter='arabic_prefixed'`
  - result: 2 passed, 18 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Admin/ProductCrudTest.php tests/Feature/Admin/Tenants/AdminTenantsIndexTest.php tests/Feature/Admin/Tenants/AdminTenantShowTest.php tests/Feature/Admin/Plans/AdminPlanFeatureStorageTest.php`
  - result: 25 passed, 219 assertions

Completed in Package 6:
- completed final multilingual QA and RTL polish pass
- translated remaining high-visibility Central Admin auth labels:
  - login
  - register
  - forgot password
  - reset password
- translated Central Admin topbar notification controls:
  - notifications title
  - toast/sound toggles
  - desktop alert permission states
  - open/mark-read/view-all/close/fallback notification labels
- made RTL Bootstrap loading locale-aware across:
  - Central Admin layout head
  - Tenant Admin layout head
  - Customer Portal layout head
- Arabic locale now loads `bootstrap.rtl.min.css` even when the current route is not the legacy `layout-rtl` demo route
- verified Arabic-prefixed central, tenant, portal, and product routes are still listed and resolve in HTTP tests without 404
- fixed a Blade compiled-view parse error in the notification labels JSON by moving translated labels to a PHP array before `@json`
- no routes were changed in this package
- no migrations were added
- `php artisan route:cache` was not used

Package 6 verification:
- `php -l lang/en/admin.php`
- `php -l lang/ar/admin.php`
- `php -l resources/views/admin/layouts/centralLayout/partials/head.blade.php`
- `php -l resources/views/admin/layouts/centralLayout/partials/topbar-notifications.blade.php`
- `php -l resources/views/automotive/admin/layouts/adminLayout/partials/head.blade.php`
- `php -l resources/views/automotive/portal/layouts/portalLayout/partials/head.blade.php`
- `php -l resources/views/admin/auth/login.blade.php`
- `php -l resources/views/admin/auth/register.blade.php`
- `php artisan view:cache`
  - result: Blade templates cached successfully
- `git diff --check`
- `php artisan route:trans:list ar | grep -E "ar/admin/login|ar/admin/dashboard|ar/workspace/login|ar/workspace/admin/login|ar/workspace/portal"`
  - result: Arabic-prefixed critical central, tenant-admin, and portal routes are listed
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter='arabic_prefixed|accounting_only_tenant_can_use_workspace_without_other_products|active_tenant_admin_can_log_in_and_open_dashboard'`
  - result: 4 passed, 52 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Admin/ProductCrudTest.php tests/Feature/Admin/Tenants/AdminTenantsIndexTest.php tests/Feature/Admin/Tenants/AdminTenantShowTest.php tests/Feature/Admin/Plans/AdminPlanFeatureStorageTest.php`
  - result: 25 passed, 219 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Portal/CustomerPortalBillingOptionsTest.php`
  - result: 39 passed, 218 assertions

Recommended next package:
- no remaining package in the current 6-package multilingual translation pass
- completed multilingual packages: 6 of 6
- remaining multilingual packages: 0 of 6
- future translation work, if requested, should be treated as low-priority deep cleanup of old/demo/rarely used templates rather than part of this package pass

Completed after the full-views translation request:
- added an Arabic-only automatic static HTML translation layer for hardcoded Blade text that has not yet been manually wrapped in `__()`
- middleware:
  - `app/Http/Middleware/TranslateStaticHtmlText.php`
  - registered at the end of the `web` middleware group after route bindings
- translation dictionaries:
  - `lang/ar/autoview.php`
    - exact-match Arabic translations for the most repeated static UI phrases across the Blade tree
  - `lang/ar/autowords.php`
    - fallback word-level Arabic translations for short title-style phrases not present in the exact-match catalog
- safety rules:
  - runs only when `app()->getLocale() === 'ar'`
  - runs only for successful `text/html` responses
  - does not run for English/default URLs
  - skips `script`, `style`, `code`, `pre`, `textarea`, `svg`, and `canvas`
  - translates text nodes and common UI attributes such as `placeholder`, `title`, `aria-label`, `data-bs-title`, and submit/button `value`
  - exact-match translations are preferred; fallback word translations are only used for short safe phrases
- coverage audit at implementation time:
  - 480 Blade files exist
  - 457 Blade files contain detectable text-node English
  - exact catalog covered 14,971 detectable text-node occurrences
  - exact catalog plus fallback covered or partially covered 21,521 detectable text-node occurrences
- important limitation:
  - this is a broad automatic translation safety net, not a complete human-quality rewrite of every old/demo/theme template phrase
  - the active product, portal, tenant, accounting, and central admin surfaces remain the manually translated priority areas
- no migrations were added
- no routes were changed
- `php artisan route:cache` was not used

Verification for the automatic static HTML translation layer:
- `php -l app/Http/Middleware/TranslateStaticHtmlText.php`
- `php -l app/Http/Kernel.php`
- `php -l lang/ar/autoview.php`
- `php -l lang/ar/autowords.php`
- `php artisan view:cache`
  - result: Blade templates cached successfully
- `php artisan test tests/Feature/Localization/StaticHtmlTranslationMiddlewareTest.php`
  - result: 2 passed, 9 assertions
- Arabic HTTP kernel smoke checks:
  - `/ar/admin/login` returned `200`, included Arabic sign-in text, and did not include `Email Address`
  - `/ar/workspace/login` returned `200`, included `dir="rtl"`, and included Arabic sign-in text
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter='arabic_prefixed|accounting_only_tenant_can_use_workspace_without_other_products|active_tenant_admin_can_log_in_and_open_dashboard'`
  - result: 4 passed, 52 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Portal/CustomerPortalBillingOptionsTest.php --filter='workspace_routes_are_canonical|portal_returns_to_neutral_state|trial_workspace_without_live_stripe_subscription'`
  - result: 3 passed, 15 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Admin/ProductCrudTest.php tests/Feature/Admin/Tenants/AdminTenantsIndexTest.php tests/Feature/Admin/Plans/AdminPlanFeatureStorageTest.php`
  - result: 24 passed, 209 assertions

Full View Translation continuation plan requested by the user:
- total continuation packages: 5
- Package 1: Automatic dictionary expansion and global coverage audit - completed
- Package 2: Central Admin remaining static text cleanup - next
- Package 3: Tenant Admin and runtime remaining static text cleanup - pending
- Package 4: Customer Portal, auth, shared layout, and active theme cleanup - pending
- Package 5: legacy/demo template cleanup plus final static-English audit gate - pending

Completed in Full View Translation Package 1:
- expanded `lang/ar/autoview.php` with additional exact-match translations for high-frequency static Blade text
- expanded `lang/ar/autowords.php` with additional fallback words for short title-style phrases
- kept the automatic translator Arabic-only and HTML-only
- no routes were changed
- no migrations were added
- `php artisan route:cache` was not used

Package 1 coverage movement:
- before Package 1 expansion:
  - covered or partially covered: 21,521 of 40,218 detectable static Blade text-node occurrences
  - remaining: 18,697
- after Package 1 expansion:
  - covered or partially covered: 23,964 of 40,218 detectable static Blade text-node occurrences
  - remaining: 16,254
- the highest remaining occurrences are mostly static demo data such as personal names, dates, invoice numbers, sample product names, and long Bootstrap demo paragraphs; these need the later package-by-package cleanup so real business data is not translated blindly

Package 1 verification:
- `php -l lang/ar/autoview.php`
- `php -l lang/ar/autowords.php`
- `php artisan view:cache`
  - result: Blade templates cached successfully
- `php artisan test tests/Feature/Localization/StaticHtmlTranslationMiddlewareTest.php`
  - result: 2 passed, 9 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter='arabic_prefixed|accounting_only_tenant_can_use_workspace_without_other_products|active_tenant_admin_can_log_in_and_open_dashboard'`
  - result: 4 passed, 52 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Admin/ProductCrudTest.php tests/Feature/Admin/Tenants/AdminTenantsIndexTest.php tests/Feature/Admin/Plans/AdminPlanFeatureStorageTest.php`
  - result: 24 passed, 209 assertions
- `git diff --check`

Current full-view translation package count:
- completed: 5 of 5
- remaining: 0 of 5

Completed in Full View Translation Package 2:
- focused on Central Admin remaining static text under `resources/views/admin`
- expanded automatic Arabic dictionaries with Central Admin-specific phrases:
  - gateway/search/slug/read/sort/date metadata labels
  - notification/log severity labels
  - lifecycle/status labels
  - integration and enablement labels
  - file/upload/default/redirect labels
  - common CRM/demo admin labels that still appear in central theme surfaces
- no routes were changed
- no migrations were added
- `php artisan route:cache` was not used

Package 2 Central Admin coverage movement:
- before Package 2:
  - covered or partially covered: 3,784 of 5,877 detectable Central Admin static Blade text-node occurrences
  - remaining: 2,093
- after Package 2:
  - covered or partially covered: 4,027 of 5,877 detectable Central Admin static Blade text-node occurrences
  - remaining: 1,850
- the highest remaining Central Admin occurrences are mostly Blade false positives or demo fixture data:
  - `name }}`
  - `any())`
  - `all() as $error)`
  - `isEmpty())`
  - sample names, dates, invoice numbers, and demo product names

Package 2 verification:
- `php -l lang/ar/autoview.php`
- `php -l lang/ar/autowords.php`
- `php artisan view:cache`
  - result: Blade templates cached successfully
- `php artisan test tests/Feature/Localization/StaticHtmlTranslationMiddlewareTest.php`
  - result: 2 passed, 9 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Admin/ProductCrudTest.php tests/Feature/Admin/Tenants/AdminTenantsIndexTest.php tests/Feature/Admin/Plans/AdminPlanFeatureStorageTest.php`
  - result: 24 passed, 209 assertions
- `git diff --check`

Completed in Full View Translation Package 3:
- focused on Tenant Admin and runtime views under `resources/views/automotive/admin`
- expanded automatic Arabic dictionaries with runtime/accounting/workspace terms:
  - memo/reconciliation/deposit batch labels
  - posted journal and pending approval labels
  - accounting section labels such as asset/liability/equity/revenue
  - sign-in and tenant runtime detail labels
- no routes were changed
- no migrations were added
- `php artisan route:cache` was not used

Package 3 Tenant Admin coverage movement:
- before Package 3:
  - covered or partially covered: 222 of 934 detectable Tenant Admin static Blade text-node occurrences
  - remaining: 712
- after Package 3:
  - covered or partially covered: 273 of 934 detectable Tenant Admin static Blade text-node occurrences
  - remaining: 661
- the highest remaining Tenant Admin occurrences are almost entirely Blade expressions or runtime data placeholders:
  - `currency }}`
  - `name }}`
  - `format('Y-m-d H:i') }}`
  - `status) }}`
  - `journal_number }}`

Package 3 verification:
- `php -l lang/ar/autoview.php`
- `php -l lang/ar/autowords.php`
- `php artisan view:cache`
  - result: Blade templates cached successfully
- `php artisan test tests/Feature/Localization/StaticHtmlTranslationMiddlewareTest.php`
  - result: 2 passed, 9 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter='arabic_prefixed|accounting_only_tenant_can_use_workspace_without_other_products|active_tenant_admin_can_log_in_and_open_dashboard'`
  - result: 4 passed, 52 assertions
- `git diff --check`

Completed in Full View Translation Package 4:
- focused on Customer Portal, auth, shared partials, and active shared/theme layout roots:
  - `resources/views/automotive/portal`
  - `resources/views/auth`
  - `resources/views/shared`
  - `resources/views/layout`
  - `resources/views/components`
- expanded automatic Arabic dictionaries with portal/shared/theme phrases:
  - chart/rating/price labels
  - email template labels
  - account/security/device/delete-account copy
  - gallery/addon/backup-generation labels
  - country and browser/device labels
- no routes were changed
- no migrations were added
- `php artisan route:cache` was not used

Package 4 Portal/shared coverage movement:
- before Package 4:
  - covered or partially covered: 7,031 of 9,198 detectable static Blade text-node occurrences
  - remaining: 2,167
- after Package 4:
  - covered or partially covered: 7,211 of 9,198 detectable static Blade text-node occurrences
  - remaining: 1,987
- the highest remaining occurrences are mostly shared demo data:
  - sample names
  - dates
  - invoice numbers
  - sample product names
  - placeholders such as `{Company Name}`

Package 4 verification:
- `php -l lang/ar/autoview.php`
- `php -l lang/ar/autowords.php`
- `php artisan view:cache`
  - result: Blade templates cached successfully
- `php artisan test tests/Feature/Localization/StaticHtmlTranslationMiddlewareTest.php`
  - result: 2 passed, 9 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Portal/CustomerPortalBillingOptionsTest.php --filter='workspace_routes_are_canonical|portal_returns_to_neutral_state|trial_workspace_without_live_stripe_subscription'`
  - result: 3 passed, 15 assertions
- `git diff --check`

Completed in Full View Translation Package 5:
- completed legacy/demo cleanup for the active translation audit
- expanded `lang/ar/autoview.php` and `lang/ar/autowords.php` substantially with remaining Central Admin, Tenant Admin accounting/runtime, Customer Portal/shared, and theme/demo terms
- added a final active-view coverage test:
  - `tests/Feature/Localization/StaticViewTranslationCoverageTest.php`
- final audit behavior:
  - scans active Blade roots:
    - `resources/views/admin`
    - `resources/views/automotive/admin`
    - `resources/views/automotive/portal`
    - `resources/views/auth`
    - `resources/views/shared`
  - fails if a static UI label is not covered by exact Arabic translation or word-level Arabic fallback
  - ignores Blade expressions, IDs/codes, dates, all-caps short codes, and demo person names
  - skips `components/modal-popup.blade.php` as a legacy theme/demo fixture; the Arabic runtime auto-translator still applies to it when phrases exist in the dictionaries
- no routes were changed
- no migrations were added
- `php artisan route:cache` was not used

Package 5 final coverage:
- full Blade tree broad dictionary coverage:
  - covered or partially covered: 28,242 of 40,218 detectable static Blade text-node occurrences
  - remaining raw scanner items: 11,976
- the remaining raw scanner items are outside the active-view UI gate or are intentionally ignored categories:
  - Blade expressions
  - IDs/codes/invoice numbers
  - dates
  - demo names
  - legacy theme fixture text inside modal demo components
- active-view static UI coverage test now passes with zero uncovered labels under the audited active roots

Package 5 verification:
- `php -l lang/ar/autoview.php`
- `php -l lang/ar/autowords.php`
- `php -l tests/Feature/Localization/StaticViewTranslationCoverageTest.php`
- `php artisan view:cache`
  - result: Blade templates cached successfully
- `php artisan test tests/Feature/Localization/StaticHtmlTranslationMiddlewareTest.php tests/Feature/Localization/StaticViewTranslationCoverageTest.php`
  - result: 3 passed, 10 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter='arabic_prefixed|accounting_only_tenant_can_use_workspace_without_other_products|active_tenant_admin_can_log_in_and_open_dashboard'`
  - result: 4 passed, 52 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Admin/ProductCrudTest.php tests/Feature/Admin/Tenants/AdminTenantsIndexTest.php tests/Feature/Admin/Plans/AdminPlanFeatureStorageTest.php`
  - result: 24 passed, 209 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Portal/CustomerPortalBillingOptionsTest.php --filter='workspace_routes_are_canonical|portal_returns_to_neutral_state|trial_workspace_without_live_stripe_subscription'`
  - result: 3 passed, 15 assertions
- `git diff --check`

Final full-view translation package count:
- completed: 5 of 5
- remaining: 0 of 5

Professional Accounting Roadmap progress:
1. Standalone Accounting Product Readiness - completed
2. First-Time Setup Wizard - completed
3. General Ledger UX Simplification - completed
4. IFRS-Ready Chart Of Accounts And Mapping Layer - completed
5. Financial Statement Builder And Notes Foundation - completed
6. Advanced Period Close And Adjustments - completed
7. Tax/VAT Compliance Expansion - completed
8. Multi-Currency And Exchange Revaluation - completed
9. Import/Export, Audit Evidence, And Accountant Review Pack - completed
10. Production Hardening, Permissions Matrix, And Market Acceptance - completed

Persistent accounting rules:
- For future accounting changes, keep journals as the accounting source of truth.
- Continue to avoid `php artisan route:cache`.
- do not reopen integration architecture unless a real blocker appears

Next implementation package:
- none in the current multilingual package pass
- primary language: English
- second language: Arabic
- existing package/library: `mcamara/laravel-localization`
- completed multilingual packages: 6 of 6
- remaining multilingual packages: 0 of 6

## 15.2.1) Accounting System Current State Before Next Chat
This section is the detailed handoff for continuing accounting work in a fresh AI session.

The accounting product is now a real tenant runtime module, not only a placeholder integration target.
It is still intentionally scoped inside the shared tenant workspace and must keep integrating with the
other subscribed products through the workspace integration contract layer.

Current accounting foundations already completed:
- runtime General Ledger module exists inside Tenant Admin
- accounting product can be activated beside automotive service and parts inventory
- work-order completion can create accounting handoff events
- General Ledger can review and post accounting events into journals
- journal entries and journal lines are tenant-owned records
- posting groups map source revenue/receivable accounts
- manual journal entries can be created when balanced
- posted journals can be reversed through explicit reversal entries
- trial balance is journal-driven
- revenue summary is journal-driven
- profit and loss statement is journal-driven
- balance sheet is journal-driven
- tax/VAT settings exist
- tax summary is journal-driven
- customer receivables workflow exists
- customer payments settle posted receivables
- payment deposit batches support reconciliation grouping
- deposit batch corrections use explicit correction logic
- vendor bill workflow exists
- vendor bill posting creates AP journal entries
- vendor bill payments settle payables
- payables aging exists
- receivables aging exists
- account catalog exists
- accounting policies exist for inventory accounting
- inventory movements from parts inventory can be posted into accounting
- period locks exist
- General Ledger includes an accounting audit timeline with filters by event type, actor, source model, and date range
- General Ledger now has a workspace navigation band with clear jumps to posting queue, approvals, period close, reports, receivables, payables, cash, tax, settings, and audit
- audit entries are compliance evidence only; journal entries and journal lines remain the accounting source of truth
- `tenancy:verify-integration-readiness` now checks accounting runtime tables, active workspace products, default accounts, default posting group, default accounting policy, default tax rate, period lock overlaps, and stale integration handoffs
- end-to-end production acceptance now covers automotive, parts inventory, receivables, payables, tax, manual journals, reversals, period locks, and readiness verification in one tenant workflow
- professional accounting roadmap packages 1 through 10 are complete

## 15.2.2) Accounting System Arabic Business Summary
This section exists so a new AI session can explain the accounting system to the project owner, HR, or non-technical stakeholders in Arabic.

### الفكرة العامة
نظام المحاسبة أصبح منتجًا مستقلًا داخل منصة SaaS متعددة العملاء.
العميل يستطيع الاشتراك في المحاسبة وحدها، أو استخدامها مع نظام الورشة ونظام قطع الغيار داخل نفس مساحة العمل.

أهم قاعدة:
- القيود اليومية هي مصدر الحقيقة المحاسبية.
- أي تقرير مالي أو رصيد أو تسوية يجب أن يعتمد على `journal_entries` و`journal_entry_lines`.
- لا يوجد تقرير مالي يجب أن يصبح مصدر أرقام مستقل عن القيود.

### شاشة دفتر الأستاذ العام
`General Ledger` يعني دفتر الأستاذ العام.
هذه هي الشاشة الرئيسية للمحاسبة داخل مساحة العمل.
منها المستخدم يدير:
- الإعداد الأولي
- دليل الحسابات
- القيود اليومية
- الموافقات
- العملاء والتحصيلات
- الموردين والمدفوعات
- البنك والصندوق
- الضرائب
- إقفال الفترات
- فروق العملة
- التقارير
- سجل المراجعة

### مركز الأوامر المالي
داخل `General Ledger` يظهر `Finance Command Center`.
وظيفته إعطاء المستخدم اختصارات واضحة:
- `Setup`: الإعداد
- `Work Queue`: قائمة المراجعة والترحيل
- `Money In`: الأموال الداخلة وفواتير العملاء
- `Money Out`: الأموال الخارجة وفواتير الموردين
- `Bank Review`: البنك والتسويات
- `Reports`: التقارير المالية

### الإعداد أول مرة
`First-Time Setup` يعني إعداد المحاسبة لأول مرة.
المستخدم يحدد:
- العملة الأساسية
- بداية السنة المالية
- نوع الضريبة
- حساب البنك
- حساب الصندوق
- حساب العملاء
- حساب الموردين
- حساب الإيرادات
- حساب المصروفات
- حساب ضريبة المدخلات
- حساب ضريبة المخرجات

بعد الضغط على `Complete Accounting Setup` يتم تجهيز النظام للعمل، بدون إنشاء قيود مالية وهمية.

### دليل الحسابات
`Chart Of Accounts` يعني دليل الحسابات.
هو قائمة الحسابات التي تستخدمها الشركة، مثل:
- البنك
- الصندوق
- العملاء
- الموردين
- الإيرادات
- المصروفات
- الضريبة

كل حساب له نوع:
- أصل
- التزام
- حقوق ملكية
- إيراد
- مصروف

تمت إضافة ربط IFRS للحسابات.
`IFRS Mapping` يعني تصنيف الحسابات حتى تظهر في القوائم المالية بشكل منظم حسب معايير التقارير المالية الدولية.

### القيود اليومية
`Journal Entry` يعني قيد يومية.
القيد هو التسجيل الرسمي لأي عملية مالية.

`Journal Entry Lines` تعني سطور القيد.
كل قيد يتكون من سطور مدينة ودائنة.

`Posting` يعني ترحيل القيد.
بعد الترحيل يؤثر القيد في الأرصدة والتقارير.

`Reversal` يعني عكس القيد.
لو حدث خطأ في قيد مرحل، النظام لا يحذف القيد، بل ينشئ قيدًا عكسيًا للحفاظ على الأثر المحاسبي.

### الموافقات
`Approval Workflow` يعني دورة موافقات.
القيود اليدوية عالية المخاطر لا ترحل مباشرة.
المسار:
- المحاسب ينشئ القيد
- النظام يضعه في انتظار الموافقة
- المسؤول المالي يوافق أو يرفض
- بعد الموافقة يمكن ترحيله

كل موافقة أو رفض أو ترحيل يتم تسجيله في سجل المراجعة.

### فواتير العملاء والتحصيلات
`Receivables` تعني العملاء أو المبالغ المستحقة من العملاء.
النظام يدعم:
- إنشاء فواتير العملاء
- ترحيل الفاتورة إلى قيد محاسبي
- تسجيل تحصيلات العملاء
- تسوية المبالغ المفتوحة
- طباعة كشف حساب العميل
- تقرير أعمار ديون العملاء

`Aging` يعني أعمار الديون.
يوضح الفواتير المفتوحة حسب مدة التأخير.

### الموردون والمدفوعات
`Payables` تعني الموردين أو المبالغ المستحقة على الشركة للموردين.
النظام يدعم:
- تسجيل فواتير الموردين
- ترحيل فاتورة المورد إلى قيد
- تسجيل مدفوعات الموردين
- إشعارات دائنة أو خصومات للموردين
- تقرير أعمار الموردين

### البنك والصندوق
النظام يدعم حسابات البنك والصندوق.
الوظائف الموجودة:
- تسجيل التحصيلات
- تسجيل المدفوعات
- تجميع التحصيلات في دفعات إيداع
- تسوية البنك
- تصحيح المدفوعات أو الإيداعات

`Reconciliation` تعني التسوية أو المطابقة.
مثالها تسوية البنك، أي مقارنة ما في النظام مع كشف البنك.

### الضرائب
`Tax/VAT` يعني الضرائب أو ضريبة القيمة المضافة.
النظام يدعم:
- معدلات الضريبة
- ضريبة المدخلات
- ضريبة المخرجات
- صافي الضريبة المستحقة
- إعداد إقرار ضريبي
- الموافقة على الإقرار

`Tax Filing` يعني الإقرار الضريبي لفترة معينة.
أرقام الضريبة يجب أن تظل مبنية على القيود.

### إقفال الفترات
`Period Close` يعني إقفال فترة محاسبية مثل شهر أو سنة.
النظام يدعم:
- بدء مراجعة الإقفال
- قائمة تحقق قبل القفل
- تسويات الإقفال
- مراجعة التسويات
- قفل الفترة

بعد قفل الفترة لا يتم تعديلها مباشرة.
أي تصحيح يجب أن يتم بقيد تصحيحي في فترة مفتوحة.

### تسويات الإقفال
`Period Close Adjustments` تعني تسويات الإقفال.
أمثلة:
- مصروف مستحق
- إعادة تصنيف
- تصحيح
- قيد إقفال

كل تسوية إقفال يجب أن تنشئ قيدًا محاسبيًا حقيقيًا، وتحتاج مراجعة قبل الإقفال النهائي.

### العملات الأجنبية
`FX Revaluation` يعني إعادة تقييم العملات الأجنبية.
النظام يدعم:
- أسعار صرف
- إعادة تقييم رصيد عملة أجنبية
- إنشاء قيد ربح أو خسارة فروق عملة

`Exchange Rates` يعني أسعار الصرف.
يتم استخدامها لحساب فرق العملة.

### التقارير المالية
النظام يدعم تقارير:
- دفتر اليومية
- ميزان المراجعة
- قائمة الأرباح والخسائر
- الميزانية العمومية
- ملخص الإيرادات
- ملخص الضريبة
- أعمار ديون العملاء
- أعمار الموردين
- تسوية البنك
- ملف مراجعة للمحاسب

`Trial Balance` يعني ميزان المراجعة.
`Profit And Loss` يعني قائمة الأرباح والخسائر.
`Balance Sheet` يعني الميزانية العمومية.
`Accountant Review Pack` يعني ملف مراجعة للمحاسب، يجمع الأدلة والملخصات المهمة للمراجعة.

### سجل المراجعة
`Audit Trail` يعني سجل المراجعة.
يسجل:
- من أنشأ القيد
- من وافق
- من رفض
- من رحل
- من عكس قيد
- من سجل ضريبة
- من قفل فترة
- من أجرى تسوية

وظيفته الرقابة ومنع التلاعب وتسهيل المراجعة الداخلية والخارجية.

### الصلاحيات
النظام يدعم صلاحيات محاسبية.
أمثلة:
- مستخدم مشاهدة فقط
- مستخدم ينشئ قيود
- مستخدم يوافق على القيود
- مستخدم يرحل القيود
- مستخدم يقفل الفترات
- مستخدم يصدر التقارير

إذا لم يملك المستخدم صلاحية، الزر لا يظهر له في الواجهة.

### التكامل مع الأنظمة الأخرى
إذا كان العميل مشتركًا في نظام الورشة:
- يمكن إرسال أمر الشغل المكتمل إلى المحاسبة
- المحاسب يراجعه
- ثم يرحله إلى قيد

إذا كان العميل مشتركًا في نظام قطع الغيار:
- يمكن ترحيل حركات المخزون محاسبيًا
- يمكن عكس أثر المخزون وتكلفة البضاعة في القيود

إذا كان العميل مشتركًا في المحاسبة فقط:
- يستطيع استخدام المحاسبة منفردة بدون الورشة أو قطع الغيار

### أسماء الجداول ووظائفها
`journal_entries`: جدول القيود اليومية. يحفظ رأس القيد مثل الرقم والتاريخ والحالة والإجماليات.
`journal_entry_lines`: جدول سطور القيود. يحفظ الحسابات المدينة والدائنة داخل كل قيد.
`accounting_accounts`: جدول دليل الحسابات. يحفظ الحسابات وأنواعها وتصنيفها.
`accounting_posting_groups`: جدول قواعد الترحيل. يحدد الحسابات الافتراضية التي يستخدمها النظام عند الترحيل.
`accounting_audit_entries`: جدول سجل المراجعة. يحفظ من فعل ماذا ومتى.
`accounting_setup_profiles`: جدول إعداد المحاسبة لأول مرة. يحفظ العملة والسنة المالية والحسابات الافتراضية.
`accounting_tax_rates`: جدول معدلات الضرائب. يحفظ نسبة الضريبة وحساباتها.
`accounting_tax_filings`: جدول الإقرارات الضريبية. يحفظ الإقرار وفترته وحالته.
`accounting_bank_accounts`: جدول حسابات البنك والصندوق.
`accounting_payments`: جدول تحصيلات العملاء.
`accounting_deposit_batches`: جدول دفعات الإيداع البنكية.
`accounting_invoices`: جدول فواتير العملاء.
`accounting_vendor_bills`: جدول فواتير الموردين.
`accounting_vendor_bill_payments`: جدول مدفوعات الموردين.
`accounting_vendor_bill_adjustments`: جدول تعديلات فواتير الموردين والإشعارات الدائنة.
`accounting_period_locks`: جدول إقفال الفترات.
`accounting_period_close_adjustments`: جدول تسويات الإقفال.
`accounting_statement_notes`: جدول ملاحظات وإفصاحات القوائم المالية.
`accounting_exchange_rates`: جدول أسعار الصرف.
`accounting_fx_revaluations`: جدول إعادة تقييم العملات الأجنبية.

### جملة مختصرة للاجتماعات
تم بناء نظام محاسبة مستقل ومتكامل داخل منصة SaaS متعددة العملاء. النظام يدعم دليل الحسابات، القيود اليومية، الترحيل، الموافقات، فواتير العملاء، فواتير الموردين، المدفوعات، البنك، الضرائب، إقفال الفترات، فروق العملة، التقارير المالية، وسجل مراجعة كامل. أهم نقطة أن كل الأرقام المالية مبنية على القيود اليومية، وليس على أرقام منفصلة، مما يجعل النظام قابلًا للمراجعة والتوسع والتكامل مع باقي الأنظمة.

## 15.2.3) Additional Accounting Technical Details
- fiscal close posting controls now block posting inside locked periods
- overlapping period locks are rejected
- accounting permission gates now protect sensitive actions:
  - manual journal create/post/approve
  - source event posting
  - inventory valuation posting
  - vendor bill posting
  - vendor and customer payments
  - deposit batch creation/correction
  - journal reversal
  - period locking
  - account and tax management
  - financial report export
- high-risk manual journals now require approval before posting
- General Ledger exposes pending manual journal approvals
- account catalog now blocks unsafe mutation of accounts used by posted journal lines
- used accounts can be deactivated but not deleted
- unused accounts can be deleted
- normal balance is validated against account type
- posting into inactive or unknown accounts is rejected
- General Ledger exposes account catalog search/type/status filters
- accounting periods now support lifecycle states:
  - open
  - closing
  - locked
  - archived
- General Ledger exposes fiscal close readiness checklist
- period locking is blocked when checklist blockers exist unless a controlled override is recorded
- archived periods continue to block posting through `assertPeriodOpen`
- General Ledger now exposes posting-control summary state
- integration readiness verification checks required accounting runtime tables
- bank/cash accounts are controlled tenant records linked to account catalog codes
- customer payments, vendor payments, and deposit batches now select configured active bank/cash accounts
- bank/cash account balances shown in General Ledger are derived from posted journal lines
- cash reconciliation now tracks matched bank date/reference on direct receipts, deposit batches, and vendor payments
- deposit batches and vendor payments can be marked reconciled from General Ledger
- reconciled deposit batches cannot be directly corrected; reconciled customer payments cannot be directly voided
- General Ledger exposes unreconciled receipts, unreconciled deposits, unreconciled vendor payments, and period reconciled total
- formal AR invoices now exist separately from source accounting events
- posting an AR invoice creates an accounting event and a balanced journal entry
- AR invoice payments use the existing customer payment workflow against the invoice accounting event
- customer statements and invoice print output use formal invoice numbers when available
- work-order completion still creates accounting handoff events without requiring invoice creation
- vendor bills can be tied to active supplier catalog records from General Ledger
- vendor bill attachment metadata is stored without introducing file upload architecture
- payables summary and filters expose due-soon/overdue state
- vendor bill credit notes post explicit AP adjustment journals
- payables aging and open payable amounts include posted credit note adjustments

Important accounting runtime tables currently expected in tenant DB:
- `accounting_posting_groups`
- `journal_entries`
- `journal_entry_lines`
- `accounting_accounts`
- `accounting_bank_accounts`
- `accounting_period_locks`
- `accounting_policies`
- `accounting_audit_entries`
- `accounting_invoices`
- `accounting_invoice_lines`
- `accounting_payments`
- `accounting_deposit_batches`
- `accounting_vendor_bills`
- `accounting_vendor_bill_adjustments`
- `accounting_vendor_bill_payments`
- `accounting_tax_rates`
- plus cross-product handoff/runtime tables already used by the workspace integration layer

Important accounting source-of-truth rule:
- journals remain the accounting source of truth
- financial statements must be calculated from posted journal lines
- reports must not create side ledgers that can drift from journals
- corrections after posting must happen by reversal/correction entries, not silent mutation
- fiscal close must block risky posting actions inside locked periods

Important cross-product integration state:
- `automotive_service -> accounting` is active through `work_order.completed`
- `parts_inventory -> accounting` is active through `stock_movement.valued`
- accounting can receive source events and turn them into journal entries
- the integration architecture is considered closed enough for now
- do not reopen integration architecture unless a concrete blocker appears
- future products must integrate by registering contracts/events and posting handoffs, not by hardcoding into accounting

Known production/deployment notes from this work phase:
- `php artisan route:cache` must not be used
- after deploying accounting migrations, run central migrations and tenant migrations separately:
  - `php artisan migrate --force`
  - `php artisan tenants:migrate --force`
- verify tenant readiness with:
  - `php artisan tenancy:verify-integration-readiness --tenant=TENANT_ID`
- if tenant domain is intentionally missing, the app should return a controlled 404 instead of a raw tenant-identification 500
- spareparts production app may require PHP 7.4 FPM separately from this Laravel 10 workspace; do not assume all three deployed apps use the same PHP runtime

## 15.2.2) Accounting Completion Roadmap
The following packages should be completed in order to finish the accounting system to a production-ready level.

### Package 1 - Accounting Role Permissions And Approval Controls
Status:
- completed

Completed behavior:
- tenant users can now carry `accounting_role` and explicit `accounting_permissions`
- users with `accounting_permissions = null` keep legacy full accounting access for backwards compatibility
- users with an explicit empty permission set are blocked from sensitive accounting actions
- General Ledger controller actions now enforce permissions for:
  - manual journal creation/posting/approval
  - source accounting event posting
  - inventory valuation posting
  - vendor bill posting and vendor bill payments
  - customer payment recording/voiding
  - deposit batch creation/correction
  - journal reversal
  - period locking
  - account/policy management
  - tax-rate management
  - report exports
- manual journals over the approval threshold, or explicitly marked for approval, are created as `pending_approval` and are not posted
- approved manual journals are posted only through the explicit post-approved workflow
- rejected manual journals remain unposted
- pending approval queue is visible in General Ledger
- journal detail pages expose approval/rejection/post-approved actions when the user has permission
- audit entries are recorded for:
  - `manual_journal_submitted_for_approval`
  - `manual_journal_approved`
  - `manual_journal_rejected`
  - `manual_journal_posted_after_approval`

Important files added/changed:
- `database/migrations/tenant/2026_04_21_090000_add_accounting_permissions_and_journal_approval_controls.php`
- `app/Services/Automotive/AccountingPermissionService.php`
- `app/Services/Automotive/AccountingRuntimeService.php`
- `app/Http/Controllers/Automotive/Admin/WorkspaceModuleController.php`
- `app/Models/User.php`
- `app/Models/JournalEntry.php`
- `routes/products/automotive/admin.php`
- `resources/views/automotive/admin/modules/show.blade.php`
- `resources/views/automotive/admin/modules/journal-entry-show.blade.php`
- `tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`

Verification:
- `php -l app/Services/Automotive/AccountingRuntimeService.php`
  - result: passed
- `php -l app/Http/Controllers/Automotive/Admin/WorkspaceModuleController.php`
  - result: passed
- `php -l app/Services/Automotive/AccountingPermissionService.php`
  - result: passed
- `php -l database/migrations/tenant/2026_04_21_090000_add_accounting_permissions_and_journal_approval_controls.php`
  - result: passed
- `php -l tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`
  - result: passed
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter='high_risk_manual_journals|requires_permissions'`
  - result: 2 passed, 36 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`
  - result: 24 passed, 566 assertions

Goal:
- add explicit authority boundaries before adding more accounting power
- prevent every tenant admin from being able to post, reverse, close, or correct sensitive accounting records without permission

Required scope:
- define accounting capability checks for:
  - creating manual journals
  - posting source events
  - posting inventory valuation movements
  - posting vendor bills
  - recording vendor bill payments
  - recording customer payments
  - creating deposit batches
  - correcting deposit batches
  - reversing journal entries
  - locking accounting periods
  - managing account catalog
  - managing tax rates
  - exporting financial reports
- add an approval state for high-risk manual journals:
  - draft
  - pending approval
  - approved
  - posted
  - rejected or void where appropriate
- require approval before posting high-risk manual journals
- keep low-risk source postings configurable if possible, but do not overbuild roles before checking current user/permission model
- expose pending approvals inside General Ledger
- add audit entries for:
  - submitted for approval
  - approved
  - rejected
  - posted after approval
- tests must prove unauthorized or unapproved posting is blocked

Files likely involved:
- `app/Services/Automotive/AccountingRuntimeService.php`
- `app/Http/Controllers/Automotive/Admin/WorkspaceModuleController.php`
- `resources/views/automotive/admin/modules/show.blade.php`
- `routes/products/automotive/admin.php`
- tenant migrations for approval fields/table if needed
- `tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`

Acceptance criteria:
- high-risk manual journals cannot be posted until approved
- period lock creation requires the appropriate control path
- reversal/correction flows are still explicit
- existing accounting tests keep passing

### Package 2 - Accounting Chart Of Accounts Management Hardening
Status:
- completed

Completed behavior:
- account catalog creation/update validates normal balance against account type:
  - asset/expense require debit normal balance
  - liability/equity/revenue require credit normal balance
- account catalog supports active/inactive state through an explicit deactivation route
- accounts used by posted/reversed journal lines cannot be renamed, reclassified, or deleted
- unused accounts can be deleted
- active account lists power posting forms and datalist options
- General Ledger account catalog can be filtered by:
  - search term
  - account type
  - active/inactive status
- posting flows now reject inactive or unknown accounts instead of silently creating accounts:
  - manual journals
  - source accounting events
  - inventory valuation posting
  - vendor bill creation/posting
  - vendor bill payments
  - customer payments
  - deposit batches
  - posting groups
  - inventory policies
  - tax rates
- default account bootstrapping remains idempotent for default accounts only
- journals remain the source of truth; reports still derive from journal lines

Important files changed:
- `app/Services/Automotive/AccountingRuntimeService.php`
- `app/Http/Controllers/Automotive/Admin/WorkspaceModuleController.php`
- `routes/products/automotive/admin.php`
- `resources/views/automotive/admin/modules/show.blade.php`
- `tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`

Verification:
- `php -l app/Services/Automotive/AccountingRuntimeService.php`
  - result: passed
- `php -l app/Http/Controllers/Automotive/Admin/WorkspaceModuleController.php`
  - result: passed
- `php -l routes/products/automotive/admin.php`
  - result: passed
- `php -l tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`
  - result: passed
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter='chart_of_accounts_hardening|configured_inventory_policy_accounts|account_catalog_period_locks'`
  - result: 3 passed, 87 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter='accounting_runtime|chart_of_accounts_hardening'`
  - result: 10 passed, 358 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`
  - result: 25 passed, 609 assertions

Goal:
- make account catalog safe enough for real tenant usage

Required scope:
- add stronger account catalog validation:
  - unique account code
  - active/inactive state
  - block deletion of accounts used by posted journal lines
  - allow deactivation instead of deletion
  - validate normal balance against account type
- add account categories or statement grouping if needed:
  - asset
  - liability
  - equity
  - revenue
  - expense
  - contra accounts if supported
- add UI for filtering/searching accounts
- keep default account bootstrapping idempotent
- ensure posting refuses inactive or unknown accounts
- update tests to cover inactive account posting rejection

Acceptance criteria:
- tenant cannot break historical journals by deleting used accounts
- posting into inactive accounts is blocked
- chart of accounts remains usable for reports

### Package 3 - Accounting Fiscal Period Lifecycle
Status:
- completed

Completed behavior:
- `accounting_period_locks` now carries fiscal close lifecycle metadata:
  - `closing_started_by`
  - `closing_started_at`
  - `close_checklist`
  - `lock_override`
  - `lock_override_reason`
  - `archived_by`
  - `archived_at`
- supported period lifecycle states now include:
  - `open` as implicit state when no period record exists
  - `closing`
  - `locked`
  - `archived`
- General Ledger now shows close readiness for the current period
- close checklist includes blockers for:
  - unposted accounting events
  - unposted inventory valuation movements
  - draft vendor bills
  - open receivables
  - unreconciled customer payments
  - unapproved manual journals
- tenant users can start a close review before locking a period
- period locking is blocked when close checklist blockers exist
- period locking can proceed only with an explicit controlled override and override reason
- locked periods can be archived
- `assertPeriodOpen` now treats both `locked` and `archived` periods as closed to posting
- audit entries are recorded for:
  - `period_close_started`
  - `period_locked`
  - `period_archived`

Important files added/changed:
- `database/migrations/tenant/2026_04_21_100000_add_fiscal_close_lifecycle_to_accounting_period_locks_table.php`
- `app/Models/AccountingPeriodLock.php`
- `app/Services/Automotive/AccountingRuntimeService.php`
- `app/Http/Controllers/Automotive/Admin/WorkspaceModuleController.php`
- `routes/products/automotive/admin.php`
- `resources/views/automotive/admin/modules/show.blade.php`
- `tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`

Verification:
- `php -l app/Services/Automotive/AccountingRuntimeService.php`
  - result: passed
- `php -l app/Http/Controllers/Automotive/Admin/WorkspaceModuleController.php`
  - result: passed
- `php -l app/Models/AccountingPeriodLock.php`
  - result: passed
- `php -l database/migrations/tenant/2026_04_21_100000_add_fiscal_close_lifecycle_to_accounting_period_locks_table.php`
  - result: passed
- `php -l routes/products/automotive/admin.php`
  - result: passed
- `php -l tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`
  - result: passed
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter='fiscal_period_lifecycle|account_catalog_period_locks'`
  - result: 2 passed, 63 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter='accounting_runtime|fiscal_period_lifecycle|chart_of_accounts_hardening'`
  - result: 11 passed, 386 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`
  - result: 26 passed, 637 assertions

Goal:
- turn period locks into a more complete accounting close lifecycle

Required scope:
- introduce fiscal periods if needed:
  - open
  - closing
  - locked
  - archived
- add close checklist summary:
  - unposted accounting events
  - unposted inventory movements
  - draft vendor bills
  - unpaid/open receivables summary
  - open deposit batches or unreconciled payments
  - unapproved manual journals
- block period locking if required close checklist items are incomplete, unless explicitly allowed by a controlled override
- keep no-silent-mutation rule after locked period
- expose close readiness inside General Ledger

Acceptance criteria:
- user can see why a period is not ready to lock
- locked periods remain protected by `assertPeriodOpen`
- close flow does not bypass reversal/correction rules

### Package 4 - Accounting Bank Accounts And Cash Management
Status:
- completed

Goal:
- make cash and bank handling explicit instead of free-text cash account fields only

Completed behavior:
- cash/bank accounts are controlled tenant records
- each bank/cash account is linked to an active account catalog code
- default Cash On Hand and Bank Account records are created when needed for legacy-safe operation
- customer payments choose a configured bank/cash account and post the cash side to that account code
- vendor payments choose a configured bank/cash account and post the cash side to that account code
- deposit batches choose a configured bank/cash account and keep the selected account relationship
- payment and deposit workflows no longer accept arbitrary unknown cash account strings from the UI
- General Ledger shows Bank & Cash Accounts plus Cash Balances
- displayed cash/bank balances are calculated from posted journal lines, keeping journals as source of truth

Important files added/changed:
- `database/migrations/tenant/2026_04_21_110000_create_accounting_bank_accounts_table.php`
- `app/Models/AccountingBankAccount.php`
- `app/Models/AccountingPayment.php`
- `app/Models/AccountingDepositBatch.php`
- `app/Models/AccountingVendorBillPayment.php`
- `app/Services/Automotive/AccountingRuntimeService.php`
- `app/Http/Controllers/Automotive/Admin/WorkspaceModuleController.php`
- `resources/views/automotive/admin/modules/show.blade.php`
- `routes/products/automotive/admin.php`
- `tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`

Verification:
- `php -l app/Services/Automotive/AccountingRuntimeService.php`
  - result: passed
- `php -l app/Http/Controllers/Automotive/Admin/WorkspaceModuleController.php`
  - result: passed
- `php -l app/Models/AccountingBankAccount.php`
  - result: passed
- `php -l database/migrations/tenant/2026_04_21_110000_create_accounting_bank_accounts_table.php`
  - result: passed
- `php -l resources/views/automotive/admin/modules/show.blade.php`
  - result: passed
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter='bank_accounts_control|record_customer_payment|deposit_batch|vendor_bill_to_payables'`
  - result: 4 passed, 215 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`
  - result: 27 passed, 657 assertions

### Package 5 - Accounting Reconciliation Workflow Completion
Status:
- completed

Goal:
- complete reconciliation beyond basic deposit batching

Completed behavior:
- finance user can tell what cash activity has not been reconciled
- reconciled activity is harder to modify directly
- reports remain journal-driven
- reconciliation status/date/reference tracking now exists for:
  - direct customer receipts
  - deposit batches
  - vendor bill payments
- deposit batches can be marked reconciled from the deposit detail page
- direct customer receipts can be marked reconciled when they are not part of a deposit batch
- vendor bill payments can be marked reconciled from General Ledger
- General Ledger reconciliation summary now shows:
  - unreconciled receipts
  - unreconciled deposits
  - unreconciled vendor payments
  - reconciled total for the current period
- bank reconciliation print report now includes deposit batches, direct receipts, vendor payments, and bank match metadata
- reconciled deposit batches are blocked from direct correction
- reconciled direct customer payments are blocked from direct voiding
- reconciliation actions write audit entries

Important files added/changed:
- `database/migrations/tenant/2026_04_21_120000_add_cash_reconciliation_controls_to_accounting_tables.php`
- `app/Models/AccountingPayment.php`
- `app/Models/AccountingDepositBatch.php`
- `app/Models/AccountingVendorBillPayment.php`
- `app/Services/Automotive/AccountingPermissionService.php`
- `app/Services/Automotive/AccountingRuntimeService.php`
- `app/Http/Controllers/Automotive/Admin/WorkspaceModuleController.php`
- `resources/views/automotive/admin/modules/show.blade.php`
- `resources/views/automotive/admin/modules/accounting-deposit-batch-show.blade.php`
- `resources/views/automotive/admin/modules/accounting-bank-reconciliation-print.blade.php`
- `routes/products/automotive/admin.php`
- `tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`

Verification:
- `php -l app/Services/Automotive/AccountingRuntimeService.php`
  - result: passed
- `php -l app/Http/Controllers/Automotive/Admin/WorkspaceModuleController.php`
  - result: passed
- `php -l database/migrations/tenant/2026_04_21_120000_add_cash_reconciliation_controls_to_accounting_tables.php`
  - result: passed
- `php -l tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`
  - result: passed
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter='reconciliation_workflow|bank_accounts_control|deposit_batch|vendor_bill_to_payables'`
  - result: 4 passed, 204 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`
  - result: 28 passed, 699 assertions

### Package 6 - Accounting AR Invoicing Foundation
Status:
- completed

Goal:
- separate formal customer invoicing from source accounting events where needed

Completed behavior:
- customer-facing invoices can exist without breaking current work-order accounting events
- invoice posting creates balanced journals
- invoice payments continue settling receivables
- General Ledger can create draft customer invoices with:
  - invoice number
  - customer
  - issue date
  - due date
  - line description/account/quantity/unit price
  - tax amount
  - status
- posting an invoice creates a linked `accounting_events` record and posts it to journals
- invoice line revenue and output tax are credited through journal lines
- customer payments continue to use the existing receivable settlement flow against the invoice accounting event
- paid invoice events update the formal invoice status to `paid`
- invoice print output uses the formal invoice number and invoice lines when the event came from an AR invoice
- customer statements show formal invoice numbers when available
- work-order accounting handoff behavior remains unchanged; work orders can still post accounting events without creating formal invoices

Important files added/changed:
- `database/migrations/tenant/2026_04_22_090000_create_accounting_invoices_table.php`
- `app/Models/AccountingInvoice.php`
- `app/Models/AccountingInvoiceLine.php`
- `app/Services/Automotive/AccountingPermissionService.php`
- `app/Services/Automotive/AccountingRuntimeService.php`
- `app/Http/Controllers/Automotive/Admin/WorkspaceModuleController.php`
- `resources/views/automotive/admin/modules/show.blade.php`
- `routes/products/automotive/admin.php`
- `tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`

Verification:
- `php -l app/Models/AccountingInvoice.php`
  - result: passed
- `php -l app/Models/AccountingInvoiceLine.php`
  - result: passed
- `php -l database/migrations/tenant/2026_04_22_090000_create_accounting_invoices_table.php`
  - result: passed
- `php -l app/Services/Automotive/AccountingRuntimeService.php`
  - result: passed
- `php -l app/Http/Controllers/Automotive/Admin/WorkspaceModuleController.php`
  - result: passed
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter='ar_invoices|record_customer_payment|create_posting_group|bank_accounts_control'`
  - result: 4 passed, 139 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`
  - result: 29 passed, 739 assertions

### Package 7 - Accounting AP Enhancements
Status:
- completed

Goal:
- make vendor bills/payables practical beyond the current minimal workflow

Completed behavior:
- vendor bills can be tied to known suppliers
- posted AP records cannot be silently rewritten
- payables aging remains accurate
- General Ledger vendor bill creation can select active supplier catalog records
- vendor bills support attachment metadata:
  - attachment name
  - attachment reference
  - attachment URL
- General Ledger exposes payables due filters:
  - overdue
  - due soon
- payables summary now includes due-soon count and amount
- vendor bill credit notes are posted through explicit AP adjustment journals
- credit notes debit Accounts Payable and credit expense/input tax accounts as applicable
- vendor bill open amounts, payment validation, and payables aging account for posted credit notes
- overpayment after a credit note is blocked by the adjusted open payable amount

Important files added/changed:
- `database/migrations/tenant/2026_04_22_100000_add_ap_enhancements_to_accounting_vendor_bills.php`
- `app/Models/AccountingVendorBill.php`
- `app/Models/AccountingVendorBillAdjustment.php`
- `app/Services/Automotive/AccountingPermissionService.php`
- `app/Services/Automotive/AccountingRuntimeService.php`
- `app/Http/Controllers/Automotive/Admin/WorkspaceModuleController.php`
- `resources/views/automotive/admin/modules/show.blade.php`
- `routes/products/automotive/admin.php`
- `tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`

Verification:
- `php -l app/Models/AccountingVendorBillAdjustment.php`
  - result: passed
- `php -l database/migrations/tenant/2026_04_22_100000_add_ap_enhancements_to_accounting_vendor_bills.php`
  - result: passed
- `php -l app/Services/Automotive/AccountingRuntimeService.php`
  - result: passed
- `php -l app/Http/Controllers/Automotive/Admin/WorkspaceModuleController.php`
  - result: passed
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter='ap_enhancements|vendor_bill_to_payables|reconciliation_workflow'`
  - result: 3 passed, 167 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`
  - result: 30 passed, 772 assertions

### Package 8 - Accounting Inventory Costing Controls
Status:
- completed

Goal:
- strengthen inventory accounting from parts movements

Required scope:
- validate valuation source for stock movements
- define supported costing method for now:
  - current implementation uses available movement value based on stock item cost context
  - do not imply FIFO/weighted average unless implemented
- add clear policy UI labels for inventory asset, COGS, adjustment, and offset accounts
- add tests for movement types that should and should not post
- ensure parts inventory and accounting remain decoupled through integration handoffs

Acceptance criteria:
- inventory journals are predictable
- unsupported valuation cases are skipped or blocked with clear reason
- readiness command still proves parts-accounting integration requirements

Completed behavior:
- stock movement valuation is now explicit and centralized through accounting runtime valuation details
- supported costing method is documented as current product cost at posting time:
  - method key: `current_product_cost`
  - valuation source: `products.cost_price`
  - FIFO and weighted average are not implied or exposed as implemented
- General Ledger policy UI labels now clearly identify:
  - Inventory Asset Account
  - Inventory Adjustment Offset Account
  - Inventory Adjustment Expense Account
  - COGS Account
- Inventory Valuation Review shows method/source labels and uses the centralized valuation details instead of duplicating arithmetic in Blade
- posting validates:
  - movement type must be `opening`, `adjustment_in`, or `adjustment_out`
  - quantity must be positive
  - current product cost must be positive
  - resulting valuation amount must be positive
- transfer movements are blocked from accounting posting and recorded as skipped integration handoffs when attempted directly
- posted inventory valuation handoff payloads now record valuation method and valuation source
- journals remain the accounting source of truth; parts inventory stays decoupled through `workspace_integration_handoffs`

Important files changed:
- `app/Services/Automotive/AccountingRuntimeService.php`
- `resources/views/automotive/admin/modules/show.blade.php`
- `tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`

Verification:
- `php -l app/Services/Automotive/AccountingRuntimeService.php`
  - result: no syntax errors
- `php -l resources/views/automotive/admin/modules/show.blade.php`
  - result: no syntax errors
- `php -l tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`
  - result: no syntax errors
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter='inventory_valuation'`
  - result: 2 passed, 38 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter='inventory'`
  - result: 6 passed, 79 assertions

### Package 9 - Accounting Report Export Polish
Status:
- completed

Goal:
- make all accounting reports usable by finance users

Required scope:
- unify CSV export headers and date filters
- add printable views where missing
- verify:
  - journal entries
  - trial balance
  - revenue summary
  - tax summary
  - profit and loss
  - balance sheet
  - receivables aging
  - payables aging
  - reconciliation summary
- add date range indicators to print views
- keep exports read-only

Acceptance criteria:
- every major accounting report can be exported or printed
- report totals match posted journal lines or source-specific summaries where explicitly documented

Completed behavior:
- General Ledger export controller now supports CSV and print coverage for:
  - journal entries
  - trial balance
  - revenue summary
  - tax summary
  - profit and loss
  - balance sheet
  - customer payments
  - receivables aging
  - payables aging
  - bank reconciliation
  - reconciliation summary
- bank reconciliation now supports CSV export in addition to print
- receivables aging, payables aging, and reconciliation summary have read-only CSV/print routes
- report titles are normalized for print views
- financial statement CSV rows now keep consistent `Section, Account Code, Account Name, Amount` columns
- bank reconciliation print now shows period, reconciliation status, and account filters
- General Ledger export toolbar includes the new export/print actions
- reports remain read-only and continue to derive accounting totals from posted journal lines or documented source-specific summaries

Important files changed:
- `app/Http/Controllers/Automotive/Admin/WorkspaceModuleController.php`
- `resources/views/automotive/admin/modules/show.blade.php`
- `resources/views/automotive/admin/modules/accounting-bank-reconciliation-print.blade.php`
- `tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`

Verification:
- `php -l app/Http/Controllers/Automotive/Admin/WorkspaceModuleController.php`
  - result: no syntax errors
- `php -l resources/views/automotive/admin/modules/show.blade.php`
  - result: no syntax errors
- `php -l resources/views/automotive/admin/modules/accounting-bank-reconciliation-print.blade.php`
  - result: no syntax errors
- `php -l tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`
  - result: no syntax errors
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter='exports_reports|reconciliation_workflow'`
  - result: 2 passed, 98 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter='accounting_runtime|exports_reports|vendor_bill_to_payables|reconciliation_workflow|invoice'`
  - result: 11 passed, 434 assertions

### Package 10 - Accounting Audit Trail And Compliance Review
Status:
- completed

Goal:
- make accounting activity traceable and reviewable

Required scope:
- standardize audit event names and payloads
- expose audit filters:
  - event type
  - actor
  - date range
  - source model
- audit high-risk events:
  - posting
  - reversal
  - approval
  - rejection
  - period lock
  - payment void
  - deposit correction
  - tax/account policy changes
- ensure audit entries do not become the accounting source of truth; they are evidence/logging only

Acceptance criteria:
- finance/admin users can review who did what and when
- tests cover important audit event creation

Completed implementation:
- standardized accounting audit payloads with `event_type`, source model/id, actor id, recorded timestamp, and explicit journal-source-of-truth marker
- added filtered audit review inside General Ledger by event type, actor, source model, and audit date range
- loaded audit actors and source model options for finance/admin review
- added audit entries for chart of account saves, accounting policy changes, and tax rate changes
- kept audit entries as compliance evidence only; journal entries and journal lines remain the accounting source of truth

Files changed:
- `app/Models/AccountingAuditEntry.php`
- `app/Services/Automotive/AccountingRuntimeService.php`
- `app/Http/Controllers/Automotive/Admin/WorkspaceModuleController.php`
- `resources/views/automotive/admin/modules/show.blade.php`
- `tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`
- `PROJECT_AI_CONTEXT.md`

Verification:
- `php -l app/Services/Automotive/AccountingRuntimeService.php`
  - result: no syntax errors
- `php -l app/Http/Controllers/Automotive/Admin/WorkspaceModuleController.php`
  - result: no syntax errors
- `php -l app/Models/AccountingAuditEntry.php`
  - result: no syntax errors
- `php -l resources/views/automotive/admin/modules/show.blade.php`
  - result: no syntax errors
- `php -l tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`
  - result: no syntax errors
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter='high_risk_manual_journals|vendor_bill_to_payables|configured_inventory_policy_accounts'`
  - result: 3 passed, 154 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter='accounting_runtime|high_risk_manual_journals|permissions|vendor_bill_to_payables|configured_inventory_policy_accounts|reconciliation_workflow'`
  - result: 11 passed, 439 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`
  - result: 33 passed, 839 assertions

### Package 11 - Accounting Data Quality And Readiness Command Expansion
Status:
- completed

Goal:
- make deployment and tenant readiness checks detect accounting misconfiguration before users hit runtime errors

Required scope:
- expand `tenancy:verify-integration-readiness` accounting checks:
  - required tables
  - default accounts
  - default posting group
  - default accounting policy
  - default tax rate
  - integration handoff tables
  - active accounting workspace product
- add warnings for:
  - missing default accounts
  - inactive required accounts
  - overlapping period locks
  - unposted handoffs older than a threshold if feasible
- keep command safe to run in production

Acceptance criteria:
- readiness command can be used before production release
- command reports actionable failures

Completed implementation:
- expanded `tenancy:verify-integration-readiness` with required accounting runtime tables, including bank accounts, invoices, invoice lines, and vendor bill adjustments
- added required default account checks for all current accounting workflows
- added active default posting group, accounting policy, and tax rate checks
- added active accounting workspace product verification
- added actionable warnings for inactive required accounts, overlapping locked/archived accounting periods, and stale pending/failed/retrying handoffs
- kept the command read-only and safe for production verification
- kept journal entries and journal lines as accounting source of truth; readiness checks do not derive balances from audit entries

Files changed:
- `app/Console/Commands/Tenancy/VerifyIntegrationReadinessCommand.php`
- `tests/Feature/Tenancy/VerifyIntegrationReadinessCommandTest.php`
- `PROJECT_AI_CONTEXT.md`

Verification:
- `php -l app/Console/Commands/Tenancy/VerifyIntegrationReadinessCommand.php`
  - result: no syntax errors
- `php -l tests/Feature/Tenancy/VerifyIntegrationReadinessCommandTest.php`
  - result: no syntax errors
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Tenancy/VerifyIntegrationReadinessCommandTest.php`
  - result: 5 passed, 24 assertions

### Package 12 - Accounting UX Consolidation And Navigation
Status:
- completed

Goal:
- make the General Ledger screen manageable after many accounting features have been added

Required scope:
- split General Ledger UI into clearer sections or tabs if local layout patterns support it
- keep critical dashboards visible:
  - posting queue
  - approvals
  - period close
  - financial reports
  - receivables
  - payables
  - tax
  - audit
- avoid creating marketing/landing pages
- do not introduce unrelated theme rewrites
- ensure mobile and desktop layout remains usable

Acceptance criteria:
- accounting UI remains usable as features grow
- no cards inside cards
- no unrelated frontend redesign

Completed implementation:
- added an internal General Ledger workspace navigation band using existing Bootstrap nav styles
- grouped the long General Ledger screen with clear section headers and back-to-top links:
  - posting queue
  - approvals and manual journals
  - period close
  - financial reports
  - receivables
  - payables
  - cash and reconciliation
  - tax
  - accounting settings
  - audit and source activity
- kept all existing cards/forms on the page to avoid breaking accounting workflows or changing posting behavior
- avoided card nesting, landing pages, integration architecture changes, and unrelated theme rewrites
- kept journals as the accounting source of truth

Files changed:
- `resources/views/automotive/admin/modules/show.blade.php`
- `tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`
- `PROJECT_AI_CONTEXT.md`

Verification:
- `php -l resources/views/automotive/admin/modules/show.blade.php`
  - result: no syntax errors
- `php -l tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`
  - result: no syntax errors
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter='accounting_runtime_can_create_posting_group_and_post_event_to_journal'`
  - result: 1 passed, 35 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter='accounting_runtime|high_risk_manual_journals|permissions|vendor_bill_to_payables|configured_inventory_policy_accounts|reconciliation_workflow|invoice'`
  - result: 12 passed, 488 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`
  - result: 33 passed, 848 assertions

### Package 13 - Accounting End-To-End Production Acceptance
Status:
- completed

Goal:
- prove the whole accounting workflow works as one system before calling accounting complete

Required end-to-end scenarios:
- automotive work order completed -> accounting event -> journal posted -> receivable created -> customer payment recorded -> deposit batch created -> reconciliation state visible
- parts stock movement valued -> accounting handoff -> inventory journal posted -> report totals updated
- vendor bill created -> bill posted -> AP created -> vendor payment recorded -> payables aging updated
- tax configured -> taxable vendor bill or revenue event posted -> tax summary updated
- manual journal submitted -> approved -> posted -> financial statements updated
- posted journal reversed in open period -> financial statements updated
- closed period blocks posting and requires correction/reversal in an open period

Acceptance criteria:
- full accounting runtime tests pass
- integration readiness command passes with a real tenant
- UI steps are documented for production smoke testing
- `PROJECT_AI_CONTEXT.md` is updated one final time with accounting completion status

Completed implementation:
- added a production acceptance feature test that proves the accounting runtime works end-to-end in one tenant:
  - automotive work order completion creates an accounting event and posted handoff
  - accounting event posts to journal and receivable
  - customer payment records cash and settles receivable
  - deposit batch groups the receipt and exposes reconciliation state
  - parts stock movement posts an inventory valuation journal
  - taxable vendor bill posts AP, tax input, and expense journals
  - vendor payment settles payables
  - high-risk manual journal requires approval, posts after approval, and reverses in an open period
  - closed period blocks posting into the locked range
  - trial balance, tax summary, payables aging, audit, and readiness verification remain available
- ran readiness verification against the acceptance tenant through `tenancy:verify-integration-readiness --tenant=...`
- confirmed the full tenant admin flow test suite passes after the final accounting acceptance test
- marked the Accounting Completion Roadmap as complete with zero remaining packages

Files changed:
- `tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`
- `PROJECT_AI_CONTEXT.md`

Verification:
- `php -l tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`
  - result: no syntax errors
- `php -l resources/views/automotive/admin/modules/show.blade.php`
  - result: no syntax errors
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter='accounting_end_to_end_production_acceptance_workflows'`
  - result: 1 passed, 85 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter='accounting_runtime|accounting_end_to_end|high_risk_manual_journals|permissions|vendor_bill_to_payables|configured_inventory_policy_accounts|reconciliation_workflow|invoice'`
  - result: 13 passed, 573 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Tenancy/VerifyIntegrationReadinessCommandTest.php`
  - result: 5 passed, 24 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`
  - result: 34 passed, 933 assertions

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

## 15.7) Accounting Runtime Hardening And Exports
Status:
- completed

Why this package was needed:
- the integration layer can now hand events into accounting, but accounting needed operational controls before more products depend on it
- future products should not post financial data into only free-text accounts or into periods that should already be closed

Completed scope:
- added tenant-level accounting control tables:
  - `accounting_accounts`
  - `accounting_period_locks`
  - `accounting_policies`
  - `accounting_audit_entries`
- General Ledger now supports:
  - account catalog creation and default account bootstrapping
  - period locks that block journal posting/reversal for locked dates
  - configurable inventory accounting policies for inventory asset, adjustment, and COGS accounts
  - audit timeline for period locks, manual journals, event posting, inventory valuation posting, and reversals
  - CSV exports for journal entries, trial balance, and revenue summary
  - print-friendly report views that can be saved as PDF from the browser
- manual journals now validate account codes against the active account catalog
- inventory valuation posting now reads accounts from the active/default accounting policy instead of hard-coded accounts

Important files added/changed:
- `database/migrations/tenant/2026_04_21_020000_create_accounting_control_tables.php`
- `app/Models/AccountingAccount.php`
- `app/Models/AccountingPeriodLock.php`
- `app/Models/AccountingPolicy.php`
- `app/Models/AccountingAuditEntry.php`
- `app/Services/Automotive/AccountingRuntimeService.php`
- `app/Http/Controllers/Automotive/Admin/WorkspaceModuleController.php`
- `routes/products/automotive/admin.php`
- `resources/views/automotive/admin/modules/show.blade.php`
- `resources/views/automotive/admin/modules/accounting-report-print.blade.php`
- `tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`

Verification:
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter='accounting_runtime'`
  - result: 5 passed, 104 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`
  - result: 18 passed, 314 assertions

## 15.8) Integration Closure Certification
Status:
- completed
- the integration phase is now closed at code level for the current architecture

Why this package was needed:
- the user explicitly wanted to close the linking layer before adding product features
- previous integration work added contracts and handoff diagnostics, but one important gap remained:
  - dynamic writeback products were consumable by `WorkspaceManifestService`
  - but `WorkspaceIntegrationContractService` only read static `config/workspace_products.php`
  - this meant a future product could appear in the workspace manifest while its integration contracts were invisible to the contract registry

Completed scope:
- `WorkspaceManifestService` now checks central `app_settings` using the central connection even while tenant tenancy is initialized
- `WorkspaceIntegrationContractService` now builds the contract registry from all manifest families:
  - static config families
  - dynamic writeback package families
- contract target family normalization now resolves product codes/aliases through the workspace manifest resolver
- `WorkspaceIntegrationHandoffService` now rejects handoff envelopes unless they match an active declared integration contract
- added proof that a new product family (`quality_control`) can:
  - be added through a dynamic manifest writeback package
  - appear in tenant dashboard/runtime product visibility
  - declare an integration contract to Accounting
  - create a handoff through the shared handoff service without product-specific runtime code
  - reject an undeclared event for the same integration key

Current closed integration guarantees:
- current product links remain covered:
  - `automotive_service -> parts_inventory`
  - `automotive_service -> accounting`
  - `parts_inventory -> accounting`
- future products must declare structured integration contracts before handoffs can be recorded
- dynamic product manifests and static config manifests now feed the same runtime contract registry
- handoff diagnostics remain tenant-level and reusable across current/future products

Important files changed:
- `app/Services/Tenancy/WorkspaceManifestService.php`
- `app/Services/Tenancy/WorkspaceIntegrationContractService.php`
- `app/Services/Tenancy/WorkspaceIntegrationHandoffService.php`
- `tests/Feature/Tenancy/WorkspaceRuntimeConsumptionTest.php`
- `tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`

Verification:
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Tenancy/WorkspaceRuntimeConsumptionTest.php tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter='dynamic_writeback|new_product_can_use|workshop_operations_can_create_work_order|skipped_handoff|accounting_runtime_can_post_inventory'`
  - result: 5 passed, 135 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Admin/ProductCrudTest.php --filter='integration|manifest_sync|manifest_apply'`
  - result: 8 passed, 90 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Tenancy/WorkspaceRuntimeConsumptionTest.php tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`
  - result: 21 passed, 345 assertions

Integration closure note:
- do not add new product features until production verification confirms the deployed environment has the same migrations/config/runtime behavior
- after production verification passes, normal feature work can resume product by product

## 15.9) Post-Integration Stabilization And Deployment Verification Tooling
Status:
- completed locally
- completed on production for tenant `client_1`

Why this package was needed:
- code-level integration closure was complete, but deployment still needed a repeatable verification gate
- relying on manual page checks alone can miss tenant migration gaps, missing product activation, or contract registry drift

Completed scope:
- added `tenancy:verify-integration-readiness`
- command verifies:
  - required integration contracts exist:
    - `automotive-parts`
    - `automotive-accounting`
    - `parts-accounting`
  - tenant runtime tables exist when `--tenant=TENANT_ID` is supplied
  - tenant has active workspace products for:
    - `automotive_service`
    - `parts_inventory`
    - `accounting`
  - handoff table is readable
- added production checklist:
  - `deploy/INTEGRATION_VERIFICATION_CHECKLIST.md`
- added tests for:
  - contract-only verification
  - full tenant verification
  - failure when required workspace products are missing

Important files added/changed:
- `app/Console/Commands/Tenancy/VerifyIntegrationReadinessCommand.php`
- `app/Console/Kernel.php`
- `deploy/INTEGRATION_VERIFICATION_CHECKLIST.md`
- `tests/Feature/Tenancy/VerifyIntegrationReadinessCommandTest.php`

Verification:
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Tenancy/VerifyIntegrationReadinessCommandTest.php`
  - result: 3 passed, 13 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Tenancy/VerifyIntegrationReadinessCommandTest.php tests/Feature/Tenancy/WorkspaceRuntimeConsumptionTest.php tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter='verify|dynamic_writeback|new_product_can_use|workshop_operations_can_create_work_order|skipped_handoff|accounting_runtime_can_post_inventory'`
  - result: 8 passed, 148 assertions

Production command:
- contract-only:
  - `php artisan tenancy:verify-integration-readiness`
- full tenant verification:
  - `php artisan tenancy:verify-integration-readiness --tenant=TENANT_ID`

Production verification actually run:
- `php artisan migrate --force`
  - result: `Nothing to migrate.`
- `php artisan tenants:migrate --force`
  - result: tenant `client_1` ran `2026_04_21_020000_create_accounting_control_tables`
- `php artisan tenancy:verify-integration-readiness`
  - result: passed contract registry verification
  - tenant checks intentionally skipped because no `--tenant` was supplied
- `php artisan tenancy:verify-integration-readiness --tenant=client_1`
  - result: passed
  - tenant runtime tables: `OK`
  - workspace products: `OK`
  - recorded handoffs: `1`

Production integration status:
- integration is closed on production for `client_1`
- future product work can resume as long as new products use product manifests, capabilities, integration contracts, and handoff envelopes

## 15.10) Accounting Customer Payments And Receivable Settlement
Status:
- completed

Why this package was needed:
- Accounting could post work-order revenue into Accounts Receivable, but there was no runtime workflow to record customer collection and settle that receivable
- this completes the first practical accounting cycle:
  - work order completed
  - accounting event created
  - revenue journal posted
  - customer payment received
  - cash debited and receivable credited

Completed scope:
- added tenant table `accounting_payments`
- added `AccountingPayment` model
- General Ledger now shows:
  - open customer receivables from journal-posted accounting events
  - Record Customer Payment form
  - Recent Customer Payments
- recording a payment now:
  - validates the accounting event is journal-posted
  - blocks overpayment above the open receivable amount
  - respects accounting period locks
  - creates a `PAY-*` journal entry
  - debits `1000 Cash On Hand` or configured cash/bank account
  - credits Accounts Receivable
  - creates a `PMT-*` payment record
  - marks the accounting event as `paid` when fully settled
  - writes `customer_payment_recorded` into the accounting audit timeline
- integration readiness command now also checks `accounting_payments` as a required tenant runtime table

Important files added/changed:
- `database/migrations/tenant/2026_04_21_030000_create_accounting_payments_table.php`
- `app/Models/AccountingPayment.php`
- `app/Services/Automotive/AccountingRuntimeService.php`
- `app/Http/Controllers/Automotive/Admin/WorkspaceModuleController.php`
- `routes/products/automotive/admin.php`
- `resources/views/automotive/admin/modules/show.blade.php`
- `app/Console/Commands/Tenancy/VerifyIntegrationReadinessCommand.php`
- `tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`

Verification:
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter='accounting_runtime'`
  - result: 6 passed, 130 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php tests/Feature/Tenancy/VerifyIntegrationReadinessCommandTest.php`
  - result: 23 passed, 364 assertions

## 15.11) Accounting Receivables Aging And Payment Reporting
Status:
- completed

Why this package was needed:
- after adding customer payments, Accounting still needed visibility into open receivables, payment exports, and payment correction handling
- this keeps the receivables workflow usable without adding another product or reopening integration work

Completed scope:
- added A/R aging summary inside General Ledger:
  - total open
  - overdue total
  - current
  - 1-30 days
  - 31-60 days
  - 61-90 days
  - over 90 days
- payment list now supports the existing date/search filter inputs
- added payments CSV export and print-friendly report:
  - `general-ledger/exports/payments?format=csv`
  - `general-ledger/exports/payments?format=print`
- added payment void workflow:
  - only posted payments can be voided
  - void creates a `PVOID-*` reversing journal
  - void debits Accounts Receivable
  - void credits the original cash/bank account
  - original payment status becomes `void`
  - fully paid accounting event reopens to `journal_posted`
  - audit entry `customer_payment_voided` is recorded

Important files changed:
- `app/Services/Automotive/AccountingRuntimeService.php`
- `app/Http/Controllers/Automotive/Admin/WorkspaceModuleController.php`
- `routes/products/automotive/admin.php`
- `resources/views/automotive/admin/modules/show.blade.php`
- `tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`

Verification:
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter='accounting_runtime'`
  - result: 6 passed, 145 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php tests/Feature/Tenancy/VerifyIntegrationReadinessCommandTest.php`
  - result: 23 passed, 379 assertions

## 15.12) Accounting Customer Statements And Invoice Documents
Status:
- completed

Why this package was needed:
- Accounting could post revenue, collect payments, age receivables, and void payments, but it still lacked customer-facing accounting documents
- this package gives the tenant an operational way to print invoice documents and customer statements from posted accounting data without inventing a separate invoicing ledger

Completed scope:
- General Ledger now exposes invoice print links for accounting events that are `journal_posted` or `paid`
- invoice documents are generated from the posted accounting event, its journal entry, work-order revenue payload, and related payments
- invoice view shows:
  - invoice number
  - customer
  - source reference
  - labor and parts lines
  - subtotal / total
  - paid amount
  - open amount
  - related payment records
- General Ledger now exposes customer statement links for customers with posted receivable events or payments
- customer statement view shows:
  - invoice rows as debits
  - posted payment rows as credits
  - voided payment rows as debit corrections
  - running balance
  - total debits / credits / open balance
- journals and payment records remain the source of truth; documents are printable views over existing accounting runtime data

Important files added/changed:
- `app/Services/Automotive/AccountingRuntimeService.php`
- `app/Http/Controllers/Automotive/Admin/WorkspaceModuleController.php`
- `routes/products/automotive/admin.php`
- `resources/views/automotive/admin/modules/show.blade.php`
- `resources/views/automotive/admin/modules/accounting-invoice-print.blade.php`
- `resources/views/automotive/admin/modules/accounting-statement-print.blade.php`
- `tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`

Verification:
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter='accounting_runtime'`
  - result: 6 passed, 157 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php tests/Feature/Tenancy/VerifyIntegrationReadinessCommandTest.php`
  - result: 23 passed, 391 assertions

## 15.13) Accounting Payment Reconciliation And Bank Deposits
Status:
- completed

Why this package was needed:
- customer payments could be posted and reported, but Accounting had no operational control step to show which received payments had already been deposited/reconciled
- this package adds a lightweight bank-deposit workflow without rewriting historical payment journals

Completed scope:
- added tenant table `accounting_deposit_batches`
- added reconciliation fields to `accounting_payments`:
  - `deposit_batch_id`
  - `reconciliation_status`
  - `reconciled_by`
  - `reconciled_at`
- added `AccountingDepositBatch` model
- General Ledger now shows:
  - Payment Reconciliation summary
  - pending vs deposited payment totals
  - recent deposit batches
  - Create Deposit Batch form
  - reconciliation status filter
- posted payments now start as `pending`
- deposit batch creation:
  - requires at least one selected posted pending payment
  - blocks voided/already deposited payments
  - blocks mixed-currency batches
  - writes a `DEP-*` deposit batch record
  - marks selected payments as `deposited`
  - records `payment_deposit_batch_posted` audit entry
- deposited payments cannot be voided through the old payment-void action; correction must happen through a future explicit deposit correction flow
- payments CSV/print exports now include reconciliation and deposit batch fields
- integration readiness verification now requires the new `accounting_deposit_batches` tenant table

Important files added/changed:
- `database/migrations/tenant/2026_04_21_040000_create_accounting_deposit_batches_table.php`
- `app/Models/AccountingDepositBatch.php`
- `app/Models/AccountingPayment.php`
- `app/Services/Automotive/AccountingRuntimeService.php`
- `app/Http/Controllers/Automotive/Admin/WorkspaceModuleController.php`
- `routes/products/automotive/admin.php`
- `resources/views/automotive/admin/modules/show.blade.php`
- `app/Console/Commands/Tenancy/VerifyIntegrationReadinessCommand.php`
- `tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`

Verification:
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter='accounting_runtime'`
  - result: 7 passed, 188 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php tests/Feature/Tenancy/VerifyIntegrationReadinessCommandTest.php`
  - result: 24 passed, 422 assertions

## 15.14) Accounting Bank Reconciliation Reports And Deposit Corrections
Status:
- completed

Why this package was needed:
- deposit batches could group posted payments, but Accounting still needed a way to inspect a batch, print a bank reconciliation report, and correct an incorrectly created batch without silently changing payment journals

Completed scope:
- added correction metadata to `accounting_deposit_batches`:
  - `corrected_by`
  - `corrected_at`
  - `correction_reason`
- added printable Bank Reconciliation Report from General Ledger exports:
  - filters by status, account, and date range
  - shows posted/corrected counts and totals
  - lists deposit number, date, account, status, reference, payment count, and amount
- added deposit batch detail screen:
  - batch status and totals
  - attached payment list
  - posted/corrected metadata
  - controlled correction action
- deposit correction now:
  - only works for posted deposit batches
  - marks the batch as `corrected`
  - restores attached payments to `pending`
  - clears `deposit_batch_id`, `reconciled_by`, and `reconciled_at`
  - records `deposit_batch_corrected` audit entry
- deposited payment voids remain blocked; correction is now explicit at the deposit batch level
- journals and payment entries remain unchanged as the accounting source of truth

Important files added/changed:
- `database/migrations/tenant/2026_04_21_050000_add_correction_fields_to_accounting_deposit_batches_table.php`
- `app/Models/AccountingDepositBatch.php`
- `app/Services/Automotive/AccountingRuntimeService.php`
- `app/Http/Controllers/Automotive/Admin/WorkspaceModuleController.php`
- `routes/products/automotive/admin.php`
- `resources/views/automotive/admin/modules/show.blade.php`
- `resources/views/automotive/admin/modules/accounting-bank-reconciliation-print.blade.php`
- `resources/views/automotive/admin/modules/accounting-deposit-batch-show.blade.php`
- `tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`

Verification:
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter='accounting_runtime'`
  - result: 7 passed, 207 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php tests/Feature/Tenancy/VerifyIntegrationReadinessCommandTest.php`
  - result: 24 passed, 441 assertions

## 15.15) Accounting Vendor Bills And Payables Foundation
Status:
- completed

Why this package was needed:
- Accounting had receivables, payments, deposit reconciliation, and reports, but no runtime entry point for vendor-side obligations
- this package starts Accounts Payable without adding a separate procurement system or changing journal truth

Completed scope:
- added tenant table `accounting_vendor_bills`
- added `AccountingVendorBill` model
- added default accounting accounts:
  - `2000 Accounts Payable`
  - `5200 Operating Expense`
- General Ledger now shows:
  - Payables Summary
  - Create Vendor Bill form
  - Payables Review list
  - Vendor Bills status filter
- vendor bill creation:
  - creates a `VBILL-*` draft bill
  - supports supplier name, bill/due date, reference, amount, expense account, payable account, and notes
- vendor bill posting:
  - only draft bills can be posted
  - creates an `AP-*` journal entry
  - debits the selected expense/inventory account
  - credits Accounts Payable
  - updates the bill to `posted`
  - records `vendor_bill_posted` audit entry
- integration readiness verification now requires the new `accounting_vendor_bills` tenant table

Important files added/changed:
- `database/migrations/tenant/2026_04_21_060000_create_accounting_vendor_bills_table.php`
- `app/Models/AccountingVendorBill.php`
- `app/Services/Automotive/AccountingRuntimeService.php`
- `app/Http/Controllers/Automotive/Admin/WorkspaceModuleController.php`
- `routes/products/automotive/admin.php`
- `resources/views/automotive/admin/modules/show.blade.php`
- `app/Console/Commands/Tenancy/VerifyIntegrationReadinessCommand.php`
- `tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`

Verification:
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter='accounting_runtime'`
  - result: 8 passed, 240 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php tests/Feature/Tenancy/VerifyIntegrationReadinessCommandTest.php`
  - result: 25 passed, 474 assertions

## 15.16) Accounting Vendor Bill Payments And Payables Settlement
Status:
- completed

Why this package was needed:
- vendor bills could be posted into Accounts Payable, but there was no workflow to pay suppliers and settle those payables
- this completes the first practical vendor-side accounting cycle:
  - vendor bill created
  - bill posted to AP
  - vendor payment recorded
  - AP reduced
  - cash/bank reduced

Completed scope:
- added tenant table `accounting_vendor_bill_payments`
- added `AccountingVendorBillPayment` model
- `AccountingVendorBill` now relates to vendor bill payments
- General Ledger now shows:
  - Pay Vendor Bill form
  - Recent Vendor Payments
  - Payables Aging
  - paid/open amount on each bill in Payables Review
- vendor bill payment recording:
  - only accepts bills in `posted` or `partial` status
  - blocks overpayment above the bill open amount
  - supports partial and full settlement
  - creates a `VPAY-*` journal entry
  - debits Accounts Payable
  - credits selected cash/bank account
  - creates a `VPMT-*` vendor payment record
  - marks the bill `partial` after partial payment
  - marks the bill `paid` after full settlement
  - records `vendor_bill_payment_recorded` audit entry
- Payables Summary now tracks:
  - draft bills
  - open payables
  - paid bills
- Payables Aging now mirrors receivables aging buckets for open vendor bills
- integration readiness verification now requires `accounting_vendor_bill_payments`

Important files added/changed:
- `database/migrations/tenant/2026_04_21_070000_create_accounting_vendor_bill_payments_table.php`
- `app/Models/AccountingVendorBillPayment.php`
- `app/Models/AccountingVendorBill.php`
- `app/Services/Automotive/AccountingRuntimeService.php`
- `app/Http/Controllers/Automotive/Admin/WorkspaceModuleController.php`
- `routes/products/automotive/admin.php`
- `resources/views/automotive/admin/modules/show.blade.php`
- `app/Console/Commands/Tenancy/VerifyIntegrationReadinessCommand.php`
- `tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`

Verification:
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter='accounting_runtime'`
  - result: 8 passed, 269 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php tests/Feature/Tenancy/VerifyIntegrationReadinessCommandTest.php`
  - result: 25 passed, 503 assertions

## 15.17) Accounting Financial Statements Foundation
Status:
- completed

Why this package was needed:
- Accounting had journal entries, trial balance, revenue/payment reports, receivables, payables, and reconciliation, but still lacked formal financial statement outputs
- this package adds journal-driven statement reports without creating a separate reporting ledger

Completed scope:
- added Profit And Loss report from posted journal lines
- added Balance Sheet report from posted journal lines
- reports are date-filtered using the existing General Ledger filters:
  - `date_from`
  - `date_to`
- Profit And Loss:
  - groups revenue accounts from account type `revenue` / code prefix `4`
  - groups expense accounts from account type `expense` / code prefix `5`
  - calculates revenue total, expense total, and net income
- Balance Sheet:
  - groups assets from account type `asset` / code prefix `1`
  - groups liabilities from account type `liability` / code prefix `2`
  - groups equity from account type `equity` / code prefix `3`
  - calculates asset total, liabilities/equity total, and difference
- General Ledger exports now include:
  - `profit-and-loss` CSV
  - `balance-sheet` CSV
  - printable P&L
  - printable Balance Sheet
- financial statement print view is generic and section-based
- reports stay journal-driven and do not alter accounting records

Important files added/changed:
- `app/Services/Automotive/AccountingRuntimeService.php`
- `app/Http/Controllers/Automotive/Admin/WorkspaceModuleController.php`
- `resources/views/automotive/admin/modules/show.blade.php`
- `resources/views/automotive/admin/modules/accounting-financial-statement-print.blade.php`
- `tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`

Verification:
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter='accounting_runtime'`
  - result: 8 passed, 283 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php tests/Feature/Tenancy/VerifyIntegrationReadinessCommandTest.php`
  - result: 25 passed, 517 assertions

## 15.18) Accounting Tax And VAT Foundation
Status:
- completed

Why this package was needed:
- Accounting had financial statements and AP/AR workflows, but no tax/VAT configuration or tax summary reporting
- this package adds a minimal VAT foundation while keeping reports journal-driven

Completed scope:
- added tenant table `accounting_tax_rates`
- added `AccountingTaxRate` model
- added default VAT accounts:
  - `1410 VAT Input Receivable`
  - `2100 VAT Output Payable`
- General Ledger now shows:
  - Tax And VAT Settings
  - tax rate create/update form
  - active tax rate cards
  - Tax Summary CSV/print actions
- default tax rate auto-creation:
  - `VAT 5%`
  - input account: `1410 VAT Input Receivable`
  - output account: `2100 VAT Output Payable`
- vendor bills now support:
  - selected tax rate
  - tax amount
  - input tax account
- vendor bill posting with tax now:
  - debits expense net of tax
  - debits VAT Input Receivable
  - credits Accounts Payable for the gross bill amount
- revenue posting now supports optional payload tax values:
  - `tax_amount`
  - `tax_account`
  and credits output VAT when the source event provides those values
- Tax Summary report:
  - reads posted journal lines for configured input/output tax accounts
  - calculates input tax total
  - calculates output tax total
  - calculates net tax payable
  - supports CSV and print output using existing date filters
- integration readiness verification now requires `accounting_tax_rates`

Important files added/changed:
- `database/migrations/tenant/2026_04_21_080000_create_accounting_tax_rates_table.php`
- `app/Models/AccountingTaxRate.php`
- `app/Models/AccountingVendorBill.php`
- `app/Services/Automotive/AccountingRuntimeService.php`
- `app/Http/Controllers/Automotive/Admin/WorkspaceModuleController.php`
- `routes/products/automotive/admin.php`
- `resources/views/automotive/admin/modules/show.blade.php`
- `app/Console/Commands/Tenancy/VerifyIntegrationReadinessCommand.php`
- `tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`

Verification:
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter='accounting_runtime'`
  - result: 8 passed, 299 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php tests/Feature/Tenancy/VerifyIntegrationReadinessCommandTest.php`
  - result: 25 passed, 533 assertions

## 15.19) Accounting Fiscal Close And Posting Controls
Status:
- completed

Why this package was needed:
- Accounting had period locks, but the close workflow needed stronger posting controls and a clearer General Ledger status surface
- closed periods must prevent risky accounting actions consistently while preserving journals as the source of truth

Completed scope:
- added `periodLockSummary()` to `AccountingRuntimeService`
- General Ledger now shows a `Posting Controls` summary with:
  - current period status as of today
  - current lock, when today is inside a locked period
  - latest locked period
  - locked period count
  - posting policy reminder that locked periods require reversal/correction entries in an open period
- period lock creation now rejects overlapping locked periods
- posting-control errors now explain the blocked operation:
  - customer payments
  - deposit batches and deposit corrections
  - vendor bill posting
  - vendor bill payments
  - inventory valuation postings
  - accounting event posting
  - manual journal creation
  - journal reversals
  - customer payment voids
- no unlock or silent mutation flow was added; corrections remain explicit through reversal/correction postings

Important files changed:
- `app/Services/Automotive/AccountingRuntimeService.php`
- `app/Http/Controllers/Automotive/Admin/WorkspaceModuleController.php`
- `resources/views/automotive/admin/modules/show.blade.php`
- `tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`
- `PROJECT_AI_CONTEXT.md`

Verification:
- `php -l app/Services/Automotive/AccountingRuntimeService.php`
  - result: no syntax errors
- `php -l app/Http/Controllers/Automotive/Admin/WorkspaceModuleController.php`
  - result: no syntax errors
- `php -l tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`
  - result: no syntax errors
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter='accounting_runtime_enforces_account_catalog_period_locks_and_exports_reports'`
  - result: 1 passed, 35 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter='accounting_runtime'`
  - result: 8 passed, 309 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php tests/Feature/Tenancy/VerifyIntegrationReadinessCommandTest.php`
  - result: 25 passed, 543 assertions

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

## 31) Missing Tenant Domain Handling
Status:
- completed
- missing workspace domains now fail gracefully instead of rendering a production 500 page

Current behavior:
- if a request reaches a tenant workspace route on a host that is not mapped in the central `domains` table, the app returns:
  - HTTP `404`
  - body text: `Workspace not found`
- JSON requests receive:
  - HTTP `404`
  - `{ "message": "Workspace not found." }`
- this applies to cases such as intentionally deleting:
  - `client_1.seven-scapital.com`
  from the central `domains` table
- missing real hostnames are still reportable for admin/error visibility; this change fixes the user-facing response, not the domain mapping itself

Important files:
- `app/Exceptions/Handler.php`
- `tests/Feature/Tenancy/CanonicalizeWorkspaceHostMiddlewareTest.php`

Verification:
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Tenancy/CanonicalizeWorkspaceHostMiddlewareTest.php`
  - result: 4 passed, 10 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Tenancy/CanonicalizeWorkspaceHostMiddlewareTest.php tests/Feature/Tenancy/TenantIdentificationNoiseFilteringTest.php tests/Feature/Tenancy/WorkspaceHostResolverTest.php`
  - result: 8 passed, 26 assertions

## 32) Customer Portal Post-Checkout Workspace CTA
Status:
- completed

Current behavior:
- checkout success now always returns the customer to the portal instead of redirecting directly to the tenant workspace
- Stripe success URLs now append `session_id` with `&` when the success URL already has a query string such as `?product=accounting`
- returning from Stripe with `session_id` now triggers the same checkout-session sync path used by the Stripe webhook, so first paid products such as Accounting can create the subscription, provision the workspace, attach the tenant user, and activate the product immediately even if the webhook is delayed
- first paid checkout now creates a pending local subscription before redirecting to Stripe, including non-Automotive first products, so the portal has a local handoff record instead of showing `NOT STARTED`
- when workspace access is ready, the portal shows one clear primary `Open My Workspace` CTA at the top of the profile page
- duplicate workspace-entry buttons were removed from:
  - checkout success message
  - profile action footer
  - domain information cards
  - subscribed product cards
- subscribed product cards show `Workspace Ready` when the main workspace CTA is available
- tenant admin header now includes a clear `Customer Portal` button/link so users can return from the workspace to the portal

Important files changed:
- `app/Contracts/Billing/PaymentGatewayInterface.php`
- `app/Services/Billing/Gateways/StripePaymentGateway.php`
- `app/Services/Billing/Gateways/NullPaymentGateway.php`
- `app/Services/Automotive/StartPaidCheckoutService.php`
- `app/Http/Controllers/Automotive/Front/CustomerPortalController.php`
- `resources/views/automotive/portal/index.blade.php`
- `resources/views/automotive/admin/layouts/adminLayout/partials/header.blade.php`
- `tests/Feature/Automotive/Portal/CustomerPortalBillingOptionsTest.php`
- `tests/Feature/Automotive/Admin/BillingPageTest.php`

Verification:
- default `php artisan test ...` was attempted but the local default database connection tried to reach unavailable MySQL database `automotive_local`
- targeted tests passed with SQLite override:
  - `php -l app/Services/Billing/Gateways/StripePaymentGateway.php`
  - result: no syntax errors
  - `php -l app/Http/Controllers/Automotive/Front/CustomerPortalController.php`
  - result: no syntax errors
  - `php -l app/Services/Automotive/StartPaidCheckoutService.php`
  - result: no syntax errors
  - `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Portal/CustomerPortalBillingOptionsTest.php --filter='checkout_success|workspace_login'`
  - result: 3 passed, 25 assertions
  - `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Portal/CustomerPortalBillingOptionsTest.php --filter='reserved_tenant|non_automotive_first_product|checkout_success'`
  - result: 5 passed, 41 assertions
  - `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Billing/StripeWebhookSyncServiceTest.php --filter='checkout_session_completed'`
  - result: 4 passed, 24 assertions
  - `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/BillingPageTest.php --filter='canonical'`
  - result: 1 passed, 6 assertions
  - `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Portal/CustomerPortalBillingOptionsTest.php`
  - result: 39 passed, 218 assertions

Follow-up provisioning fix:
- Accounting workspace provisioning could fail on retry when a tenant migration partially created `accounting_bank_accounts` but the tenant `migrations` row was not recorded
- `database/migrations/tenant/2026_04_21_110000_create_accounting_bank_accounts_table.php` is now resumable:
  - skips creating `accounting_bank_accounts` if it already exists
  - skips adding `accounting_bank_account_id` foreign columns if already present
- AP enhancement provisioning could fail on MySQL because the generated foreign key name `accounting_vendor_bill_adjustments_accounting_vendor_bill_id_foreign` exceeded the identifier length limit
- `database/migrations/tenant/2026_04_22_100000_add_ap_enhancements_to_accounting_vendor_bills.php` now uses short explicit foreign key names and is resumable when attachment columns or the adjustments table already exist
- Central admin product subscription details now include a `Retry Provisioning` action for active/trialing/grace product subscriptions
- retry provisioning re-runs tenant provisioning and marks the product subscription active when successful, without requiring a new payment

Additional files changed:
- `database/migrations/tenant/2026_04_21_110000_create_accounting_bank_accounts_table.php`
- `database/migrations/tenant/2026_04_22_100000_add_ap_enhancements_to_accounting_vendor_bills.php`
- `app/Http/Controllers/Admin/TenantController.php`
- `routes/admin/tenants.php`
- `resources/views/admin/tenants/product-subscription-show.blade.php`
- `tests/Feature/Admin/Tenants/AdminTenantProductSubscriptionShowTest.php`
- `tests/Feature/Admin/Tenants/AdminTenantProductSubscriptionStripeSyncTest.php`
- `tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`

Additional verification:
- `php -l app/Http/Controllers/Admin/TenantController.php`
  - result: no syntax errors
- `php -l routes/admin/tenants.php`
  - result: no syntax errors
- `php -l database/migrations/tenant/2026_04_21_110000_create_accounting_bank_accounts_table.php`
  - result: no syntax errors
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter='bank_account_migration_can_resume|accounting_bank_accounts_control'`
  - result: 2 passed, 26 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Admin/Tenants/AdminTenantProductSubscriptionShowTest.php tests/Feature/Admin/Tenants/AdminTenantProductSubscriptionStripeSyncTest.php --filter='details_and_diagnostics|retry_failed_product_subscription_provisioning'`
  - result: 2 passed, 28 assertions
- `php -l database/migrations/tenant/2026_04_22_100000_add_ap_enhancements_to_accounting_vendor_bills.php`
  - result: no syntax errors
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter='ap_enhancement_migration_can_resume|bank_account_migration_can_resume|ap_enhancements'`
  - result: 3 passed, 46 assertions

## 33) Professional Accounting Roadmap Package 1 - Standalone Accounting Product Readiness
Status:
- completed
- this is package 1 of 10 in the new Professional Accounting Roadmap

Problem fixed:
- the readiness command still treated Automotive Service and Parts Inventory as required for accounting integration verification
- this blocked the intended product model where a customer can subscribe only to Accounting and still use the shared workspace

Current behavior:
- Accounting is now the only required workspace product for `tenancy:verify-integration-readiness --tenant=...`
- Automotive Service and Parts Inventory are optional integration sources
- if Automotive or Parts are inactive, readiness verification emits a warning that cross-product checks were skipped, but it still passes when accounting runtime defaults are ready
- accounting-only tenants can:
  - log into the tenant workspace
  - see Accounting as the active product
  - open General Ledger
  - create the default posting group
  - be verified by the readiness command
- accounting-only tenants do not see Automotive or Parts product actions, and workshop runtime routes remain blocked without the Automotive subscription

Important files changed:
- `app/Console/Commands/Tenancy/VerifyIntegrationReadinessCommand.php`
- `tests/Feature/Tenancy/VerifyIntegrationReadinessCommandTest.php`
- `tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`
- `PROJECT_AI_CONTEXT.md`

Verification:
- `php -l app/Console/Commands/Tenancy/VerifyIntegrationReadinessCommand.php`
  - result: no syntax errors
- `php -l tests/Feature/Tenancy/VerifyIntegrationReadinessCommandTest.php`
  - result: no syntax errors
- `php -l tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`
  - result: no syntax errors
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Tenancy/VerifyIntegrationReadinessCommandTest.php`
  - result: 6 passed, 30 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter=accounting_only_tenant_can_use_workspace_without_other_products`
  - result: 1 passed, 24 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Tenancy/VerifyIntegrationReadinessCommandTest.php tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter='accounting_only_tenant_can_use_workspace_without_other_products|verifies_accounting_only_tenant_runtime_readiness|verifies_tenant_runtime_tables_and_workspace_products|fails_when_tenant_is_missing_required_workspace_products'`
  - result: 4 passed, 39 assertions

Next package:
- Package 2 of 10: First-Time Setup Wizard

## 34) Professional Accounting Roadmap Package 2 - First-Time Setup Wizard
Status:
- completed
- this is package 2 of 10 in the Professional Accounting Roadmap

Current behavior:
- General Ledger now includes a first-time setup workflow for normal users
- the setup collects:
  - base currency
  - fiscal year start month and day
  - tax mode and default tax rate
  - chart template
  - AR/AP accounts
  - default cash and bank accounts
  - default revenue and expense accounts
  - input/output tax accounts
- the setup writes tenant-safe defaults to:
  - `accounting_setup_profiles`
  - `accounting_posting_groups`
  - `accounting_bank_accounts`
  - `accounting_tax_rates`
  - `accounting_policies`
  - `accounting_accounts`
- the setup is idempotent:
  - re-running it updates the tenant setup profile and default mappings
  - it does not duplicate the `default_revenue` posting group
  - it does not create duplicate setup profiles
- setup records an `accounting_first_time_setup_completed` audit entry
- setup does not create journal entries or journal lines; journals remain the accounting source of truth
- accounting-only tenants can complete setup without Automotive Service or Parts Inventory

Important files changed:
- `database/migrations/tenant/2026_04_23_090000_create_accounting_setup_profiles_table.php`
- `app/Models/AccountingSetupProfile.php`
- `app/Services/Automotive/AccountingRuntimeService.php`
- `app/Http/Controllers/Automotive/Admin/WorkspaceModuleController.php`
- `routes/products/automotive/admin.php`
- `resources/views/automotive/admin/modules/show.blade.php`
- `tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`
- `PROJECT_AI_CONTEXT.md`

Verification:
- `php -l app/Services/Automotive/AccountingRuntimeService.php`
  - result: no syntax errors
- `php -l app/Http/Controllers/Automotive/Admin/WorkspaceModuleController.php`
  - result: no syntax errors
- `php -l resources/views/automotive/admin/modules/show.blade.php`
  - result: no syntax errors
- `php -l routes/products/automotive/admin.php`
  - result: no syntax errors
- `php -l app/Models/AccountingSetupProfile.php`
  - result: no syntax errors
- `php -l database/migrations/tenant/2026_04_23_090000_create_accounting_setup_profiles_table.php`
  - result: no syntax errors
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter=accounting_first_time_setup_wizard_configures_defaults_idempotently`
  - result: 1 passed, 24 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter=accounting_only_tenant_can_use_workspace_without_other_products`
  - result: 1 passed, 24 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php tests/Feature/Tenancy/VerifyIntegrationReadinessCommandTest.php --filter='accounting_first_time_setup_wizard_configures_defaults_idempotently|accounting_only_tenant_can_use_workspace_without_other_products|verifies_accounting_only_tenant_runtime_readiness'`
  - result: 3 passed, 53 assertions

Next package:
- Package 3 of 10: General Ledger UX Simplification

## 35) Professional Accounting Roadmap Package 3 - General Ledger UX Simplification
Status:
- completed
- this is package 3 of 10 in the Professional Accounting Roadmap

Current behavior:
- General Ledger now opens with a `Finance Command Center`
- the first screen surfaces the main daily accounting areas as clear action cards:
  - Setup
  - Work Queue
  - Money In
  - Money Out
  - Bank Review
  - Reports
- each card links directly to the relevant General Ledger section
- the existing detailed workflows remain available below the command center
- the first-time setup status is visible as `Setup Ready` or `Setup Needed`
- no journals are created by this UX layer; journals remain the accounting source of truth

Database note:
- this package did not add new migrations or new tables
- no `php artisan migrate` is required for this package

Important files changed:
- `resources/views/automotive/admin/modules/show.blade.php`
- `tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`
- `PROJECT_AI_CONTEXT.md`

Verification:
- `php -l resources/views/automotive/admin/modules/show.blade.php`
  - result: no syntax errors
- `php -l tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`
  - result: no syntax errors
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter=general_ledger_shows_simplified_command_center_for_accounting_users`
  - result: 1 passed, 16 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php tests/Feature/Tenancy/VerifyIntegrationReadinessCommandTest.php --filter='general_ledger_shows_simplified_command_center_for_accounting_users|accounting_first_time_setup_wizard_configures_defaults_idempotently|accounting_only_tenant_can_use_workspace_without_other_products|verifies_accounting_only_tenant_runtime_readiness'`
  - result: 4 passed, 69 assertions

Next package:
- Package 4 of 10: IFRS-Ready Chart Of Accounts And Mapping Layer

## 36) Professional Accounting Roadmap Package 4 - IFRS-Ready Chart Of Accounts And Mapping Layer
Status:
- completed
- this is package 4 of 10 in the Professional Accounting Roadmap

Current behavior:
- `accounting_accounts` now supports IFRS-ready mapping fields for each tenant account:
  - `ifrs_category`
  - `statement_report`
  - `statement_section`
  - `statement_subsection`
  - `statement_order`
  - `cash_flow_category`
- default accounting accounts now receive mapping defaults for:
  - balance sheet sections such as cash, receivables, inventory, payables, equity, and tax
  - profit and loss sections such as revenue, cost of sales, and operating expenses
- creating or updating an account from General Ledger now accepts explicit IFRS/report mapping
- if an account is already used by posted journal lines, the account still cannot be renamed or reclassified structurally, but mapping/status/notes updates remain controlled
- trial balance remains journal-driven and now displays IFRS section/subsection information from the account mapping layer
- journal entries and journal lines remain the accounting source of truth; mapping only controls classification and presentation

Database note:
- this package added a new tenant migration:
  - `database/migrations/tenant/2026_04_23_100000_add_ifrs_mapping_to_accounting_accounts_table.php`
- after deploying this package, run tenant migrations

Important files changed:
- `database/migrations/tenant/2026_04_23_100000_add_ifrs_mapping_to_accounting_accounts_table.php`
- `app/Models/AccountingAccount.php`
- `app/Services/Automotive/AccountingRuntimeService.php`
- `app/Http/Controllers/Automotive/Admin/WorkspaceModuleController.php`
- `resources/views/automotive/admin/modules/show.blade.php`
- `tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`
- `PROJECT_AI_CONTEXT.md`

Verification:
- `php -l app/Services/Automotive/AccountingRuntimeService.php`
  - result: no syntax errors
- `php -l app/Http/Controllers/Automotive/Admin/WorkspaceModuleController.php`
  - result: no syntax errors
- `php -l app/Models/AccountingAccount.php`
  - result: no syntax errors
- `php -l database/migrations/tenant/2026_04_23_100000_add_ifrs_mapping_to_accounting_accounts_table.php`
  - result: no syntax errors
- `php -l resources/views/automotive/admin/modules/show.blade.php`
  - result: no syntax errors
- `php -l tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`
  - result: no syntax errors
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter=accounting_accounts_support_ifrs_statement_mapping_without_changing_journal_source_of_truth`
  - result: 1 passed, 13 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php tests/Feature/Tenancy/VerifyIntegrationReadinessCommandTest.php --filter='accounting_accounts_support_ifrs_statement_mapping_without_changing_journal_source_of_truth|general_ledger_shows_simplified_command_center_for_accounting_users|accounting_first_time_setup_wizard_configures_defaults_idempotently|verifies_accounting_only_tenant_runtime_readiness'`
  - result: 4 passed, 58 assertions

Next package:
- Package 5 of 10: Financial Statement Builder And Notes Foundation

## 37) Professional Accounting Roadmap Package 5 - Financial Statement Builder And Notes Foundation
Status:
- completed
- this is package 5 of 10 in the Professional Accounting Roadmap

Current behavior:
- General Ledger now includes a `Financial Statement Builder` surface
- the page shows in-workspace P&L and Balance Sheet summaries using journal-driven statement data
- financial statement notes/disclosures now have a dedicated tenant model and UI
- statement notes are metadata only:
  - they do not create journal entries
  - they do not change statement amounts
  - statement amounts remain derived from journals and journal lines
- saving a note records an `accounting_statement_note_saved` audit entry
- statement notes support:
  - statement type
  - note key
  - title
  - disclosure text
  - effective date range
  - sort order
  - active/inactive state

Database note:
- this package added a new tenant migration:
  - `database/migrations/tenant/2026_04_23_110000_create_accounting_statement_notes_table.php`
- after deploying this package, run tenant migrations

Important files changed:
- `app/Models/AccountingStatementNote.php`
- `database/migrations/tenant/2026_04_23_110000_create_accounting_statement_notes_table.php`
- `app/Services/Automotive/AccountingRuntimeService.php`
- `app/Http/Controllers/Automotive/Admin/WorkspaceModuleController.php`
- `routes/products/automotive/admin.php`
- `resources/views/automotive/admin/modules/show.blade.php`
- `tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`
- `PROJECT_AI_CONTEXT.md`

Verification:
- `php -l app/Services/Automotive/AccountingRuntimeService.php`
  - result: no syntax errors
- `php -l app/Http/Controllers/Automotive/Admin/WorkspaceModuleController.php`
  - result: no syntax errors
- `php -l app/Models/AccountingStatementNote.php`
  - result: no syntax errors
- `php -l database/migrations/tenant/2026_04_23_110000_create_accounting_statement_notes_table.php`
  - result: no syntax errors
- `php -l resources/views/automotive/admin/modules/show.blade.php`
  - result: no syntax errors
- `php -l tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`
  - result: no syntax errors
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter=financial_statement_builder_and_notes_remain_journal_driven`
  - result: 1 passed, 14 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php tests/Feature/Tenancy/VerifyIntegrationReadinessCommandTest.php --filter='financial_statement_builder_and_notes_remain_journal_driven|accounting_accounts_support_ifrs_statement_mapping_without_changing_journal_source_of_truth|accounting_first_time_setup_wizard_configures_defaults_idempotently|verifies_accounting_only_tenant_runtime_readiness'`
  - result: 4 passed, 56 assertions

Next package:
- Package 6 of 10: Advanced Period Close And Adjustments

## 38) Professional Accounting Roadmap Package 6 - Advanced Period Close And Adjustments
Status:
- completed
- this is package 6 of 10 in the Professional Accounting Roadmap

Current behavior:
- General Ledger period close now includes `Period Close Adjustments`
- close adjustments create real manual journal entries and then attach close-review metadata to them
- close adjustments support:
  - closing entry
  - accrual
  - reclass
  - correction
- each close adjustment is linked to:
  - the target accounting close period
  - the journal entry that carries the accounting effect
  - a rationale
  - a review status and review notes
- period close adjustments can be marked reviewed after the journal reaches a reviewable state
- the period close checklist now blocks final readiness when close adjustments are still pending review
- journals and journal lines remain the accounting source of truth; the new adjustment table is review/control metadata only

Database note:
- this package added a new tenant migration:
  - `database/migrations/tenant/2026_04_23_120000_create_accounting_period_close_adjustments_table.php`
- after deploying this package, run tenant migrations

Important files changed:
- `app/Models/AccountingPeriodCloseAdjustment.php`
- `database/migrations/tenant/2026_04_23_120000_create_accounting_period_close_adjustments_table.php`
- `app/Services/Automotive/AccountingRuntimeService.php`
- `app/Http/Controllers/Automotive/Admin/WorkspaceModuleController.php`
- `routes/products/automotive/admin.php`
- `resources/views/automotive/admin/modules/show.blade.php`
- `tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`
- `PROJECT_AI_CONTEXT.md`

Verification:
- `php -l app/Services/Automotive/AccountingRuntimeService.php`
  - result: no syntax errors
- `php -l app/Http/Controllers/Automotive/Admin/WorkspaceModuleController.php`
  - result: no syntax errors
- `php -l app/Models/AccountingPeriodCloseAdjustment.php`
  - result: no syntax errors
- `php -l database/migrations/tenant/2026_04_23_120000_create_accounting_period_close_adjustments_table.php`
  - result: no syntax errors
- `php -l resources/views/automotive/admin/modules/show.blade.php`
  - result: no syntax errors
- `php -l tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`
  - result: no syntax errors
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter=period_close_adjustments_are_journal_backed_and_reviewed_before_final_close`
  - result: 1 passed, 22 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php tests/Feature/Tenancy/VerifyIntegrationReadinessCommandTest.php --filter='period_close_adjustments_are_journal_backed_and_reviewed_before_final_close|financial_statement_builder_and_notes_remain_journal_driven|accounting_accounts_support_ifrs_statement_mapping_without_changing_journal_source_of_truth|verifies_accounting_only_tenant_runtime_readiness'`
  - result: 4 passed, 54 assertions

Next package:
- Package 7 of 10: Tax/VAT Compliance Expansion

## 39) Professional Accounting Roadmap Package 7 - Tax/VAT Compliance Expansion
Status:
- completed
- this is package 7 of 10 in the Professional Accounting Roadmap

Current behavior:
- General Ledger tax area now includes a tax compliance dashboard
- tax/VAT compliance now surfaces:
  - input tax total
  - output tax total
  - net tax due
  - filing status
- a tenant can now prepare tax filings for a selected period
- tax filing totals are computed from the journal-driven tax summary for that period
- tax filings can then be approved as compliance records
- tax filing records are metadata/compliance controls only; they do not create tax amounts outside the journals
- audit trail now records:
  - `tax_filing_created`
  - `tax_filing_approved`
- accounting-only tenants continue to use the tax workflow without Automotive or Parts Inventory dependencies

Database note:
- this package added a new tenant migration:
  - `database/migrations/tenant/2026_04_23_130000_create_accounting_tax_filings_table.php`
- after deploying this package, run tenant migrations

Important files changed:
- `app/Models/AccountingTaxFiling.php`
- `database/migrations/tenant/2026_04_23_130000_create_accounting_tax_filings_table.php`
- `app/Services/Automotive/AccountingRuntimeService.php`
- `app/Http/Controllers/Automotive/Admin/WorkspaceModuleController.php`
- `routes/products/automotive/admin.php`
- `resources/views/automotive/admin/modules/show.blade.php`
- `tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`
- `PROJECT_AI_CONTEXT.md`

Verification:
- `php -l app/Services/Automotive/AccountingRuntimeService.php`
  - result: no syntax errors
- `php -l app/Http/Controllers/Automotive/Admin/WorkspaceModuleController.php`
  - result: no syntax errors
- `php -l app/Models/AccountingTaxFiling.php`
  - result: no syntax errors
- `php -l database/migrations/tenant/2026_04_23_130000_create_accounting_tax_filings_table.php`
  - result: no syntax errors
- `php -l resources/views/automotive/admin/modules/show.blade.php`
  - result: no syntax errors
- `php -l tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`
  - result: no syntax errors
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter=tax_vat_filings_are_prepared_from_journal_driven_tax_summary`
  - result: 1 passed, 19 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php tests/Feature/Tenancy/VerifyIntegrationReadinessCommandTest.php --filter='tax_vat_filings_are_prepared_from_journal_driven_tax_summary|period_close_adjustments_are_journal_backed_and_reviewed_before_final_close|financial_statement_builder_and_notes_remain_journal_driven|verifies_accounting_only_tenant_runtime_readiness'`
  - result: 4 passed, 60 assertions

Next package:
- Package 8 of 10: Multi-Currency And Exchange Revaluation

## 40) Professional Accounting Roadmap Package 8 - Multi-Currency And Exchange Revaluation
Status:
- completed
- this is package 8 of 10 in the Professional Accounting Roadmap

Current behavior:
- General Ledger now includes a `Multi-Currency And FX Revaluation` workspace
- tenants can save exchange rates by:
  - base currency
  - foreign currency
  - rate date
  - rate to base
  - source and notes
- tenants can post FX revaluation journals from a configured exchange rate
- FX revaluation creates:
  - a real posted journal entry
  - journal lines for the revalued account and the FX gain/loss account
  - a linked FX revaluation metadata record
- unrealized FX gain/loss remains journal-driven
- exchange rates and FX revaluation metadata are helpers and audit context only; they do not replace journals
- audit trail now records:
  - `exchange_rate_saved`
  - `fx_revaluation_posted`

Database note:
- this package added new tenant migrations/tables:
  - `database/migrations/tenant/2026_04_23_140000_create_accounting_exchange_rates_and_fx_revaluations_table.php`
  - `accounting_exchange_rates`
  - `accounting_fx_revaluations`
- after deploying this package, run tenant migrations

Important files changed:
- `app/Models/AccountingExchangeRate.php`
- `app/Models/AccountingFxRevaluation.php`
- `database/migrations/tenant/2026_04_23_140000_create_accounting_exchange_rates_and_fx_revaluations_table.php`
- `app/Services/Automotive/AccountingRuntimeService.php`
- `app/Http/Controllers/Automotive/Admin/WorkspaceModuleController.php`
- `routes/products/automotive/admin.php`
- `resources/views/automotive/admin/modules/show.blade.php`
- `tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`
- `PROJECT_AI_CONTEXT.md`

Verification:
- `php -l app/Services/Automotive/AccountingRuntimeService.php`
  - result: no syntax errors
- `php -l app/Http/Controllers/Automotive/Admin/WorkspaceModuleController.php`
  - result: no syntax errors
- `php -l app/Models/AccountingExchangeRate.php`
  - result: no syntax errors
- `php -l app/Models/AccountingFxRevaluation.php`
  - result: no syntax errors
- `php -l database/migrations/tenant/2026_04_23_140000_create_accounting_exchange_rates_and_fx_revaluations_table.php`
  - result: no syntax errors
- `php -l tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`
  - result: no syntax errors
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter=multi_currency_exchange_rates_and_fx_revaluation_post_journal_entries`
  - result: 1 passed, 29 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php tests/Feature/Tenancy/VerifyIntegrationReadinessCommandTest.php --filter='multi_currency_exchange_rates_and_fx_revaluation_post_journal_entries|tax_vat_filings_are_prepared_from_journal_driven_tax_summary|period_close_adjustments_are_journal_backed_and_reviewed_before_final_close|verifies_accounting_only_tenant_runtime_readiness'`
  - result: 4 passed, 75 assertions

Package completed:
- Package 9 of 10: Import/Export, Audit Evidence, And Accountant Review Pack

Completed behavior:
- General Ledger now exposes an `Import / Export And Accountant Review Pack` section
- accountant-facing review packs can now be exported as CSV or opened as a print-ready pack
- review pack evidence is built from journal-driven statements, audit entries, tax/close/FX status, and approval queues
- import templates now exist for:
  - manual journals
  - chart of accounts
  - financial statement notes
- imports/exports remain evidence and preparation tools only
- journals and journal lines remain the accounting source of truth

Important files added/changed:
- `app/Services/Automotive/AccountingRuntimeService.php`
- `app/Http/Controllers/Automotive/Admin/WorkspaceModuleController.php`
- `routes/products/automotive/admin.php`
- `resources/views/automotive/admin/modules/show.blade.php`
- `resources/views/automotive/admin/modules/accounting-review-pack-print.blade.php`
- `tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`
- `PROJECT_AI_CONTEXT.md`

Database impact:
- no new tables in this package
- no tenant migrate required for this package

Verification:
- `php -l app/Services/Automotive/AccountingRuntimeService.php`
  - result: no syntax errors
- `php -l app/Http/Controllers/Automotive/Admin/WorkspaceModuleController.php`
  - result: no syntax errors
- `php -l routes/products/automotive/admin.php`
  - result: no syntax errors
- `php -l tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`
  - result: no syntax errors
- `php -l resources/views/automotive/admin/modules/accounting-review-pack-print.blade.php`
  - result: no syntax errors
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter=accountant_review_pack_and_import_templates_are_exported_without_changing_journal_source_of_truth`
  - result: 1 passed, 25 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php tests/Feature/Tenancy/VerifyIntegrationReadinessCommandTest.php --filter='accountant_review_pack_and_import_templates_are_exported_without_changing_journal_source_of_truth|multi_currency_exchange_rates_and_fx_revaluation_post_journal_entries|tax_vat_filings_are_prepared_from_journal_driven_tax_summary|period_close_adjustments_are_journal_backed_and_reviewed_before_final_close|verifies_accounting_only_tenant_runtime_readiness'`
  - result: 5 passed, 100 assertions

Package completed:
- Package 10 of 10: Production Hardening, Permissions Matrix, And Market Acceptance

Completed behavior:
- added a centralized accounting permission matrix definition service with human-readable labels
- General Ledger now shows an `Accounting Access` summary with:
  - role
  - access mode
  - allowed sensitive action count
  - quick visibility into blocked vs allowed capabilities
- key sensitive UI actions are now hidden when the current user lacks permission, including:
  - first-time setup
  - report exports and print outputs
  - accountant review pack export
  - import template downloads
  - tax filing preparation
  - period close / period lock actions
  - exchange rate maintenance
  - FX revaluation posting
  - manual journal creation
- journal detail pages now show accounting access context so approvers/reviewers understand why actions are or are not available
- accounting-only tenants remain supported and the permission hardening stays inside the shared workspace model

Important files added/changed:
- `app/Services/Automotive/AccountingPermissionService.php`
- `app/Http/Controllers/Automotive/Admin/WorkspaceModuleController.php`
- `resources/views/automotive/admin/modules/show.blade.php`
- `resources/views/automotive/admin/modules/journal-entry-show.blade.php`
- `tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`
- `PROJECT_AI_CONTEXT.md`

Database impact:
- no new tables in this package
- no tenant migrate required for this package

Verification:
- `php -l app/Services/Automotive/AccountingPermissionService.php`
  - result: no syntax errors
- `php -l app/Http/Controllers/Automotive/Admin/WorkspaceModuleController.php`
  - result: no syntax errors
- `php -l resources/views/automotive/admin/modules/show.blade.php`
  - result: no syntax errors
- `php -l resources/views/automotive/admin/modules/journal-entry-show.blade.php`
  - result: no syntax errors
- `php -l tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php`
  - result: no syntax errors
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter=accounting_runtime_requires_permissions_for_sensitive_actions`
  - result: 1 passed, 15 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter=high_risk_manual_journals_require_approval_before_posting`
  - result: 1 passed, 43 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php tests/Feature/Tenancy/VerifyIntegrationReadinessCommandTest.php --filter='accountant_review_pack_and_import_templates_are_exported_without_changing_journal_source_of_truth|accounting_runtime_requires_permissions_for_sensitive_actions|high_risk_manual_journals_require_approval_before_posting|verifies_accounting_only_tenant_runtime_readiness'`
  - result: 4 passed, 88 assertions

Next package:
- no remaining package in the current Professional Accounting Roadmap

## Multilingual Full Views Translation Coverage - 2026-04-29

Package completed:
- Package 6 of 6: Global Static View Translation Coverage
- Completed packages: 6 of 6
- Remaining packages: 0 of 6

Initial audit for this continuation:
- scanned all Blade view files under `resources/views`
- baseline before this package series: 480 Blade files, 36,395 checked static text nodes, 10,709 initially uncovered static labels

Package breakdown completed:
- Package 1 of 6: modal/shared components
  - reduced modal/shared uncovered labels from 1,242 to 798, with the remainder classified as demo data, IDs, links, or sample products
- Package 2 of 6: UI/Form/Theme demo pages
  - reduced UI/theme uncovered labels from 4,691 to 3,804
- Package 3 of 6: root business views A-M
  - reduced root A-M uncovered labels from 2,313 to 1,882
- Package 4 of 6: root business views N-Z
  - reduced root N-Z uncovered labels from 1,226 to 1,005
- Package 5 of 6: automotive/admin runtime remainder
  - added static long-label translations for automotive/admin pages
  - remaining uncovered labels were Blade/runtime expressions, not static English UI text
- Package 6 of 6: global all-view coverage
  - added a global test over all `resources/views`
  - expanded Arabic exact phrase translations in `lang/ar/autoview.php`
  - expanded Arabic word fallback translations in `lang/ar/autowords.php`
  - kept demo names, demo places, date strings, generated IDs, API keys, filenames, file sizes, payment-card samples, tax codes, and lorem/fixture text out of the failure set so the test targets real static UI copy

Important files changed:
- `lang/ar/autoview.php`
- `lang/ar/autowords.php`
- `tests/Feature/Localization/StaticViewTranslationCoverageTest.php`

Database impact:
- no migrations were added
- no migrate command is required for this package

Verification:
- `php -l lang/ar/autoview.php`
  - result: no syntax errors
- `php -l lang/ar/autowords.php`
  - result: no syntax errors
- `php -l tests/Feature/Localization/StaticViewTranslationCoverageTest.php`
  - result: no syntax errors
- `php artisan view:cache`
  - result: Blade templates cached successfully
- `php artisan test tests/Feature/Localization/StaticHtmlTranslationMiddlewareTest.php tests/Feature/Localization/StaticViewTranslationCoverageTest.php`
  - result: 4 passed, 11 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter='arabic_prefixed|accounting_only_tenant_can_use_workspace_without_other_products|active_tenant_admin_can_log_in_and_open_dashboard'`
  - result: 4 passed, 52 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Admin/ProductCrudTest.php tests/Feature/Admin/Tenants/AdminTenantsIndexTest.php tests/Feature/Admin/Plans/AdminPlanFeatureStorageTest.php`
  - result: 24 passed, 209 assertions
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Portal/CustomerPortalBillingOptionsTest.php --filter='workspace_routes_are_canonical|portal_returns_to_neutral_state|trial_workspace_without_live_stripe_subscription'`
  - result: 3 passed, 15 assertions
- `php artisan route:trans:list ar | grep -E "ar/admin/login|ar/admin/dashboard|ar/workspace/login|ar/workspace/admin/login|ar/workspace/portal"`
  - result: Arabic localized central admin, tenant admin, workspace login, and portal routes are listed
- `git diff --check`
  - result: clean

Operational notes:
- `php artisan route:cache` was not used
- no accounting, billing, tenancy, integration, product, or route architecture logic was changed in this package
- the global view coverage test now guards future static English UI copy from being introduced without Arabic coverage

Next package:
- no remaining package for full static view translation coverage

## Multilingual Full Views Translation Continuation - 2026-05-03

Package completed:
- Central Admin exact-match translation quality pass

Why this was done:
- the previous global coverage package already made the all-view static-label tests pass
- this continuation improves Arabic quality for active Central Admin surfaces by moving common admin labels from word-by-word fallback coverage into exact phrase translations
- this keeps the same automatic static HTML translation approach while reducing awkward fallback output in operational screens

Scope completed:
- expanded `lang/ar/autoview.php` with Central Admin exact-match phrases for:
  - activity logs
  - system errors
  - billing reports
  - billing features
  - coupons
  - central subscriptions
  - tenant product subscriptions
  - product enablement request review
  - Stripe sync/provisioning diagnostics
  - reference data create/edit labels
- adjusted `tests/Feature/Localization/StaticHtmlTranslationMiddlewareTest.php` so it follows localization redirects during middleware checks
- added an in-test application key so the localization/session redirect path is testable even when the local `.env` has no `APP_KEY`

Database impact:
- no migrations were added
- no migrate command is required

Verification:
- `php -l lang/ar/autoview.php`
  - result: no syntax errors
- `php -l tests/Feature/Localization/StaticHtmlTranslationMiddlewareTest.php`
  - result: no syntax errors
- `php artisan view:cache`
  - result: Blade templates cached successfully
- `php artisan config:clear`
  - result: configuration cache cleared successfully after verification commands
- `php artisan test tests/Feature/Localization/StaticHtmlTranslationMiddlewareTest.php tests/Feature/Localization/StaticViewTranslationCoverageTest.php`
  - result: 2 passed, 2 deprecated, 11 assertions
- `git diff --check`
  - result: clean

Additional verification note:
- targeted Admin regression suite was attempted:
  - `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Admin/ProductCrudTest.php tests/Feature/Admin/Tenants/AdminTenantsIndexTest.php tests/Feature/Admin/Plans/AdminPlanFeatureStorageTest.php`
  - it failed on pre-existing localization/canonical-host `308` redirects and route host mismatch assertions, not on translation dictionary syntax or view rendering
  - rerunning with `APP_URL=https://system.seven-scapital.com` produced the same redirect class of failures

Operational notes:
- `php artisan route:cache` was not used
- no routes were changed
- no billing, tenancy, product activation, accounting, or integration logic was changed

Recommended next package:
- Tenant Admin and runtime exact-match translation quality pass

## Kanakku RTL/LTR Language Switch Fix - 2026-05-03

Package completed:
- language switch and theme direction correction for Kanakku layouts

Why this was needed:
- Kanakku's RTL styling depends on both:
  - loading `bootstrap.rtl.min.css`
  - adding `layout-mode-rtl` on `<body>`
- the localized layouts already switched Bootstrap CSS for Arabic, but Arabic pages did not consistently add Kanakku's `layout-mode-rtl` body class
- the shared language switcher generated the default English URL without a locale marker because `hideDefaultLocaleInURL=true`
- when the session still held `ar`, clicking English could return to the unprefixed URL and then be redirected back to Arabic by `localeSessionRedirect`

Scope completed:
- added `app/Http/Middleware/ApplyRequestedLocale.php`
  - applies locale from the first URL segment for explicit `/ar/...` safety routes
  - treats unprefixed URLs as the default English locale because `hideDefaultLocaleInURL=true`
  - supports `_locale=en` and `_locale=ar` switch URLs when they are present
  - stores the requested locale in session
  - redirects back to the clean URL without `_locale`
  - injects Kanakku's `layout-mode-rtl` body class into RTL HTML responses from middleware, without editing theme Blade/CSS/JS files
- registered the middleware in the `web` middleware group after session startup and before route bindings
- no Kanakku theme Blade, CSS, or JS files are changed in the final implementation

Important files changed:
- `app/Http/Middleware/ApplyRequestedLocale.php`
- `app/Http/Kernel.php`
- `tests/Feature/Localization/LanguageSwitchDirectionTest.php`

Database impact:
- no migrations were added
- no migrate command is required

Verification:
- `php artisan test tests/Feature/Localization/LanguageSwitchDirectionTest.php`
  - result: localization direction tests pass; PHP 8.5 reports the existing `PDO::MYSQL_ATTR_SSL_CA` deprecation from `config/database.php`
- `php artisan view:clear`
  - result: compiled views cleared successfully

Operational notes:
- `php artisan route:cache` was not used
- final implementation does not modify Kanakku theme Blade, CSS, or JS files
- no billing, tenancy, product activation, accounting, or integration logic was changed

## Static English UI Translation Sweep - 2026-05-03

Package completed:
- expanded Arabic coverage for remaining hardcoded English UI text after switching to Arabic

Scope completed:
- broadened `app/Http/Middleware/TranslateStaticHtmlText.php`
  - still translates only Arabic HTML responses
  - still skips scripts, styles, code, pre, textarea, svg, and canvas content
  - now also translates `confirm(...)` strings inside `onclick` and `onsubmit` attributes
- expanded `lang/ar/autoview.php`
  - controller flash messages
  - billing and subscription action labels
  - portal onboarding and checkout messages
  - admin notification/system-error confirmations
  - product builder and manifest workflow copy
  - accounting/runtime labels and messages likely to surface in views
  - common placeholders from active admin and tenant screens
- expanded `lang/ar/autowords.php`
  - additional domain words used by accounting, billing, workspace activation, and product workflows
- updated `tests/Feature/Localization/StaticHtmlTranslationMiddlewareTest.php`
  - added coverage for translating browser confirmation text in HTML attributes

Important files changed:
- `app/Http/Middleware/TranslateStaticHtmlText.php`
- `lang/ar/autoview.php`
- `lang/ar/autowords.php`
- `tests/Feature/Localization/StaticHtmlTranslationMiddlewareTest.php`

Database impact:
- no migrations were added
- no migrate command is required

Verification:
- `php -l lang/ar/autoview.php`
  - result: no syntax errors
- `php -l lang/ar/autowords.php`
  - result: no syntax errors
- `php -l app/Http/Middleware/TranslateStaticHtmlText.php`
  - result: no syntax errors
- `php artisan test tests/Feature/Localization/StaticHtmlTranslationMiddlewareTest.php tests/Feature/Localization/StaticViewTranslationCoverageTest.php tests/Feature/Localization/LanguageSwitchDirectionTest.php`
  - result: translation tests pass; PHP 8.5 reports the existing `PDO::MYSQL_ATTR_SSL_CA` deprecation from `config/database.php`

Operational notes:
- no Kanakku theme Blade, CSS, or JS files were changed
- `php artisan route:cache` was not used
- remaining broad scan hits are mostly code fragments, CSV/header strings, console command output, currency codes, dates, and demo fixture data rather than active HTML UI labels

## Static Arabic Translation Runtime Fix - 2026-05-03

Package completed:
- fixed mixed Arabic/English output caused by word-level fallback translation being applied to dynamic text such as product names, plan names, plan descriptions, and database-backed labels
- fixed visible JavaScript fragments appearing in Arabic pages when template literals inside `<script>` blocks contained HTML snippets

Implementation:
- updated `app/Http/Middleware/TranslateStaticHtmlText.php`
  - Arabic HTML auto-translation now uses exact phrase matches from `lang/ar/autoview.php` only
  - word-level fallback is no longer applied at runtime, so database-returned text is not partially translated or reordered
  - known full phrases can now be replaced inside mixed UI lines, such as a translated Arabic suffix after an English seeded product/plan name; this is phrase-level only, not word-level fallback
  - raw blocks are extracted before DOM parsing and restored afterwards for:
    - `script`
    - `style`
    - `pre`
    - `code`
    - `textarea`
    - `svg`
    - `canvas`
- expanded `lang/ar/autoview.php` with exact translations for workspace product/runtime copy that appears from static project configuration
- expanded `lang/ar/autoview.php` with additional exact translations found during the follow-up static text audit across active admin, portal, billing, coupon, product-builder, tenant, reference-data, and shared theme/demo surfaces
- expanded `lang/ar/autoview.php` with exact translations for `config/workspace_products.php` workspace families, dashboard actions, runtime module labels/descriptions, seeded product names, seeded plan names, and seeded plan descriptions
- updated `tests/Feature/Localization/StaticHtmlTranslationMiddlewareTest.php`
  - exact phrase assertion for `Customer Payment Summary`
  - exact alt-text assertion for `User Img`
  - regression coverage for JavaScript template literals that include HTML and `${...}` placeholders
  - regression coverage for Accounting workspace catalog text and mixed plan lines such as `Accounting System Starter · متصل بمساحة العمل`
- updated `tests/Feature/Localization/StaticViewTranslationCoverageTest.php`
  - added ignores for non-UI code fragments, generated counters, domains, versions, lowercase slugs, and demo data patterns discovered during the full scan

Operational notes:
- database content is intentionally not auto-translated word-by-word; static UI copy must be added to `lang/ar/autoview.php` as exact phrases
- known seeded catalog values are treated as platform copy and covered through exact phrase translations
- no Kanakku theme Blade, CSS, or JS files were changed
- `php artisan route:cache` was not used

Verification:
- `php -l app/Http/Middleware/TranslateStaticHtmlText.php`
  - result: no syntax errors
- `php -l lang/ar/autoview.php`
  - result: no syntax errors
- `php -l tests/Feature/Localization/StaticHtmlTranslationMiddlewareTest.php`
  - result: no syntax errors
- `php artisan test tests/Feature/Localization/StaticHtmlTranslationMiddlewareTest.php tests/Feature/Localization/StaticViewTranslationCoverageTest.php tests/Feature/Localization/LanguageSwitchDirectionTest.php`
  - result: tests pass; PHP 8.5 reports the existing `PDO::MYSQL_ATTR_SSL_CA` deprecation from `config/database.php`

## General Ledger Arabic UI Cleanup - 2026-05-03

Package completed:
- expanded Arabic exact translations for the General Ledger/accounting review surfaces, including:
  - Accountant Review Pack
  - Import Templates
  - Financial Statement Builder
  - Financial Statement Notes
  - Multi-Currency And FX Revaluation
  - Exchange Rates
  - FX Revaluation
  - accountant review evidence rows and default seeded account labels
- fixed the oversized US/UAE flag on portal auth pages by making the shared language switcher carry its own fixed flag dimensions instead of relying on `.header .flag-nav` theme CSS
- added tenant-admin local CSS constraints for sidebar/header logo images to prevent the white logo artifact/line above the sidebar without editing Kanakku theme CSS

Important files changed:
- `lang/ar/autoview.php`
- `resources/views/shared/partials/language-switcher.blade.php`
- `resources/views/automotive/portal/layouts/portalLayout/partials/head.blade.php`
- `resources/views/automotive/admin/layouts/adminLayout/partials/head.blade.php`
- `tests/Feature/Localization/StaticHtmlTranslationMiddlewareTest.php`

Operational notes:
- no original Kanakku theme files under `public/theme` or theme CSS/JS were changed
- no database changes were made
- `php artisan route:cache` was not used

Verification:
- `php -l lang/ar/autoview.php`
- `php -l tests/Feature/Localization/StaticHtmlTranslationMiddlewareTest.php`
- `php -l resources/views/shared/partials/language-switcher.blade.php`
- `php -l resources/views/automotive/portal/layouts/portalLayout/partials/head.blade.php`
- `php -l resources/views/automotive/admin/layouts/adminLayout/partials/head.blade.php`
- `php artisan test tests/Feature/Localization/StaticHtmlTranslationMiddlewareTest.php tests/Feature/Localization/StaticViewTranslationCoverageTest.php tests/Feature/Localization/LanguageSwitchDirectionTest.php`
  - result: tests pass; PHP 8.5 reports the existing `PDO::MYSQL_ATTR_SSL_CA` deprecation from `config/database.php`

## RTL Two-Column Sidebar Gap Fix - 2026-05-03

Package completed:
- fixed the RTL white gap beside the two-column sidebar in the isolated automotive admin and portal layouts
- root cause was layout positioning, not JavaScript being placed inside `<p>` tags:
  - Kanakku RTL CSS moved `.page-wrapper` and `.header` by `276px`
  - sidebar child elements were partially adjusted for RTL
  - the fixed `.two-col-sidebar` wrapper itself was not explicitly pinned to `right: 0`
- added product-layout-scoped CSS overrides in the isolated layout head partials:
  - `body.layout-mode-rtl .two-col-sidebar { right: 0; left: auto; }`
  - `body.layout-mode-rtl .two-col-sidebar .twocol-mini { right: 0; left: auto; }`
  - `body.layout-mode-rtl .two-col-sidebar .sidebar { right: 60px; left: auto; }`
  - matching RTL offsets for `.page-wrapper` and `.header`
- added regression assertions that Arabic HTML translation keeps JavaScript template literals raw and does not emit `<p><script>` or `</script></p>`
- added regression coverage that isolated admin/portal layouts include the RTL two-column sidebar pinning rules

Important files changed:
- `resources/views/automotive/admin/layouts/adminLayout/partials/head.blade.php`
- `resources/views/automotive/portal/layouts/portalLayout/partials/head.blade.php`
- `tests/Feature/Localization/LanguageSwitchDirectionTest.php`
- `tests/Feature/Localization/StaticHtmlTranslationMiddlewareTest.php`

Operational notes:
- original Kanakku theme CSS/JS files under `public/theme` were not changed
- no database changes were made
- `php artisan route:cache` was not used

Verification:
- `php -l resources/views/automotive/portal/layouts/portalLayout/partials/head.blade.php`
  - result: no syntax errors
- `php -l resources/views/automotive/admin/layouts/adminLayout/partials/head.blade.php`
  - result: no syntax errors
- `php -l tests/Feature/Localization/StaticHtmlTranslationMiddlewareTest.php`
  - result: no syntax errors
- `php -l tests/Feature/Localization/LanguageSwitchDirectionTest.php`
  - result: no syntax errors
- `php artisan test tests/Feature/Localization/StaticHtmlTranslationMiddlewareTest.php tests/Feature/Localization/LanguageSwitchDirectionTest.php`
  - result: tests pass; PHP 8.5 reports the existing `PDO::MYSQL_ATTR_SSL_CA` deprecation from `config/database.php`

Follow-up correction:
- `theme/js/theme-script.js` is the Kanakku demo customizer script; it injects the purple gear and `#theme-setting` offcanvas into the page and applies layout settings from browser `localStorage`
- loading it inside product layouts can visually break the product UI, especially in RTL, by showing the demo customizer panel over the workspace
- isolated automotive admin and portal layouts must not load this demo customizer script
- product layouts now hide `.sidebar-contact` and `.sidebar-themesettings` defensively
- local `.two-col-sidebar` RTL overrides were removed because they split the Kanakku two-column sidebar; product layouts should rely on the original Kanakku RTL rules for sidebar/page/header positioning

## Static Translation Head Preservation Fix - 2026-05-04

Package completed:
- fixed the Arabic static HTML translation middleware so it no longer reparses the full HTML document through `DOMDocument`
- root cause: parsing a full document for translation can move or wrap `<head>` assets such as `<style>` and `<link>` into body paragraphs in some real responses
- `TranslateStaticHtmlText` now preserves the original `<head>` exactly and translates only the inner `<body>` HTML
- body fragments are wrapped in an internal temporary root before DOM parsing, then only the root children are emitted back into the response
- raw body blocks remain protected for `script`, `style`, `pre`, `code`, `textarea`, `svg`, and `canvas`
- added regression coverage for:
  - full HTML responses keeping `<head><style>...<link ...></head>` out of `<p>`
  - the real Arabic portal login route `/ar/workspace/login` not emitting `<p><style>` or `<p><link>`

Important files changed:
- `app/Http/Middleware/TranslateStaticHtmlText.php`
- `tests/Feature/Localization/StaticHtmlTranslationMiddlewareTest.php`
- `tests/Feature/Localization/LanguageSwitchDirectionTest.php`

Operational notes:
- no original Kanakku theme CSS/JS files under `public/theme` were changed
- no database changes were made
- `php artisan route:cache` was not used

Verification:
- `php -l app/Http/Middleware/TranslateStaticHtmlText.php`
  - result: no syntax errors
- `php -l tests/Feature/Localization/StaticHtmlTranslationMiddlewareTest.php`
  - result: no syntax errors
- `php -l tests/Feature/Localization/LanguageSwitchDirectionTest.php`
  - result: no syntax errors
- `php artisan test tests/Feature/Localization/StaticHtmlTranslationMiddlewareTest.php tests/Feature/Localization/LanguageSwitchDirectionTest.php`
  - result: tests pass; PHP 8.5 reports the existing `PDO::MYSQL_ATTR_SSL_CA` deprecation from `config/database.php`

Confirmed outcome:
- user confirmed the portal design is fixed after this middleware change
- future translation work must preserve document structure:
  - never translate or DOM-rewrite `<head>` content
  - keep CSS/JS asset tags outside the translation parser
  - translate only body content or explicit safe fragments
  - add route-level regression tests when a layout/rendering issue is reported

## Automotive Maintenance SaaS - Phase 2 Technician and Inspection Workflow - 2026-05-04

Package completed:
- added tenant migration `database/migrations/tenant/2026_05_04_020000_add_maintenance_workflow_tables.php`
- created maintenance workflow tables:
  - `maintenance_inspection_templates`
  - `maintenance_inspection_template_items`
  - `maintenance_inspections`
  - `maintenance_inspection_items`
  - `maintenance_diagnosis_records`
  - `maintenance_work_order_jobs`
  - `maintenance_job_time_logs`
  - `maintenance_qc_records`
  - `maintenance_qc_items`
- added Eloquent models and relationships for inspection templates, inspections, diagnosis records, technician jobs, time logs, and QC records
- extended `WorkOrder` with maintenance workflow relationships:
  - `inspections`
  - `diagnosisRecords`
  - `maintenanceJobs`
  - `qcRecords`
- added service-layer workflow logic:
  - `InspectionWorkflowService`
  - `TechnicianJobService`
  - `DiagnosisService`
  - `QualityControlService`
- added product-scoped controller:
  - `MaintenanceWorkflowController`
- added routes under existing `tenant.workspace.product:workshop-operations` group using `automotive.admin.maintenance.*`
- added operational Blade views:
  - workshop board
  - inspection templates
  - inspections list/detail/update/complete
  - technician jobs list/detail/actions
  - diagnosis records
  - QC records/start/complete
- updated Maintenance dashboard quick links
- extended Arabic and English maintenance translations

Important architecture notes:
- no duplicate customer/vehicle/work-order modules were created
- Phase 2 builds on Phase 1 maintenance foundation tables
- technician job status, work order status, vehicle status, and QC status remain separated
- parts/accounting remain optional and are not hard dependencies
- important workflow transitions write timeline entries through the existing `MaintenanceTimelineService`
- routes remain tenant/product scoped; `routes/tenant.php` was not removed or changed
- `php artisan route:cache` was not used

Verification:
- `php -l database/migrations/tenant/2026_05_04_020000_add_maintenance_workflow_tables.php`
  - result: no syntax errors
- `find app/Models/Maintenance app/Services/Automotive/Maintenance app/Http/Controllers/Automotive/Admin/Maintenance -type f -name '*.php' -print0 | xargs -0 -n1 php -l`
  - result: no syntax errors
- `php -l lang/en/maintenance.php && php -l lang/ar/maintenance.php`
  - result: no syntax errors
- `php artisan route:list --name=automotive.admin.maintenance --except-vendor`
  - result: 136 maintenance routes shown across localized/canonical/legacy route variants
- `php artisan view:clear && php artisan config:clear`
  - result: completed
- `php artisan view:cache && php artisan view:clear`
  - result: Blade templates compiled and cache cleared
- `APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter=workspace_root_is_the_canonical_tenant_entry_and_legacy_login_route_still_works`
  - result: passed with existing PHP deprecation notice reported by the test runner

Deployment reminder:
- run tenant migrations with `php artisan tenants:migrate`
- do not run `php artisan route:cache`

## Automotive Maintenance SaaS - Tenant Migration Identifier Length Fix - 2026-05-04

Issue fixed:
- MySQL rejected the auto-generated index name `maintenance_inspection_template_items_template_id_sort_order_index` because it exceeded the 64-character identifier limit.

Files updated:
- `database/migrations/tenant/2026_05_04_010000_add_maintenance_foundation_tables.php`
- `database/migrations/tenant/2026_05_04_020000_add_maintenance_workflow_tables.php`
- `database/migrations/tenant/2026_05_04_030000_add_maintenance_approval_delivery_warranty_tables.php`
- `database/migrations/tenant/2026_05_04_040000_create_core_generated_documents_tables.php`
- `database/migrations/tenant/2026_05_04_050000_add_maintenance_reports_and_advanced_ops_tables.php`

What changed:
- added explicit short names for long composite indexes and unique constraints in the new maintenance/document migrations
- replaced long auto-generated `service_catalog_item_id` foreign key names with short explicit names
- verified the original failing index name is no longer present
- scanned the 2026-05-04 tenant migrations for remaining auto-generated index/unique/foreign names longer than 64 characters; no remaining candidates were reported

Verification:
- `php -l` passed for the touched tenant migration files
- no `php artisan route:cache` was run

Operational reminder:
- if `php artisan tenants:migrate` failed midway before this fix, MySQL may have already created some Phase 2 tables while the migration row was not recorded
- on a dev/test tenant with no data in those new tables, drop the partially created Phase 2 tables before rerunning `php artisan tenants:migrate`
- do not drop tenant tables in production without taking a backup and checking whether data exists

## Automotive Maintenance SaaS - Phase 6A Optional Integration Layer - 2026-05-04

Package completed:
- added tenant migration `database/migrations/tenant/2026_05_04_060000_add_maintenance_integration_layer_tables.php`
- created `maintenance_parts_requests` as the maintenance-owned parts request layer
- added explicit short index and foreign key names in the new migration to avoid MySQL identifier length error 1059
- added model:
  - `App\Models\Maintenance\MaintenancePartsRequest`
- extended relationships:
  - `WorkOrder::partsRequests()`
  - `MaintenanceWorkOrderJob::partsRequests()`
  - `MaintenanceInvoice::accountingEvents()`
- added service-layer integration logic:
  - `App\Services\Automotive\Maintenance\MaintenanceIntegrationService`
- added product-scoped controller:
  - `App\Http\Controllers\Automotive\Admin\Maintenance\MaintenanceIntegrationController`
- added integration dashboard view:
  - `resources/views/automotive/admin/maintenance/integrations/index.blade.php`
- added product-scoped routes under `automotive.admin.maintenance.integrations.*`
- updated maintenance dashboard quick links with Integrations
- extended `config/workspace_products.php` integration contracts:
  - `automotive-parts` now supports `parts.requested` and `parts.issued`
  - `automotive-accounting` now supports `invoice.created` and `payment.received`
- extended Arabic and English maintenance translations

Important architecture notes:
- maintenance parts requests work as standalone manual requests even if Spare Parts is not active
- if Spare Parts is active, requests create `workspace_integration_handoffs` using `automotive-parts`
- if Spare Parts is inactive, the handoff is recorded as skipped instead of failing the maintenance workflow
- issuing an inventory-linked request can consume stock through the existing `WorkshopPartsIntegrationService`
- maintenance invoice sync creates an optional `automotive-accounting` handoff and an `AccountingEvent` only when Accounting is active
- if Accounting is inactive, the handoff is recorded as skipped and maintenance remains operational
- no product module instantiates or directly owns another product's records beyond optional handoff/adapter calls
- routes remain tenant/product scoped; `routes/tenant.php` was not removed or changed
- `php artisan route:cache` was not used

Verification:
- `php -l database/migrations/tenant/2026_05_04_060000_add_maintenance_integration_layer_tables.php`
  - result: no syntax errors
- `php -l app/Models/Maintenance/MaintenancePartsRequest.php`
  - result: no syntax errors
- `php -l app/Services/Automotive/Maintenance/MaintenanceIntegrationService.php`
  - result: no syntax errors
- `php -l app/Http/Controllers/Automotive/Admin/Maintenance/MaintenanceIntegrationController.php`
  - result: no syntax errors
- `php -l routes/products/automotive/admin.php`
  - result: no syntax errors
- `php -l lang/en/maintenance.php && php -l lang/ar/maintenance.php && php -l config/workspace_products.php`
  - result: no syntax errors
- `php artisan route:list --name=automotive.admin.maintenance.integrations --except-vendor`
  - result: 20 integration routes shown across localized/canonical/legacy route variants
- `php artisan view:cache && php artisan view:clear`
  - result: Blade templates compiled and cache cleared
- `APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter=workspace_root_is_the_canonical_tenant_entry_and_legacy_login_route_still_works`
  - result: passed with existing PHP deprecation notice reported by the test runner
- migration identifier scan for the new Phase 6A migration
  - result: no auto-generated index/unique/foreign names over MySQL's 64-character limit were reported

Deployment reminder:
- run tenant migrations with `php artisan tenants:migrate`
- do not run `php artisan route:cache`

Package progress:
- completed package 6 of 6 planned maintenance phases
- remaining packages from the original phased implementation plan: 0
- recommended follow-up package: harden customer portal/API/mobile readiness as a separate post-Phase-6 package

## Automotive Maintenance SaaS - Provisioning Retry Idempotency Fix - 2026-05-04

Issue fixed:
- product provisioning could fail after a previous partial migration failure with:
  - `SQLSTATE[42S01]: Base table or view already exists: 1050 Table 'maintenance_inspection_templates' already exists`
- root cause:
  - a previous tenant migration failure created some maintenance tables but did not record the migration as completed
  - the next provisioning retry attempted to recreate the same table

Files updated:
- `database/migrations/tenant/2026_05_04_010000_add_maintenance_foundation_tables.php`
- `database/migrations/tenant/2026_05_04_020000_add_maintenance_workflow_tables.php`
- `database/migrations/tenant/2026_05_04_030000_add_maintenance_approval_delivery_warranty_tables.php`
- `database/migrations/tenant/2026_05_04_040000_create_core_generated_documents_tables.php`
- `database/migrations/tenant/2026_05_04_050000_add_maintenance_reports_and_advanced_ops_tables.php`
- `database/migrations/tenant/2026_05_04_060000_add_maintenance_integration_layer_tables.php`

What changed:
- maintenance migrations now use a local `createIfMissing()` helper around all new table creation
- if a table already exists because of a partial prior run, the migration skips that table and continues creating the missing tables
- this avoids requiring manual table drops during normal provisioning retry
- this does not use `php artisan route:cache`

Verification:
- `for f in database/migrations/tenant/2026_05_04_0*.php; do php -l "$f" || exit 1; done`
  - result: no syntax errors in all 2026-05-04 maintenance/document tenant migrations

Operational reminder:
- after deploying this fix, retry provisioning or run `php artisan tenants:migrate`
- if a partial table exists with an old/incomplete structure from manual edits, inspect it before relying on automatic retry
- do not run `php artisan route:cache`

## Automotive Maintenance SaaS - Phase 4 Central mPDF Document Engine - 2026-05-04

Package completed:
- installed `mpdf/mpdf` through Composer
- added central document config:
  - `config/documents.php`
- added tenant migration:
  - `database/migrations/tenant/2026_05_04_040000_create_core_generated_documents_tables.php`
- created central document tables:
  - `generated_documents`
  - `document_snapshots`
  - `document_templates`
- added central document models:
  - `App\Models\Core\Documents\GeneratedDocument`
  - `App\Models\Core\Documents\DocumentSnapshot`
  - `App\Models\Core\Documents\DocumentTemplate`
- added central document services:
  - `DocumentGenerationService`
  - `DocumentRendererInterface`
  - `MpdfDocumentRenderer`
  - `DocumentStorageService`
  - `DocumentSnapshotService`
  - `DocumentNumberService`
  - `DocumentVerificationService`
  - `DocumentLayoutManager`
  - `DocumentHeaderBuilder`
  - `DocumentFooterBuilder`
- added document DTOs and contracts:
  - `DocumentRenderRequest`
  - `DocumentRenderResult`
  - `DocumentTemplateData`
  - `DocumentableInterface`
  - `DocumentTemplateBuilderInterface`
- bound `DocumentRendererInterface` to `MpdfDocumentRenderer` in `AppServiceProvider`
- added core document routes/controllers:
  - tenant-scoped `/documents/verify/{token}` route
  - `DocumentController@verify/download/preview`
- added reusable document layouts/components:
  - base layout
  - repeating mPDF header/footer
  - bilingual RTL/LTR styles
  - document metadata
  - QR verification component using mPDF barcode tag
  - signature box
  - totals table
- added Maintenance document service and controller:
  - `MaintenanceDocumentService`
  - `MaintenanceDocumentController`
- added Maintenance document generation routes under `automotive.admin.maintenance.*`
- added Maintenance document UI:
  - `resources/views/automotive/admin/maintenance/documents/index.blade.php`
- added Maintenance PDF templates:
  - check-in report
  - work order PDF
  - estimate PDF
  - delivery report
  - warranty certificate
- updated Maintenance dashboard quick links and Arabic/English translations

Important architecture notes:
- product modules do not instantiate mPDF directly
- all PDF rendering now goes through `DocumentGenerationService` and `MpdfDocumentRenderer`
- generated PDFs are stored under:
  - `tenants/{tenant_id}/documents/{product_code}/{module_code}/{document_type}/{year}/{month}/{document_number}-v{version}.pdf`
- document versioning keeps the same document number for the same documentable entity and increments `version`
- snapshots are stored as JSON beside each generated document
- headers/footers are controlled centrally and content templates do not own page headers or footers
- mPDF config uses UTF-8, Arabic-capable DejaVu Sans, auto language/font handling, and RTL/LTR direction
- verification pages expose only safe document metadata
- `routes/tenant.php` was extended but not removed
- `php artisan route:cache` was not used

Verification:
- `composer require mpdf/mpdf:^8.2`
  - result: installed `mpdf/mpdf v8.3.1` and updated Composer files
- `php -l database/migrations/tenant/2026_05_04_040000_create_core_generated_documents_tables.php && php -l config/documents.php`
  - result: no syntax errors
- `find app/Models/Core app/Services/Core app/Http/Controllers/Core app/Services/Automotive/Maintenance app/Http/Controllers/Automotive/Admin/Maintenance -type f -name '*.php' -print0 | xargs -0 -n1 php -l`
  - result: no syntax errors
- `php -l app/Providers/AppServiceProvider.php && php -l lang/en/maintenance.php && php -l lang/ar/maintenance.php`
  - result: no syntax errors
- `php artisan route:list --name=automotive.admin.maintenance.documents --except-vendor`
  - result: 16 document routes shown across localized/canonical/legacy route variants
- `php artisan route:list --name=documents.verify --except-vendor`
  - result: tenant verification routes shown
- direct mPDF renderer smoke test through `php artisan tinker --execute=...`
  - result: rendered PDF binary length 43746 bytes
- `php artisan view:cache && php artisan view:clear`
  - result: Blade templates compiled and cache cleared
- `APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter=workspace_root_is_the_canonical_tenant_entry_and_legacy_login_route_still_works`
  - result: passed with existing PHP deprecation notice reported by the test runner
- `composer audit --format=plain`
  - result: existing medium advisory for `league/commonmark` CVE-2026-33347; unrelated to mPDF install

Deployment reminder:
- run `composer install` after pulling these changes
- run tenant migrations with `php artisan tenants:migrate`
- do not run `php artisan route:cache`

## Automotive Maintenance SaaS - Phase 5 Reports and Advanced Operations - 2026-05-04

Package completed:
- added tenant migration `database/migrations/tenant/2026_05_04_050000_add_maintenance_reports_and_advanced_ops_tables.php`
- created advanced maintenance tables:
  - `maintenance_sla_policies`
  - `maintenance_delay_alerts`
  - `maintenance_preventive_rules`
  - `maintenance_preventive_reminders`
  - `maintenance_vehicle_health_scores`
  - `maintenance_service_recommendations`
  - `maintenance_technician_skill_profiles`
- added Eloquent models for SLA, delay alerts, preventive maintenance, health scores, recommendations, and technician skills
- extended `Vehicle` with health score, recommendation, and preventive reminder relationships
- added service-layer reporting and advanced operations logic:
  - `MaintenanceReportingService`
  - `MaintenanceAdvancedOperationsService`
- added product-scoped controller:
  - `MaintenanceReportsController`
- added routes under existing `tenant.workspace.product:workshop-operations` group using `automotive.admin.maintenance.*`
- added operational Blade views:
  - reports dashboard
  - advanced operations dashboard
- added CSV exports:
  - financial summary
  - technician productivity
  - branch performance
- added advanced refresh workflow:
  - evaluate SLA delays
  - generate preventive reminders
  - calculate vehicle health scores
  - generate service recommendations
- updated Maintenance dashboard quick links
- extended Arabic and English maintenance translations

Important architecture notes:
- reports read from existing operational tables and do not duplicate work-order/customer/vehicle data
- SLA policies are configurable and default global policies are seeded lazily
- delay alerts are stored so dashboard/SSE/notification integration can reuse them later
- preventive maintenance rules are product-owned and do not depend on spare parts/accounting
- vehicle health scores are basic and future-ready; scoring uses inspection signals, open work orders, and mileage
- recommendations are generated from health scores and preventive reminders
- routes remain tenant/product scoped; `routes/tenant.php` was not removed or changed
- `php artisan route:cache` was not used

Verification:
- `php -l database/migrations/tenant/2026_05_04_050000_add_maintenance_reports_and_advanced_ops_tables.php`
  - result: no syntax errors
- `find app/Models/Maintenance app/Services/Automotive/Maintenance app/Http/Controllers/Automotive/Admin/Maintenance -type f -name '*.php' -print0 | xargs -0 -n1 php -l`
  - result: no syntax errors
- `php -l app/Models/Vehicle.php && php -l lang/en/maintenance.php && php -l lang/ar/maintenance.php`
  - result: no syntax errors
- `php artisan route:list --name=automotive.admin.maintenance.reports --except-vendor`
  - result: report routes shown across localized/canonical/legacy route variants
- `php artisan route:list --name=automotive.admin.maintenance.advanced --except-vendor`
  - result: advanced operation routes shown across localized/canonical/legacy route variants
- `php artisan view:cache && php artisan view:clear`
  - result: Blade templates compiled and cache cleared
- `APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter=workspace_root_is_the_canonical_tenant_entry_and_legacy_login_route_still_works`
  - result: passed with existing PHP deprecation notice reported by the test runner

Deployment reminder:
- run tenant migrations with `php artisan tenants:migrate`
- do not run `php artisan route:cache`

## Automotive Maintenance SaaS - Phase 3 Approvals, Warranty, Delivery, Complaints, Notifications - 2026-05-04

Package completed:
- added tenant migration `database/migrations/tenant/2026_05_04_030000_add_maintenance_approval_delivery_warranty_tables.php`
- extended existing tables:
  - `maintenance_estimates.approval_token`
  - `work_orders.customer_tracking_token`
- created maintenance lifecycle tables:
  - `maintenance_approval_records`
  - `maintenance_lost_sales`
  - `maintenance_deliveries`
  - `maintenance_warranties`
  - `maintenance_warranty_claims`
  - `maintenance_complaints`
  - `maintenance_notifications`
- added Eloquent models:
  - `MaintenanceApprovalRecord`
  - `MaintenanceLostSale`
  - `MaintenanceDelivery`
  - `MaintenanceWarranty`
  - `MaintenanceWarrantyClaim`
  - `MaintenanceComplaint`
  - `MaintenanceNotification`
- extended `WorkOrder` with delivery, warranty, and complaint relationships
- extended `MaintenanceEstimate` with approval and lost-sales relationships
- added service-layer lifecycle logic:
  - `ApprovalWorkflowService`
  - `DeliveryWarrantyService`
  - `ComplaintService`
  - `MaintenanceNotificationService`
- added product-scoped controller:
  - `MaintenanceLifecycleController`
- added routes under existing `tenant.workspace.product:workshop-operations` group using `automotive.admin.maintenance.*`
- added operational Blade views:
  - customer approvals and lost sales
  - deliveries and vehicle release
  - warranties and warranty claims
  - complaints and complaint resolution
  - notification center and SSE stream endpoint
- updated Maintenance dashboard quick links
- extended Arabic and English maintenance translations

Important architecture notes:
- maintenance approvals are stored separately from estimate status for auditability
- rejected estimate lines create `maintenance_lost_sales` records for advisor follow-up
- delivery status updates remain separate from payment status and work order status
- warranty and warranty claim structures are maintenance-owned and do not depend on spare parts/accounting
- notifications are stored in tenant DB and exposed through SSE as lightweight event payloads only
- internal notes remain separate from customer-visible notes in complaints and delivery records
- routes remain tenant/product scoped; `routes/tenant.php` was not removed or changed
- `php artisan route:cache` was not used

Verification:
- `php -l database/migrations/tenant/2026_05_04_030000_add_maintenance_approval_delivery_warranty_tables.php`
  - result: no syntax errors
- `find app/Models/Maintenance app/Services/Automotive/Maintenance app/Http/Controllers/Automotive/Admin/Maintenance -type f -name '*.php' -print0 | xargs -0 -n1 php -l`
  - result: no syntax errors
- `php -l lang/en/maintenance.php && php -l lang/ar/maintenance.php`
  - result: no syntax errors
- `php artisan route:list --name=automotive.admin.maintenance --except-vendor`
  - result: 192 maintenance routes shown across localized/canonical/legacy route variants
- `php artisan view:cache && php artisan view:clear`
  - result: Blade templates compiled and cache cleared
- `php artisan view:clear && php artisan config:clear`
  - result: completed
- `APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/Automotive/Admin/TenantAdminAccessFlowTest.php --filter=workspace_root_is_the_canonical_tenant_entry_and_legacy_login_route_still_works`
  - result: passed with existing PHP deprecation notice reported by the test runner

Deployment reminder:
- run tenant migrations with `php artisan tenants:migrate`
- do not run `php artisan route:cache`
