# Providing Geo-IP Data to Registration Guard

Registration Guard uses IP geolocation for its geo-restriction feature but does not bundle a GeoIP database. Instead, it delegates IP lookups to external providers via the `registration_guard_geolocate_ip` filter.

Any plugin, mu-plugin, or theme can register as a geo-IP provider. When a provider is registered, Registration Guard automatically:

- Enables the geo-restriction settings UI
- Resolves country codes during registration validation
- Displays country flags in the event log

---

## Quick Start

Add this to your mu-plugin, plugin, or theme's `functions.php`:

```php
add_filter( 'registration_guard_geolocate_ip', function ( string $country_code, string $ip ): string {
    if ( '' !== $country_code ) {
        return $country_code; // Another provider already resolved it.
    }

    // Your lookup logic here. Return an uppercase ISO 3166-1
    // alpha-2 country code (e.g. "GB", "US", "DE"), or an
    // empty string if the IP cannot be resolved.
    return my_geoip_lookup( $ip );
}, 10, 2 );
```

That's it. Registration Guard will detect your hook automatically and enable geo-restriction.

---

## Filter Reference

### `registration_guard_geolocate_ip`

Resolve an IP address to a two-letter country code.

| Parameter | Type | Description |
|-----------|------|-------------|
| `$country_code` | `string` | Current country code. Empty if no provider has resolved it yet. |
| `$ip` | `string` | The IP address to look up (IPv4 or IPv6). |

**Return:** An uppercase ISO 3166-1 alpha-2 country code (e.g. `"GB"`), or an empty string if the IP cannot be resolved.

**Important:** Always check whether `$country_code` is already populated before doing your lookup. Multiple providers can be registered, and the first one to resolve wins:

```php
if ( '' !== $country_code ) {
    return $country_code;
}
```

---

## Examples

### MaxMind GeoLite2 (via PHP extension)

```php
add_filter( 'registration_guard_geolocate_ip', function ( string $country_code, string $ip ): string {
    if ( '' !== $country_code ) {
        return $country_code;
    }

    if ( ! function_exists( 'geoip_country_code_by_name' ) ) {
        return '';
    }

    $code = @geoip_country_code_by_name( $ip );

    return ( false !== $code ) ? strtoupper( $code ) : '';
}, 10, 2 );
```

### MaxMind GeoIP2 PHP Library

```php
add_filter( 'registration_guard_geolocate_ip', function ( string $country_code, string $ip ): string {
    if ( '' !== $country_code ) {
        return $country_code;
    }

    $db_path = '/usr/share/GeoIP/GeoLite2-Country.mmdb';

    if ( ! file_exists( $db_path ) || ! class_exists( '\GeoIp2\Database\Reader' ) ) {
        return '';
    }

    try {
        $reader  = new \GeoIp2\Database\Reader( $db_path );
        $record  = $reader->country( $ip );
        return strtoupper( $record->country->isoCode );
    } catch ( \Exception $e ) {
        return '';
    }
}, 10, 2 );
```

### Custom Database or API

```php
add_filter( 'registration_guard_geolocate_ip', function ( string $country_code, string $ip ): string {
    if ( '' !== $country_code ) {
        return $country_code;
    }

    // Example: query a custom database table.
    global $wpdb;

    $code = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT country_code FROM {$wpdb->prefix}my_geoip WHERE ip_address = %s",
            $ip
        )
    );

    return $code ? strtoupper( $code ) : '';
}, 10, 2 );
```

### Hosting Provider mu-plugin

If your hosting platform provides geo-IP data via server headers (e.g. Cloudflare, AWS CloudFront, Nginx GeoIP module):

```php
/**
 * Provide geo-IP data to Registration Guard from server headers.
 *
 * Works with:
 * - Cloudflare:    CF-IPCountry
 * - AWS CloudFront: CloudFront-Viewer-Country
 * - Nginx GeoIP:   X-Country-Code (or custom header name)
 */
add_filter( 'registration_guard_geolocate_ip', function ( string $country_code, string $ip ): string {
    if ( '' !== $country_code ) {
        return $country_code;
    }

    // Cloudflare.
    if ( ! empty( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ) {
        return strtoupper( sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ) );
    }

    // AWS CloudFront.
    if ( ! empty( $_SERVER['HTTP_CLOUDFRONT_VIEWER_COUNTRY'] ) ) {
        return strtoupper( sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLOUDFRONT_VIEWER_COUNTRY'] ) ) );
    }

    // Nginx GeoIP module.
    if ( ! empty( $_SERVER['HTTP_X_COUNTRY_CODE'] ) ) {
        return strtoupper( sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_COUNTRY_CODE'] ) ) );
    }

    return '';
}, 10, 2 );
```

---

## Public API Functions

Registration Guard provides global-scope functions (no namespace required) for use by external code:

```php
// Get the plugin instance.
$plugin = registration_guard_get_plugin();

// Check if any geo-IP provider is registered.
if ( registration_guard_has_geo_provider() ) {
    // Geo-restriction is available.
}

// Look up a country code for an IP address (requires a provider).
$geo  = registration_guard_get_plugin()->get_geo_restriction();
$code = $geo->get_country_code( '8.8.8.8' );  // Returns "US" or "".
```

---

## How It Works Internally

1. **Provider detection:** Registration Guard checks `has_filter( 'registration_guard_geolocate_ip' )` to determine if a provider is available. This controls whether the geo-restriction settings UI is shown.

2. **Lookup:** When Registration Guard needs a country code (during registration validation or in the event log), it calls `apply_filters( 'registration_guard_geolocate_ip', '', $ip )`.

3. **Multiple providers:** If multiple providers are hooked, WordPress runs them in priority order. Each provider should check if `$country_code` is already populated and return early if so.

4. **WooCommerce:** When WooCommerce is active, Registration Guard's built-in WooCommerce integration automatically registers as a provider using `WC_Geolocation::geolocate_ip()`. If you register your own provider alongside WooCommerce, whichever has the lower priority number runs first.

---

## Testing Your Provider

1. Activate Registration Guard
2. Add your filter hook (mu-plugin, plugin, or theme)
3. Go to **Settings > Registration Guard**
4. The Geo-Restriction section should show full settings (not the "no provider available" notice)
5. Enable geo-restriction with a blocklist containing your own country code
6. Try to register a new account -- it should be blocked
7. Check the Event Log tab -- IP addresses should show country flags
