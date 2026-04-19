# Upgrading to Wicked H6

## Routing changes

Wicked H6 now uses the Horde Rampage front controller for all request routing.
The per-endpoint PHP entry points (`display.php`, `history.php`, `diff.php`,
`preview.php`) are no longer used directly. All requests are handled by
`rampage.php` in the Horde base directory.

### What changed

- The wicked-specific `.htaccess` file has been removed.
- The `lighttpd-wicked.conf` rewrite rules are obsolete.
- The `urls.pretty` configuration setting is ignored. All URLs are clean
  by default (e.g. `/wicked/Wiki/Home` instead of
  `/wicked/display.php?page=Wiki/Home`).
- Old-style URLs (`display.php?page=...`, `history.php?page=...`, etc.)
  continue to work via secondary route definitions. Bookmarks and external
  links will not break.

### What administrators need to do

The required configuration depends on how Horde and Wicked are deployed.

---

## Scenario 1: Wicked under the Horde webroot (default)

This is the standard layout where all Horde applications share a single
document root:

```
/var/www/horde/web/          <- document root
  horde/                     <- Horde base (rampage.php lives here)
  wicked/                    <- Wicked (symlink or directory)
  turba/
  ...
```

**No webserver changes are needed.** The base Horde webserver configuration
already routes all unmatched requests to `rampage.php`. Rampage identifies the
target application from the URL path and dispatches to the correct controller.

Verify that your existing Horde configuration includes the fallback rule for
`rampage.php`. Examples for reference:

### Apache with mod_php or PHP-FPM

The Horde base `.htaccess` (or equivalent `<Directory>` block) must contain:

```apache
<Directory /var/www/horde/web>
    Options -Indexes +FollowSymLinks
    AllowOverride None
    Require all granted

    # PHP-FPM (adjust socket path to your PHP version)
    <FilesMatch \.php$>
        SetHandler "proxy:unix:/run/php/php8.4-fpm.sock|fcgi://localhost"
    </FilesMatch>

    RewriteEngine On
    RewriteBase /

    # Pass Authorization header to PHP
    RewriteRule .* - [env=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
    RewriteRule .* - [env=REDIRECT_HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Serve existing files/directories directly, route everything else
    # through rampage
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^(.*)$ horde/rampage.php [QSA,L]
</Directory>
```

If you are using mod_php instead of PHP-FPM, remove the `<FilesMatch>` block.

### Nginx with PHP-FPM

```nginx
server {
    listen 80;
    server_name horde.example.com;
    root /var/www/horde/web;
    index index.php;

    location / {
        try_files $uri $uri/ /horde/rampage.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;

        # Pass Authorization header
        fastcgi_param HTTP_AUTHORIZATION $http_authorization;
    }

    location ~ /\.(ht|git) {
        deny all;
    }

    location ~ /(composer|package)\.json$ {
        deny all;
    }
}
```

### Cleanup

If you previously added wicked-specific rewrite rules to your webserver
configuration (from the old `lighttpd-wicked.conf` or a custom Apache config),
remove them. They are no longer needed and may interfere with rampage routing.

---

## Scenario 2: Wicked at its own subdomain

If Wicked is served from a separate virtual host (e.g. `wiki.example.com`),
the webserver root still points at the bundle's `web/` directory — the same
directory that serves the main Horde instance. The key challenges are:

1. Routing requests to `rampage.php`
2. Configuring the registry so that asset URIs resolve correctly
3. Sharing the session cookie across subdomains

### Bundle layout

A typical horde/bundle deployment looks like this:

```
/var/www/horde-bundle/
  vendor/                    <- composer dependencies (horde/horde, horde/wicked, ...)
  web/                       <- document root for ALL vhosts
    horde/
      rampage.php            <- front controller
      .htaccess
    js/
      horde/
      wicked/
    themes/
      horde/
      wicked/
    static/
```

Both the main Horde vhost and the Wicked vhost point their document root at
`/var/www/horde-bundle/web/`. Rampage handles dispatching to the correct
application based on the URL and the registry configuration.

### Step 1: Route requests to rampage.php

#### Apache with PHP-FPM

```apache
<VirtualHost *:443>
    ServerName wiki.example.com
    DocumentRoot /var/www/horde-bundle/web

    <Directory /var/www/horde-bundle/web>
        Options -Indexes +FollowSymLinks
        AllowOverride None
        Require all granted

        <FilesMatch \.php$>
            SetHandler "proxy:unix:/run/php/php8.4-fpm.sock|fcgi://localhost"
        </FilesMatch>

        RewriteEngine On

        # Pass Authorization header to PHP
        RewriteRule .* - [env=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
        RewriteRule .* - [env=REDIRECT_HTTP_AUTHORIZATION:%{HTTP:Authorization}]

        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteRule ^(.*)$ horde/rampage.php [QSA,L]
    </Directory>

    <FilesMatch "^\.ht|composer\.json|package\.json">
        Require all denied
    </FilesMatch>

    # SSL configuration ...
</VirtualHost>
```

#### Nginx with PHP-FPM

```nginx
server {
    listen 443 ssl;
    server_name wiki.example.com;
    root /var/www/horde-bundle/web;
    index index.php;

    location / {
        try_files $uri $uri/ /horde/rampage.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;

        fastcgi_param HTTP_AUTHORIZATION $http_authorization;
    }

    location ~ /\.(ht|git) {
        deny all;
    }

    location ~ /(composer|package)\.json$ {
        deny all;
    }

    # SSL configuration ...
}
```

### Step 2: Registry overrides for asset URIs

When Wicked runs on its own subdomain, `Horde_PageOutput` must emit absolute
URLs for JavaScript, themes and static assets — the default relative paths
(`/js/wicked`, `/themes/wicked`) would resolve against `wiki.example.com`
which may or may not serve those paths depending on your vhost configuration.

Create a registry snippet, e.g.
`var/config/horde/registry.d/50-wicked-subdomain.php`:

```php
<?php
$this->applications['wicked']['webroot'] = 'https://wiki.example.com/';

// If wiki.example.com shares the same document root as the main Horde
// instance, the default js/themes/static paths work as-is.  If the
// subdomain vhost has a DIFFERENT document root, point these at absolute
// URLs that resolve to the bundle's web/ directory:
//
// $this->applications['wicked']['jsuri'] = 'https://horde.example.com/js/wicked';
// $this->applications['wicked']['themesuri'] = 'https://horde.example.com/themes/wicked';
// $this->applications['wicked']['staticuri'] = 'https://horde.example.com/static/';
```

If the subdomain vhost uses the same `web/` document root as the main Horde
vhost (the recommended setup), the default relative asset paths will resolve
correctly and only `webroot` needs to be overridden.

### Step 3: Session cookies

Horde uses a PHP session cookie to track authenticated users. By default the
cookie is scoped to the current hostname, so a session on `horde.example.com`
is not visible to `wiki.example.com`.

To share the session across subdomains, set a broad cookie domain in
`var/config/horde/conf.php`:

```php
$conf['cookie']['domain'] = '.example.com';
$conf['cookie']['path'] = '/';
```

**Security note:** A broad cookie domain means every subdomain under
`example.com` is equally trusted. If any subdomain is compromised, session
cookies for all other subdomains are exposed. Only use this when all
subdomains are under your control and have the same security level.

Upcoming JWT-based session support in Horde will provide a more secure
alternative for cross-subdomain authentication without requiring shared
cookies.

---

## Removed files

The following files are no longer needed and have been removed:

- `.htaccess` - wicked-specific rewrite rules (routing is handled by the
  Horde base configuration)
- `doc/lighttpd-wicked.conf` - lighttpd rewrite rules (replaced by the
  generic rampage fallback)

The legacy entry points (`display.php`, `index.php`, `history.php`,
`diff.php`, `preview.php`, `admin/attachments.php`) still exist but simply
forward to `rampage.php`. They do not need to be called directly.

## Configuration changes

- The `urls.pretty` setting in `config/conf.php` is no longer used. Wicked
  always generates clean URLs. You may remove it from `conf.php` and
  `conf.xml` at your convenience; leaving it has no effect.
