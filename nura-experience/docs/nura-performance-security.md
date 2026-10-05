# NURA - Host-level performance & security checklist

The `NURAX_Perf` and `NURAX_Security` modules apply the safe, code-level wins
automatically. The items below are host / server-level and must be done in your
hosting panel, LiteSpeed/Cloudflare, or `.htaccess`. They are what actually move
a PageSpeed score into the green and close the remaining security gaps.

## Performance (target: 1-3s, green PageSpeed)

1. **Full-page cache** - LiteSpeed Cache (you are on LiteSpeed): enable Cache,
   Browser Cache, and Object Cache (Redis/Memcached if available). Set TTL high;
   exclude cart/checkout/my-account.
2. **Image delivery** - enable the LiteSpeed image optimisation (or Cloudflare
   Polish): serve WebP, lossless-ish compression, and correctly sized images.
   Product mannequin shots are the heaviest asset on the page.
3. **CSS/JS minify + combine + defer** - in LiteSpeed Page Optimization: minify
   CSS/JS, load CSS asynchronously, defer JS, and remove unused CSS. Test after
   each toggle - revert any that breaks the layout or cart.
4. **CDN** - put Cloudflare in front of the site: free CDN, HTTP/3, Brotli,
   and edge caching for static assets.
5. **PHP 8.1+** and **HTTP/2 or HTTP/3** in the hosting panel.
6. **Fonts** - self-host or `font-display: swap`; preload only the one or two
   weights actually used.
7. **Lazy-load below-the-fold** - core already lazy-loads images; make sure the
   slider/hero is not lazy-loaded (LCP element should load eagerly).

### Optional `.htaccess` (gzip + browser caching) - if not handled by LiteSpeed
```apache
<IfModule mod_deflate.c>
  AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css application/javascript application/json image/svg+xml
</IfModule>
<IfModule mod_expires.c>
  ExpiresActive On
  ExpiresByType image/webp "access plus 1 year"
  ExpiresByType image/jpeg "access plus 1 year"
  ExpiresByType image/png  "access plus 1 year"
  ExpiresByType text/css   "access plus 1 month"
  ExpiresByType application/javascript "access plus 1 month"
  ExpiresByType font/woff2 "access plus 1 year"
</IfModule>
```

## Security (no space for hacking / spamming)

The plugin already: disables XML-RPC + pingback, hides the WP version, blocks
`?author=` enumeration, closes the REST users endpoint to guests, sets
nosniff/SAMEORIGIN/Referrer-Policy/Permissions-Policy headers, and rate-limits
the `nurax/v1` POST endpoints. Add, at host level:

1. **WAF + CDN** - Cloudflare (free) or the LiteSpeed/Imunify WAF: blocks bots,
   SQLi/XSS probes and most brute-force before it reaches WordPress.
2. **Security plugin** - Wordfence or Solid Security: login lockout, malware
   scan, 2FA for admins.
3. **HTTPS everywhere + HSTS** - once HTTPS is confirmed sitewide, add to
   `.htaccess`:
   ```apache
   Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"
   ```
4. **Strong Content-Security-Policy** - start in report-only, then enforce once
   your third-party scripts (M-Pesa, GA, Meta Pixel, fonts) are allow-listed.
   A too-strict CSP will break scripts, so test carefully. Example starting
   point (report-only):
   ```apache
   Header set Content-Security-Policy-Report-Only "default-src 'self'; img-src 'self' data: https:; script-src 'self' 'unsafe-inline' https://www.googletagmanager.com https://connect.facebook.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; connect-src 'self' https:;"
   ```
5. **Anti-spam on forms/comments** - Akismet or Cloudflare Turnstile on the
   contact/consultation forms; disable comments on products/pages if unused.
6. **Logins** - unique admin username (not "admin"), strong passwords, limit
   login attempts, and disable file editing:
   ```php
   define( 'DISALLOW_FILE_EDIT', true );
   ```
7. **Keep updated** - WordPress core, theme, WooCommerce and plugins on the
   latest versions; remove anything unused.
