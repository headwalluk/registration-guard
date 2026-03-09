# Hosting & Server Notes

Notes for system administrators, hosting providers, and anyone managing the server environment.

---

## Caching Compatibility

### Page Caching

Registration Guard is compatible with full-page caching (Varnish, Nginx FastCGI cache, WP Super Cache, W3 Total Cache, etc.).

The nonce challenge works because:
- The registration page itself can be cached normally
- The nonce is fetched via a separate AJAX request to `admin-ajax.php`
- `admin-ajax.php` is universally excluded from page caching by all major caching solutions

**No special cache rules are needed.**

### Object Caching

Registration Guard uses WordPress transients for rate limiting. If your site uses a persistent object cache (Redis, Memcached), transients are stored there automatically. This is expected behaviour and improves performance.

---

## Email Delivery

Registration Guard sends verification emails via `wp_mail()`. It does not connect to external email services directly.

For reliable verification email delivery:

- Configure an SMTP plugin (WP Mail SMTP, Post SMTP, etc.)
- Ensure your domain has SPF, DKIM, and DMARC records
- Verification emails are plain text with no attachments -- they should pass most spam filters

If verification emails are not arriving, the issue is with your site's mail configuration, not Registration Guard.

---

## WP-Cron

Registration Guard schedules two WP-Cron tasks:

| Hook | Schedule | Purpose |
|------|----------|---------|
| `rg_cleanup_unverified_accounts` | Hourly | Delete unverified accounts past the verification window |
| `rg_prune_event_log` | Daily | Remove log entries older than 30 days |

### Low-Traffic Sites

WordPress cron depends on site traffic to trigger. On low-traffic sites, cleanup tasks may run slightly later than scheduled. This is acceptable -- it's a cleanup task, not a security-critical timer.

For precise scheduling, configure a system cron to trigger WordPress cron:

```bash
*/5 * * * * curl -s https://example.com/wp-cron.php?doing_wp_cron > /dev/null 2>&1
```

Or via WP-CLI:

```bash
*/5 * * * * cd /path/to/wordpress && wp cron event run --due-now > /dev/null 2>&1
```

And disable WordPress's built-in cron trigger in `wp-config.php`:

```php
define( 'DISABLE_WP_CRON', true );
```

---

## Database

Registration Guard creates one custom table on activation:

| Table | Purpose |
|-------|---------|
| `{prefix}rg_log` | Event log (registrations, verifications, blocks, deletions) |

The table is created via `dbDelta()` and uses standard WordPress charset/collation settings.

Log entries are automatically pruned daily (entries older than 30 days are deleted in batches of 1000).

### Data in Core Tables

Registration Guard also stores data in WordPress core tables:

- **`wp_usermeta`:** `_rg_email_verified`, `_rg_verification_token`, `_rg_token_created` (per-user verification state)
- **`wp_options`:** Settings prefixed with `regguard_` (plugin configuration)
- **Transients:** Rate limiting data prefixed with `rg_` (auto-expiring)

---

## Uninstallation

When the plugin is deleted (not just deactivated), the `uninstall.php` script runs and removes:

- All `regguard_*` options from `wp_options`
- All `_rg_*` user meta from `wp_usermeta`
- All `rg_*` transients
- The `{prefix}rg_log` custom table
- All scheduled cron hooks

**Deactivation** only unschedules the cron hooks. All data is preserved for reactivation.

---

## Security Considerations

### Rate Limiting

The nonce endpoint is rate-limited per IP address using WordPress transients. This prevents bots from harvesting nonces at scale.

### Verification Tokens

Tokens are generated using `random_bytes()` and stored as hashes via `wp_hash_password()`. Plaintext tokens are never stored in the database.

### Role Safety

The account cleanup cron only deletes users with the `subscriber` or `customer` role. Accounts with `administrator`, `editor`, `author`, `contributor`, or `shop_manager` roles are never auto-deleted, even if they are technically unverified.

### Referer Validation

The AJAX nonce endpoint validates the HTTP referer before issuing a nonce. Requests with no referer or an invalid referer are rejected without generating a nonce.

---

## Deploying to Client Sites

If you are deploying Registration Guard across multiple client sites:

- The plugin works with default settings out of the box -- no per-site configuration required
- All three layers are enabled by default (geo-restriction requires WooCommerce)
- The plugin creates its database table on activation and cleans up fully on deletion
- No external API keys or third-party service accounts are needed
