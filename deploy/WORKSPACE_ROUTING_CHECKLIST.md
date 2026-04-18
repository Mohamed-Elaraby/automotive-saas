# Workspace Routing Checklist

Use this when `https://seven-scapital.com/workspace/portal` returns `404`.

## 1. Confirm Laravel route registration

Run:

```bash
php artisan optimize:clear
php artisan route:list --path=workspace/portal --except-vendor
php artisan tenancy:diagnose-workspace-routing
```

Expected:
- `workspace/portal` exists as `automotive.portal`
- the diagnosis command says Laravel routing is internally consistent

## 2. Confirm production `.env`

Required values:

```env
APP_URL=https://seven-scapital.com
ASSET_URL=https://seven-scapital.com
SESSION_DOMAIN=.seven-scapital.com
SESSION_SECURE_COOKIE=true
SANCTUM_STATEFUL_DOMAINS=seven-scapital.com,www.seven-scapital.com
```

## 3. Confirm web server binding

The web server must serve the same Laravel `public/` directory for:
- `seven-scapital.com`
- `www.seven-scapital.com`
- `*.seven-scapital.com`

If the browser gets `404` but Laravel tests show `/workspace/portal` exists, the `404` is coming from the web server or upstream proxy before Laravel.

## 4. Confirm the document root

It must point to:

```text
/var/www/automotive-saas/public
```

Not the project root.

## 5. Confirm rewrite/front controller behavior

For Nginx:
- `try_files $uri $uri/ /index.php?$query_string;`

For Apache:
- `AllowOverride All`
- Laravel `public/.htaccess` must be active

## 6. Confirm request flow manually

Guest central access:

```bash
curl -I https://seven-scapital.com/workspace/portal
```

Expected:
- `302` or `303` to `/workspace/login`

Legacy central access:

```bash
curl -I https://automotive.seven-scapital.com/workspace/portal
```

Expected:
- `308` to `https://seven-scapital.com/workspace/portal`

Tenant access:

```bash
curl -I https://demo.seven-scapital.com/workspace
```

Expected:
- Laravel tenant response, not web-server `404`
