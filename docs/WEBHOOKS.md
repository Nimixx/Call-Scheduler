# Webhook Integration

Call Scheduler supports webhooks for integrating with external services like n8n, Zapier, Make.com, or custom endpoints.

## Quick Start

1. **Enable webhooks** in WordPress Admin → Call Scheduler → Settings → Webhooks
2. **Enter your webhook URL** (must be HTTPS)
3. **Optional:** Add secret key to `wp-config.php` for signature verification

```php
// wp-config.php
define('CS_WEBHOOK_SECRET', 'your-secret-key-here');
```

## Configuration

### Admin Settings

| Setting | Description |
|---------|-------------|
| Enable webhooks | Toggle to enable/disable webhook notifications |
| Webhook URL | HTTPS endpoint to receive notifications |

### wp-config.php Constants

| Constant | Description | Required |
|----------|-------------|----------|
| `CS_WEBHOOK_SECRET` | HMAC-SHA256 signing key | Optional |

Generate a secure secret:
```bash
# Linux/Mac
openssl rand -hex 32
```

## Events

### `booking.created`

Fired when a new booking is successfully created.

**Payload:**
```json
{
  "event": "booking.created",
  "timestamp": "2026-01-07T14:30:00+00:00",
  "data": {
    "booking": {
      "id": 123,
      "user_id": 5,
      "customer_name": "Jan Novák",
      "customer_email": "jan@example.com",
      "booking_date": "2026-01-15",
      "booking_time": "10:00",
      "status": "pending"
    },
    "team_member": {
      "id": 5,
      "display_name": "Marie Svobodová",
      "email": "marie@company.com"
    }
  },
  "meta": {
    "plugin_version": "1.0.0",
    "site_url": "https://example.com"
  }
}
```

## HTTP Headers

Each webhook request includes these headers:

| Header | Description | Example |
|--------|-------------|---------|
| `Content-Type` | Always JSON | `application/json` |
| `X-CS-Event` | Event type | `booking.created` |
| `X-CS-Timestamp` | ISO 8601 timestamp | `2026-01-07T14:30:00+00:00` |
| `X-CS-Signature` | HMAC-SHA256 signature (if secret configured) | `a1b2c3d4...` |

## Signature Verification

When `CS_WEBHOOK_SECRET` is configured, each request includes an `X-CS-Signature` header containing an HMAC-SHA256 signature of the payload.

### Verification Examples

**PHP:**
```php
$payload = file_get_contents('php://input');
$received_signature = $_SERVER['HTTP_X_CS_SIGNATURE'] ?? '';
$secret = 'your-secret-key';

$expected_signature = hash_hmac('sha256', $payload, $secret);

if (!hash_equals($expected_signature, $received_signature)) {
    http_response_code(401);
    exit('Invalid signature');
}

$data = json_decode($payload, true);
// Process webhook...
```

**Node.js:**
```javascript
const crypto = require('crypto');

function verifySignature(payload, signature, secret) {
  const expected = crypto
    .createHmac('sha256', secret)
    .update(payload)
    .digest('hex');

  return crypto.timingSafeEqual(
    Buffer.from(signature),
    Buffer.from(expected)
  );
}

// Express middleware
app.post('/webhook', (req, res) => {
  const signature = req.headers['x-cs-signature'];
  const payload = JSON.stringify(req.body);

  if (!verifySignature(payload, signature, process.env.WEBHOOK_SECRET)) {
    return res.status(401).send('Invalid signature');
  }

  // Process webhook...
});
```

**Python:**
```python
import hmac
import hashlib

def verify_signature(payload: bytes, signature: str, secret: str) -> bool:
    expected = hmac.new(
        secret.encode(),
        payload,
        hashlib.sha256
    ).hexdigest()
    return hmac.compare_digest(expected, signature)

# Flask example
@app.route('/webhook', methods=['POST'])
def webhook():
    signature = request.headers.get('X-CS-Signature', '')

    if not verify_signature(request.data, signature, WEBHOOK_SECRET):
        return 'Invalid signature', 401

    data = request.json
    # Process webhook...
```

## n8n Integration

1. Create a new workflow in n8n
2. Add **Webhook** trigger node
3. Copy the webhook URL (e.g., `https://your-n8n.com/webhook/abc123`)
4. Paste URL in Call Scheduler webhook settings
5. Build your automation workflow

**Example workflow:**
```
[Webhook] → [IF booking before 12:00] → [Slack notification]
                                      → [Google Calendar event]
                                      → [CRM contact creation]
```

## Zapier Integration

1. Create a new Zap
2. Choose **Webhooks by Zapier** as trigger
3. Select **Catch Hook**
4. Copy the webhook URL
5. Paste URL in Call Scheduler webhook settings
6. Test the connection by creating a booking
7. Build your automation

## Security

### HTTPS Required

All webhook URLs must use HTTPS. HTTP URLs are rejected to prevent credentials from being transmitted in clear text.

### SSRF Protection

The following URLs are blocked to prevent Server-Side Request Forgery:

- `localhost`, `127.0.0.1`, `::1`
- Private IP ranges: `10.x.x.x`, `172.16-31.x.x`, `192.168.x.x`
- Internal hostnames: `*.local`, `*.internal`, `*.localhost`

### Secret Storage

The webhook secret is stored in `wp-config.php`, not in the database. This prevents exposure via:

- SQL injection attacks
- Database backup leaks
- Other plugins reading `wp_options`
- Compromised admin accounts viewing settings

## Delivery

### Non-Blocking

Webhooks are sent asynchronously with a near-zero timeout. This means:

- Booking creation is never delayed by webhook delivery
- Failed webhooks don't affect the user experience
- No retry mechanism (fire-and-forget)

### Error Logging

When `WP_DEBUG` is enabled, webhook errors are logged to the WordPress debug log:

```
Call Scheduler Webhook Error: [error message]
```

## Extending Webhooks

### Filter: `cs_webhook_args`

Modify webhook request arguments before sending:

```php
add_filter('cs_webhook_args', function($args, $payload) {
    // Add custom header
    $args['headers']['X-Custom-Header'] = 'value';

    // Increase timeout for testing
    $args['timeout'] = 5;
    $args['blocking'] = true;

    return $args;
}, 10, 2);
```

### Future Events

The webhook system is designed for extensibility. Future events may include:

- `booking.confirmed` - When booking status changes to confirmed
- `booking.cancelled` - When booking is cancelled
- `booking.rescheduled` - When booking date/time is changed

## Troubleshooting

### Webhook not firing

1. Check that webhooks are enabled in settings
2. Verify the URL is HTTPS
3. Check WordPress debug log for errors

### Invalid signature errors

1. Verify the secret matches on both ends
2. Ensure you're using the raw request body (not parsed JSON)
3. Check for any middleware modifying the payload

### Connection timeouts

Webhooks use a near-zero timeout for non-blocking delivery. The request is sent but the response is not waited for. This is by design to prevent booking delays.

### Testing webhooks

Use [webhook.site](https://webhook.site) or [RequestBin](https://requestbin.com) to inspect webhook payloads during development.
