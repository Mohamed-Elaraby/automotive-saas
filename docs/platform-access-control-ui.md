# Platform Access Control UI

## Package 12.1 Hotfix Notes

### Admin Session Isolation

Central SaaS Admin and Tenant Workspace Admin use separate guards:

- `admin`
- `automotive_admin`

Logout is scoped to the guard being logged out. Guard logout no longer invalidates the entire browser session because that removes every guard key stored in the same Laravel session cookie.

The login flow may regenerate the session id, but it must preserve the other guard's authenticated state.

### Plan Limit Source Of Truth

Product branch limits are resolved from the current active tenant product subscription and its current plan:

1. current plan `plan_limits.branch_limit`
2. current plan `plans.max_branches`
3. subscription snapshot `tenant_product_subscriptions.branch_limit` as fallback only
4. active `extra_branch` add-ons

When SaaS Admin changes a legacy subscription plan, the tenant product subscription mirror is synced immediately and its denormalized snapshots are refreshed.

### Workspace Owner Access

The tenant is not a user. The Workspace Owner is the primary tenant user, currently represented by user id `1` inside the tenant database.

Workspace Owner behavior:

- has implicit workspace management access
- can manage Access Control, users, products, branches, billing, roles, and permissions through the management layer
- does not require explicit product access records to appear as authorized in management UI
- does not consume product seats by default
- can choose any active branch that is enabled for a subscribed product

### Sync Owner Access

`Sync Owner Access` creates explicit records for compatibility flows:

- active/trialing subscribed products in `tenant_user_product_access`
- enabled active product branches in `tenant_user_product_branches`

The action is idempotent and does not enable new product branches. Owner product access records are marked with `metadata.consumes_seat = false`.

### Emergency Recovery

Use this command when a tenant owner account must be restored:

```bash
php artisan tenant:grant-owner client-1 admin@example.com --sync-access
```

If the tenant database name contains a dash, wrap it with backticks in direct MySQL checks:

```sql
USE `tenant_client-1`;
```

Never run:

```bash
php artisan route:cache
```
