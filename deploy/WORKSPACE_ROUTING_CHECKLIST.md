# Production Routing Checklist

Use this when the production browser shows 404s for app routes or theme assets.

## 1. Confirm the intended production split

The production server runs three separate Laravel projects:

- `seven-scapital.com` and `www.seven-scapital.com`
  - Nginx file: `/etc/nginx/sites-available/saas`
  - document root: `/var/www/saas/public`
  - purpose: public frontend/marketing/customer-facing pages only
- system app
  - Nginx file: `/etc/nginx/sites-available/automotive`
  - document root: `/var/www/automotive/public`
  - purpose: the multi-product SaaS/workspace system
  - note: the Nginx file name is legacy; it must not mean the app is automotive-only
- `spareparts.seven-scapital.com`
  - Nginx file: `/etc/nginx/sites-available/spareparts`
  - document root: `/var/www/spareparts/public`
  - purpose: the standalone spare-parts Laravel project

Do not put `*.seven-scapital.com` on the `saas` vhost. Tenant workspace
subdomains must be handled by the system app unless a more specific standalone
project vhost exists, such as `spareparts.seven-scapital.com`.

## 2. Confirm Nginx server names

Run on production:

```bash
sudo nginx -T | grep -n "server_name"
```

Expected shape:

```nginx
server_name seven-scapital.com www.seven-scapital.com;
server_name system.seven-scapital.com *.seven-scapital.com;
server_name spareparts.seven-scapital.com;
```

Replace `system.seven-scapital.com` with the real system hostname if production
uses a different one.

## 3. Confirm document roots

Run:

```bash
sudo nginx -T | grep -n "root /var/www"
```

Expected:

```nginx
root /var/www/saas/public;
root /var/www/automotive/public;
root /var/www/spareparts/public;
```

## 4. Confirm Laravel front-controller behavior

Every Laravel vhost must include:

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php$ {
    include snippets/fastcgi-php.conf;
    fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    fastcgi_param DOCUMENT_ROOT $realpath_root;
}
```

## 5. Confirm each app's production env

For `/var/www/saas`, `APP_URL` should be:

```env
APP_URL=https://seven-scapital.com
```

For `/var/www/automotive`, use the system hostname, not an automotive-branded
hostname:

```env
APP_URL=https://system.seven-scapital.com
ASSET_URL=https://system.seven-scapital.com
SESSION_DOMAIN=.seven-scapital.com
SESSION_SECURE_COOKIE=true
```

For `/var/www/spareparts`:

```env
APP_URL=https://spareparts.seven-scapital.com
```

If the actual system hostname is not `system.seven-scapital.com`, use the real
hostname consistently in Nginx and `.env`.

## 6. Confirm theme assets are requested from the correct app

If the failing page is on:

```text
https://seven-scapital.com/login
```

then the request is handled by the `saas` app. Its assets must exist under:

```text
/var/www/saas/public/theme
```

or the `saas` layout must stop referencing `/theme/...`.

If the failing page is on the system hostname, the system app theme files live in:

```text
/var/www/automotive/public/theme
```

So system pages must request:

```text
https://system.seven-scapital.com/theme/...
```

not:

```text
https://seven-scapital.com/theme/...
```

If the frontend app intentionally reuses the same theme as the system app, use a
deliberate deployment step or Nginx alias on the `saas` vhost. Do not rely on
the `automotive` vhost to serve assets for `seven-scapital.com`.

Quick checks:

```bash
curl -I https://seven-scapital.com/theme/css/bootstrap.min.css
curl -I https://system.seven-scapital.com/theme/css/bootstrap.min.css
curl -I https://system.seven-scapital.com/theme/js/script.js
curl -I https://system.seven-scapital.com/theme/img/logo.svg
```

Expected:

- `200`
- not Laravel HTML
- not `404`

## 7. Reload safely after changes

Run:

```bash
sudo nginx -t
sudo systemctl reload nginx
cd /var/www/automotive
php artisan config:clear
php artisan cache:clear
php artisan view:clear
```

Do not run `php artisan route:cache`.
