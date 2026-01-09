# Call Scheduler Security Analysis

## Executive Summary

The booking system implements multiple security layers. This document analyzes each security mechanism and identifies potential vulnerabilities.

---

## 1. Race Condition Protection

### Current Implementation

**Database Level (Strong)**
```sql
-- Generated column: NULL for cancelled, 1 for active
ADD COLUMN is_active TINYINT UNSIGNED
    GENERATED ALWAYS AS (IF(status = 'cancelled', NULL, 1)) STORED

-- Unique constraint ignores NULL values, allowing re-booking cancelled slots
ADD UNIQUE KEY unique_active_booking (user_id, booking_date, booking_time, is_active)
```

**Application Level**
- Uses `$wpdb->insert()` which triggers MySQL unique constraint
- Returns 409 Conflict on duplicate entry detection

### Assessment: SECURE

The database unique constraint is the **gold standard** for race condition prevention. Even if 100 concurrent requests hit the same slot, MySQL guarantees only one INSERT succeeds.

**Strengths:**
- Atomic at database level (cannot be bypassed)
- Cancelled bookings can be re-booked (NULL in unique index)
- Proper error handling returns 409 Conflict

**Potential Improvement:**
- Consider adding `SELECT ... FOR UPDATE` before INSERT for complex booking flows

---

## 2. Rate Limiting

### Current Implementation

```php
// RateLimiter.php - Uses atomic locking
public function check(): ?WP_Error
{
    // Atomic lock acquisition
    $lock_acquired = wp_cache_add($lock_key, '1', '', 1);

    // ... increment counter atomically

    // Release lock
    wp_cache_delete($lock_key);
}
```

**Configuration (Config.php):**
- Read endpoints: 60 requests/minute (CS_RATE_LIMIT_READ)
- Write endpoints: 5 requests/minute (CS_RATE_LIMIT_WRITE)
- Window: 60 seconds

### Assessment: GOOD (with caveats)

**Strengths:**
- Atomic lock prevents counter race conditions
- Separate limits for read/write operations
- Proper headers (X-RateLimit-Limit, Remaining, Reset)
- IP-based limiting with proxy support

**Potential Issues:**

1. **Transient Storage**: Uses WordPress transients which may be stored in database (slow) if no object cache is configured
   - **Recommendation**: Ensure Redis/Memcached is configured for production

2. **IP Spoofing**: When `CS_TRUST_PROXY=true`, attackers could spoof X-Forwarded-For
   - **Recommendation**: Only enable if behind trusted proxy (Cloudflare, nginx)

3. **Graceful Degradation**: If lock fails after retries, request is allowed
   - This is intentional (availability over security) but should be monitored

---

## 3. Token Security (X-CS-Token)

### Current Implementation

```php
// Token format: timestamp:HMAC-SHA256(timestamp, secret)
// Valid for 5 minutes

$expected_hash = hash_hmac('sha256', $timestamp, $secret);
if (!hash_equals($expected_hash, $hash)) {
    return error;
}
```

### Assessment: SECURE (when enabled)

**Strengths:**
- HMAC-SHA256 is cryptographically secure
- Timing-safe comparison (`hash_equals`)
- 5-minute expiry prevents replay attacks
- Optional (graceful for simple deployments)

**Configuration Required:**
```php
// wp-config.php
define('CS_BOOKING_SECRET', 'your-32-character-random-string');
```

**Potential Improvements:**
1. Add nonce to prevent replay within 5-minute window
2. Consider per-session tokens instead of time-based

---

## 4. CORS Headers

### Current Implementation

```php
private function sendCorsHeaders(): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    if (Config::isOriginAllowed($origin)) {
        header("Access-Control-Allow-Origin: {$origin}");
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-WP-Nonce, X-CS-Token');
        header('Access-Control-Allow-Credentials: true');
    }
}
```

### Assessment: SECURE

**Strengths:**
- Validates origin against whitelist
- Only applies to cs/v1 routes
- Supports credentials for authenticated requests
- X-CS-Token allowed in headers

**Configuration:**
```php
// wp-config.php - for multiple origins
define('CS_ALLOWED_ORIGINS', 'https://site1.com,https://site2.com');
```

---

## 5. Input Validation

### Current Implementation

| Field | Validation |
|-------|------------|
| consultant_id | `sanitize_text_field` + DB lookup |
| customer_name | `sanitize_text_field` |
| customer_email | `sanitize_email` + `is_email()` |
| booking_date | Regex `/^\d{4}-\d{2}-\d{2}$/` + `checkdate()` + range check |
| booking_time | Regex `/^\d{2}:\d{2}$/` + bounds check |

### Assessment: SECURE

**Strengths:**
- WordPress sanitization functions used
- Date/time format strictly validated
- Past dates rejected
- Future date limit enforced (CS_MAX_BOOKING_DAYS)
- Availability cross-checked with database

**SQL Injection Protection:**
- Uses `$wpdb->prepare()` for all queries
- Uses `$wpdb->insert()` with format specifiers

---

## 6. Honeypot Bot Detection

### Current Implementation

```php
// Hidden field in form - bots fill it, humans don't see it
if (!empty($request->get_param('website'))) {
    // Return fake success, don't create booking
    return new WP_REST_Response(['id' => 0, 'status' => 'pending'], 201);
}
```

### Assessment: GOOD

**Strengths:**
- Silent failure (bot thinks it succeeded)
- No database pollution
- Zero false positives for legitimate users

**Potential Improvements:**
1. Add timing check (submissions < 2 seconds = bot)
2. Add multiple honeypot fields
3. Consider JavaScript challenge for high-risk endpoints

---

## 7. Security Headers

### Missing Headers (Recommended Additions)

Add to `.htaccess` or nginx config:

```
# Prevent clickjacking
Header always set X-Frame-Options "SAMEORIGIN"

# Prevent MIME sniffing
Header always set X-Content-Type-Options "nosniff"

# XSS Protection
Header always set X-XSS-Protection "1; mode=block"

# Referrer Policy
Header always set Referrer-Policy "strict-origin-when-cross-origin"
```

---

## 8. Identified Vulnerabilities

### Low Risk

1. **No CSRF token for REST API**
   - Mitigated by: CORS restrictions, optional X-CS-Token
   - WordPress REST API relies on nonce for authenticated requests

2. **Email enumeration possible**
   - Booking confirmation reveals if email exists in system
   - Low risk for public booking system

### Recommendations

1. **Enable token verification in production:**
   ```php
   define('CS_BOOKING_SECRET', bin2hex(random_bytes(16)));
   ```

2. **Configure object cache** for rate limiting performance

3. **Add security headers** at server level

4. **Monitor failed bookings** for attack patterns

---

## 9. Testing Commands

```bash
# Run security test suite
php tests/security-test.php https://your-site.com

# Test rate limiting manually
for i in {1..70}; do curl -s -o /dev/null -w "%{http_code}\n" https://site.com/wp-json/cs/v1/team-members; done

# Test race condition with Apache Bench
ab -n 10 -c 10 -p booking.json -T application/json https://site.com/wp-json/cs/v1/bookings
```

---

## 10. Security Checklist

- [x] SQL injection prevention (prepared statements)
- [x] XSS prevention (sanitization)
- [x] CSRF protection (CORS + optional token)
- [x] Race condition prevention (DB unique constraint)
- [x] Rate limiting (per IP, atomic)
- [x] Input validation (strict formats)
- [x] Bot detection (honeypot)
- [x] Secure token verification (HMAC-SHA256)
- [ ] Security headers (add at server level)
- [ ] Audit logging (consider adding)

---

*Generated: 2026-01-09*
