# Access Control UI Acceptance Checklist

Use this checklist after deploying Phase 2 Access Control UI & Enforcement.

Do not run:

```bash
php artisan route:cache
```

## Workspace Owner

- Login expectation: Workspace Owner can log in to the tenant workspace.
- Product access expectation: owner shows Owner Access, Implicit Full Access, and Does not consume product seat.
- Branch access expectation: owner can see all active enabled branches for subscribed products.
- Visible menu expectation: Access Control, Users, Roles, Products, Branches, Audit Logs, and Diagnostics are visible.
- Hidden menu/action expectation: no owner-critical management action is hidden from the owner.
- Effective permissions expectation: access management permissions resolve as allowed through owner implicit access.
- Data visibility expectation: owner can see branch-scoped records across enabled product branches.
- Forbidden direct URL expectation: owner should not receive 403 for protected Access Control routes.
- Audit/diagnostics expectation: owner can view audit logs and run diagnostics for users, permissions, and routes.

## Branch Manager

- Login expectation: Branch Manager can log in when product access is active.
- Product access expectation: automotive_service product access is active.
- Branch access expectation: only assigned branches are visible/selectable.
- Visible menu expectation: branch operations and allowed work-order/report views are visible.
- Hidden menu/action expectation: billing and global role management are hidden unless explicitly granted.
- Effective permissions expectation: branch operations, work orders, estimates approval, and branch reports are granted by role.
- Data visibility expectation: records from unassigned branches are hidden and direct record access is blocked.
- Forbidden direct URL expectation: direct role-management or billing URLs return 403.
- Audit/diagnostics expectation: forbidden protected Access Control attempts create audit records for authorized diagnostics review.

## Service Advisor

- Login expectation: Service Advisor can log in when product and branch access are active.
- Product access expectation: automotive_service access is active.
- Branch access expectation: assigned service branch is visible/selectable.
- Visible menu expectation: customers, vehicles, check-ins, work orders, and estimates workflow links are visible when module routes are available.
- Hidden menu/action expectation: delete, billing, user, and role management actions are hidden.
- Effective permissions expectation: customer/vehicle create/edit, check-in create, work-order create/edit, and estimate create/edit are granted.
- Data visibility expectation: service records are limited to allowed branch scope.
- Forbidden direct URL expectation: direct delete, billing, or role-management requests are blocked.
- Audit/diagnostics expectation: diagnostics explain any missing role, branch, or product access.

## Technician

- Login expectation: Technician can log in when product and branch access are active.
- Product access expectation: automotive_service access is active.
- Branch access expectation: assigned workshop branch is visible/selectable.
- Visible menu expectation: assigned jobs, inspections, and attachments actions are visible where implemented.
- Hidden menu/action expectation: invoices, payments, reports, users, roles, and billing management are hidden.
- Effective permissions expectation: jobs and inspections update permissions are granted; invoice/report/access permissions are denied.
- Data visibility expectation: technician sees only allowed-branch task records.
- Forbidden direct URL expectation: direct access to finance or access-management routes is blocked.
- Audit/diagnostics expectation: diagnostics show missing permission or missing role when a task is blocked.

## Accountant

- Login expectation: Accountant can log in when product and branch access are active.
- Product access expectation: automotive_service access is active.
- Branch access expectation: assigned finance branches are visible/selectable.
- Visible menu expectation: invoices, payments, and finance reports are visible where module routes are available.
- Hidden menu/action expectation: technical job update actions and access-management actions are hidden.
- Effective permissions expectation: invoice view/create/edit/print, payment create/post/reconcile, and report view/export are granted.
- Data visibility expectation: finance records are filtered to allowed branches where branch-scoped.
- Forbidden direct URL expectation: direct role, branch, or user management URLs are blocked unless explicitly granted.
- Audit/diagnostics expectation: diagnostics distinguish missing branch access from missing permission.

## Viewer

- Login expectation: Viewer can log in when product and branch access are active.
- Product access expectation: automotive_service access is active.
- Branch access expectation: assigned viewer branch is visible/selectable.
- Visible menu expectation: read-only pages are visible where view permissions exist.
- Hidden menu/action expectation: create, edit, delete, approve, export, billing, users, roles, and branch management actions are hidden.
- Effective permissions expectation: view permissions are granted; create/edit/delete/manage/export permissions are denied.
- Data visibility expectation: only allowed-branch records are visible.
- Forbidden direct URL expectation: direct management routes and POST/PUT/DELETE requests are blocked and do not modify data.
- Audit/diagnostics expectation: forbidden Access Control attempts are logged for owner/admin review.

## Cross-Role Checks

- `/workspace/admin/access` renders for owner and authorized access managers.
- `/workspace/admin/access/users/{user}` shows products, branches, roles, effective permissions, warnings, and activity/audit context.
- Permission Matrix saves only for authorized users.
- Branch context selector lists only eligible branches for non-owners.
- Audit Logs filters work by actor, target, product, branch, event, and date range.
- Diagnostics result cards show subscription, product access, branch access, roles, permissions, owner access, final decision, and suggested fix.
- Backend enforcement blocks direct forbidden routes even when UI buttons are hidden.
- Branch-scoped filtering hides forbidden branch records in lists, dashboards, reports, attachments, notifications, and direct record access where implemented.
