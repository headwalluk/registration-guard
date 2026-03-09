# Contributing

Thanks for your interest in contributing to Registration Guard.

---

## Requirements

- PHP 8.0+
- WordPress 6.0+ development environment
- [PHP_CodeSniffer](https://github.com/PHPCSStandards/PHP_CodeSniffer) with [WordPress Coding Standards](https://github.com/WordPress/WordPress-Coding-Standards)

---

## Code Quality

There is no build step, test framework, or package.json. Code quality is enforced via phpcs:

```bash
phpcs                              # Check all files
phpcbf                             # Auto-fix violations
phpcs includes/class-plugin.php    # Check a specific file
```

All code must pass `phpcs` with zero errors before committing.

---

## Pre-Commit Workflow

1. Run `phpcs` to check for violations
2. Run `phpcbf` to auto-fix what it can
3. Run `phpcs` again to verify clean
4. Stage changed files
5. Commit

---

## Commit Messages

Format: `type: brief description`

Types: `feat:` `fix:` `chore:` `refactor:` `docs:` `style:` `test:`

Examples:
- `feat: add nonce challenge endpoint`
- `fix: handle missing referer in nonce request`
- `chore: update phpcs configuration`

---

## Coding Standards

Registration Guard follows the [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/) with these project-specific conventions:

### PHP

- **Namespace:** `Registration_Guard`
- **No `declare(strict_types=1)`** -- breaks WordPress interop
- **Single-Entry Single-Exit (SESE):** One return per function, at the end
- **Type hints and return types** on all functions
- **Constants for all magic strings/numbers** in `constants.php`
- **Dates as human-readable strings** (`Y-m-d H:i:s T`), not Unix timestamps
- **Boolean options:** Always use `filter_var()` with `FILTER_VALIDATE_BOOLEAN`

### Templates & HTML Output

**All HTML output must use `printf()` or `echo`.** This applies everywhere: views, email templates, settings page callbacks, admin pages.

```php
// Correct
printf(
    '<label><input type="checkbox" name="%s" value="1" %s /> %s</label>',
    esc_attr( OPT_NONCE_ENABLED ),
    checked( $value, true, false ),
    esc_html__( 'Enable nonce challenge', 'registration-guard' )
);

// Wrong -- no inline HTML with PHP snippets
?>
<label>
    <input type="checkbox" name="<?php echo esc_attr( OPT_NONCE_ENABLED ); ?>" />
    <?php esc_html_e( 'Enable nonce challenge', 'registration-guard' ); ?>
</label>
<?php
```

This prevents whitespace bleeding into attributes and values, and makes the code easier to debug.

### JavaScript

- No inline JavaScript -- all JS in files under `assets/js/`
- Use class-based selectors (not IDs)
- Modern JS (no jQuery dependency unless necessary)

### Security

- Sanitise all input, escape all output
- Verify nonces on all form handlers
- Check capabilities before privileged operations
- Tokens hashed with `wp_hash_password()`, verified with `wp_check_password()`

---

## Project Structure

```
registration-guard/
├── registration-guard.php       # Main plugin file, bootstrap
├── constants.php                # All constants
├── functions.php                # Public API (global scope)
├── functions-private.php         # Namespaced helper functions
├── uninstall.php                # Clean data removal
├── phpcs.xml                    # Coding standards config
├── CLAUDE.md                    # AI assistant instructions
├── includes/                    # Core classes
│   ├── class-plugin.php
│   ├── class-nonce-challenge.php
│   ├── class-email-verification.php
│   ├── class-account-cleanup.php
│   ├── class-logger.php
│   ├── class-geo-restriction.php
│   └── class-settings.php
├── integrations/                # Third-party plugin integrations
│   └── class-integration-woocommerce.php
├── assets/
│   └── js/
│       ├── nonce-challenge.js
│       └── admin.js
├── views/
│   └── emails/
│       └── verification-email.php
└── docs/                        # Public documentation
```

---

## Decision Log

Architectural decisions are documented in `dev-notes/00-project-tracker.md`. If your contribution involves a design trade-off, please discuss it in an issue first.
