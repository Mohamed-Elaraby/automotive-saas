# Product-Scoped SaaS Architecture Roadmap

## Purpose
This roadmap is the controlled implementation plan for moving the existing Laravel 10 multi-tenant SaaS into a professional multi-product platform architecture.

It is intentionally based on the current codebase, not a greenfield design. The existing tenant routing model, `stancl/tenancy` setup, Automotive route names under `automotive.admin.*`, customer portal billing direction, workspace product manifest, accounting runtime, maintenance runtime, central mPDF document engine, and localization foundation must be reused.

## Current Architecture Inventory

### Already Reusable
- Central platform product catalog exists through `products`, `plans`, `product_capabilities`, and product-aware plan billing.
- Central `tenant_product_subscriptions` exists and already includes activation/provisioning status.
- Workspace runtime product discovery exists through `config/workspace_products.php`, `TenantWorkspaceProductService`, `WorkspaceManifestService`, `WorkspaceProductFamilyResolver`, and `EnsureTenantHasWorkspaceProduct`.
- Tenant runtime users exist once per tenant database in `users`; central `tenant_users` links portal users to tenants.
- Tenant runtime branches exist centrally in tenant database table `branches`.
- Tenant runtime customers, suppliers, vehicles, work orders, stock, accounting, and maintenance tables already exist.
- Accounting runtime is journal-driven and should remain the tenant financial source of truth.
- Central document generation is already present under `app/Services/Core/Documents` and tenant tables `generated_documents`, `document_snapshots`, and `document_templates`.
- Notifications exist for admin, customer portal, and maintenance, but they are not yet unified behind one product-aware tenant notification engine.
- Audit exists in admin, accounting, and maintenance, but not yet as one reusable tenant audit service/table.
- Approval exists in maintenance and accounting workflows, but not yet as one central approval engine.
- English/Arabic localization and RTL/LTR layout support are already implemented.

### Main Architectural Gaps
- Product identity is inconsistent: central billing mostly uses `product_id`, runtime uses product family/code, and documents use `product_code`.
- Product access is subscription-scoped only; per-user product access and per-product seat enforcement are missing.
- Branches are tenant-central, but product branch activation and user branch access are missing.
- Product-scoped roles/permissions are currently encoded in product-specific columns such as `accounting_role`, `accounting_permissions`, `maintenance_role`, and `maintenance_permissions`.
- Plan limits are still legacy columns (`max_users`, `max_branches`, etc.) plus billing feature pivots; there is no normalized product-aware plan limit/add-on model.
- Customers and suppliers are central tenant entities, but they need explicit product profile boundaries and cleanup rules.
- Employees do not exist as a tenant business entity separate from login users.
- Attachments are maintenance-specific; a central attachment engine is missing.
- Numbering exists as document and maintenance services, but the requested central `numbering_sequences` model/table is missing.
- Product-scoped settings, reports, approval, audit, search, activity timeline, and import/export engines need central foundations.
- Routes are still stored under `routes/products/automotive/*` even when modules include Accounting and Parts Inventory. Route names must remain stable while internal ownership is gradually clarified.

## Total Required Changes
Total Required Changes: 72

## Package Breakdown

### Package 1: Architecture Inventory And Execution Guardrails
Changes 1-8.

1. Capture current architecture inventory from models, services, controllers, middleware, providers, migrations, routes, config, views, translations, and tests.
2. Identify reusable existing components and prevent duplicated replacement work.
3. Identify polluted or product-specific logic that must be migrated behind central services.
4. Define the full 72-change implementation list.
5. Split the implementation into small packages with stable package numbers.
6. Document non-negotiable constraints: keep `routes/tenant.php`, keep `automotive.admin.*`, do not use `php artisan route:cache`, and keep central mPDF for PDFs.
7. Update `PROJECT_AI_CONTEXT.md` with the new architecture-roadmap status.
8. Verify documentation and context changes without runtime behavior changes.

### Package 2: Product Identity, Subscriptions, Plans, Limits, And Add-Ons
Changes 9-16.

9. Add a compatibility-safe `product_key` mirror to `tenant_product_subscriptions`, backfilled from `products.code`.
10. Add subscription commercial entitlement columns for `included_seats`, `extra_seats`, `branch_limit`, `usage_limits`, `current_period_start`, and `current_period_end`.
11. Add normalized `plan_limits` with `product_key`, `plan_id`, `limit_key`, and `limit_value`.
12. Add normalized `subscription_addons` with tenant/product/add-on quantity and status.
13. Add product-key aware relationships/helpers while keeping existing `product_id` joins working.
14. Extend plan seed/bootstrap logic to populate default normalized limits without breaking existing plan columns.
15. Add service tests for product-key subscription lookup and active/trialing entitlement base checks.
16. Update admin/portal read paths to prefer product-aware entitlement data where safe.

### Package 3: Tenant Product Access And Product Seats
Changes 17-24.

17. Add `tenant_user_product_access` tenant table with user, product_key, role state, and enabled status.
18. Add a central `ProductAccessService`.
19. Add a central `ProductSeatService` or seat calculation inside `ProductAccessService`.
20. Enforce active/trialing product subscription before granting access.
21. Enforce included plus extra seat limits before granting access.
22. Migrate maintenance/accounting role reads to product access records while keeping legacy columns as fallback during transition.
23. Update Tenant Admin user create/edit UI to manage product access by product.
24. Add tests for user product access and seat limit enforcement.

### Package 4: Branch Product Activation And Branch Access
Changes 25-32.

25. Expand central tenant `branches` with manager, city/emirate/country/timezone fields where missing.
26. Add `tenant_product_branches` tenant table.
27. Add `tenant_user_product_branch_access` tenant table.
28. Add `ProductBranchAccessService`.
29. Enforce product branch limit before enabling a branch for a product.
30. Apply branch activation checks to product module entry middleware.
31. Update branch UI to show central branch data and per-product activation.
32. Add tests for product branch limits and user branch access.

### Package 5: Product-Scoped Roles, Permissions, Middleware, And Policies
Changes 33-40.

33. Add `product_roles` tenant table.
34. Add `product_permissions` tenant table or config-backed permission catalog with persisted role grants.
35. Add `product_role_user` or attach role assignment through product access records.
36. Create a product-scoped permission naming catalog such as `automotive.work_orders.view`.
37. Add `EnsureProductEntitlement` middleware for subscription, access, branch, feature, limit, and permission checks.
38. Replace product-specific role columns in enforcement paths with the central permission service, keeping fallback compatibility.
39. Add policies where controller-level authorization is currently missing.
40. Add tests for product-scoped permissions.

### Package 6: Central Business Entities
Changes 41-48.

41. Normalize customers as tenant-central entities and document product profile boundaries.
42. Add customer product profile tables only where product-specific fields are required.
43. Normalize suppliers/vendors as tenant-central entities.
44. Add supplier/vendor product profile tables only where needed.
45. Add central `employees` tenant table separate from login users.
46. Link employees optionally to users.
47. Migrate automotive technicians/service advisors to employee-aware references where safe.
48. Add tests for user/employee separation and central customer/supplier reuse.

### Package 7: Central Numbering, Documents, Attachments, And Storage Entitlements
Changes 49-56.

49. Add tenant `numbering_sequences` with product_key, document_type, branch/year, prefix, next_number, padding, and reset strategy.
50. Implement concurrency-safe `NumberingSequenceService`.
51. Migrate maintenance numbering and document numbering to the central numbering service.
52. Add central tenant `attachments` table with product_key, branch_id, morph target, storage metadata, and uploader.
53. Implement `AttachmentService` with plan-controlled file size, type, retention, and storage checks.
54. Keep existing maintenance attachments as a compatibility source or migrate them safely.
55. Harden central document engine template selection by product_key/document_type/language.
56. Add tests for numbering concurrency basics, document rendering basics, and attachment limits.

### Package 8: Central Notifications, Approval, Audit, Reports, And Settings
Changes 57-64.

57. Add central tenant notification event model/service with product_key and channel rules.
58. Add central approval workflow model/service with product_key and plan-controlled approval modes.
59. Add central tenant audit log model/service with product_key, branch_id, user_id, entity, old/new values, IP, and user agent.
60. Add central reports registry with product-specific and cross-product report definitions.
61. Add product-aware report entitlement checks for advanced reports.
62. Split settings into tenant settings, product settings, and branch settings.
63. Migrate maintenance/accounting settings reads behind product settings where safe.
64. Add tests for notifications, approvals, audit logs, and report entitlement checks.

### Package 9: References, Warehouses, Accounting Integration, Search, Timeline, Import/Export, Cleanup
Changes 65-72.

65. Confirm central countries/currencies/taxes and add tenant base currency/product tax profile gaps.
66. Add tenant warehouses linked to branches and product activation where needed.
67. Formalize accounting core integration contracts so other products post financial events without direct polluted coupling.
68. Add tenant search foundation respecting product, branch, and permission access.
69. Add central activity timeline foundation with product_key and entity scopes.
70. Add import/export engine registry with product-specific types and plan controls.
71. Create cleanup/deprecation map for unused routes/controllers/views/services/migrations without risky deletion.
72. Run final regression, static audits, and update architecture context for the next QA/hardening phase.

## Execution Rules
- Never delete `routes/tenant.php`.
- Never run `php artisan route:cache`.
- Keep Automotive Admin route names under `automotive.admin.*`.
- New migrations must use explicit short index and foreign key names.
- New Blade partials must use `_form.blade.php`.
- Future PDFs must use the central mPDF document engine under `app/Services/Core/Documents`.
- Runtime changes must keep existing tenant workflows working while introducing central services behind compatibility layers.
