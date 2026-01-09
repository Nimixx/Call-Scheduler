<?php
/**
 * Call Scheduler - Security Test Suite
 *
 * Tests race conditions, token security, rate limiting, CORS, and input validation.
 *
 * Usage:
 *   php tests/security-test.php https://your-site.com
 *
 * Requirements:
 *   - PHP 8.0+ with curl extension
 *   - A valid consultant_id from your site
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

class SecurityTest
{
    private string $baseUrl;
    private string $apiUrl;
    private array $results = [];
    private int $passed = 0;
    private int $failed = 0;

    public function __construct(string $baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiUrl = $this->baseUrl . '/wp-json/cs/v1';
    }

    public function run(): void
    {
        echo "\n╔══════════════════════════════════════════════════════════════╗\n";
        echo "║       CALL SCHEDULER SECURITY TEST SUITE                     ║\n";
        echo "╚══════════════════════════════════════════════════════════════╝\n\n";

        echo "Target: {$this->baseUrl}\n";
        echo "API: {$this->apiUrl}\n\n";

        // Get a valid consultant ID first
        $consultantId = $this->getValidConsultantId();
        if (!$consultantId) {
            echo "❌ Could not find a valid consultant. Make sure you have team members set up.\n";
            return;
        }
        echo "Using consultant ID: {$consultantId}\n\n";

        // Run test suites
        $this->testRateLimiting();
        $this->testTokenSecurity($consultantId);
        $this->testRaceConditions($consultantId);
        $this->testCorsHeaders();
        $this->testInputValidation($consultantId);
        $this->testHoneypot($consultantId);
        $this->testSqlInjection($consultantId);
        $this->testXss($consultantId);

        // Summary
        $this->printSummary();
    }

    // =========================================================================
    // Test Suites
    // =========================================================================

    private function testRateLimiting(): void
    {
        $this->section("RATE LIMITING");

        // Test read endpoint rate limit
        echo "Testing read endpoint rate limit (default: 60/min)...\n";
        $responses = [];
        for ($i = 0; $i < 65; $i++) {
            $response = $this->request('GET', '/team-members');
            $responses[] = $response;
            if ($response['status'] === 429) {
                break;
            }
        }

        $rateLimited = array_filter($responses, fn($r) => $r['status'] === 429);
        if (!empty($rateLimited)) {
            $this->pass("Read rate limit enforced after " . count($responses) . " requests");
            $lastResponse = end($rateLimited);
            if (isset($lastResponse['headers']['X-RateLimit-Remaining'])) {
                echo "   Rate limit headers present: ✓\n";
            }
        } else {
            $this->warn("Read rate limit not triggered (may need higher request count or lower limit)");
        }

        // Check rate limit headers
        $response = $this->request('GET', '/team-members');
        $hasHeaders = isset($response['headers']['X-RateLimit-Limit'])
            && isset($response['headers']['X-RateLimit-Remaining'])
            && isset($response['headers']['X-RateLimit-Reset']);

        if ($hasHeaders) {
            $this->pass("Rate limit headers present (Limit, Remaining, Reset)");
        } else {
            $this->fail("Rate limit headers missing");
        }
    }

    private function testTokenSecurity(string $consultantId): void
    {
        $this->section("TOKEN SECURITY (X-CS-Token)");

        $bookingData = $this->getValidBookingData($consultantId);

        // Test without token (should work if CS_BOOKING_SECRET not defined)
        $response = $this->request('POST', '/bookings', $bookingData);
        if ($response['status'] === 201 || $response['status'] === 409) {
            $this->warn("Token verification disabled (CS_BOOKING_SECRET not set)");
            echo "   Bookings work without token - define CS_BOOKING_SECRET in wp-config.php to enable\n";
        } elseif ($response['status'] === 403) {
            $this->pass("Token required when CS_BOOKING_SECRET is set");

            // Test with invalid token
            $response = $this->request('POST', '/bookings', $bookingData, [
                'X-CS-Token: invalid_token'
            ]);
            if ($response['status'] === 403) {
                $this->pass("Invalid token rejected");
            } else {
                $this->fail("Invalid token was accepted");
            }

            // Test with malformed token
            $response = $this->request('POST', '/bookings', $bookingData, [
                'X-CS-Token: not:a:valid:format'
            ]);
            if ($response['status'] === 403) {
                $this->pass("Malformed token rejected");
            } else {
                $this->fail("Malformed token was accepted");
            }

            // Test with expired token (old timestamp)
            $expiredToken = (time() - 400) . ':' . hash('sha256', (string)(time() - 400));
            $response = $this->request('POST', '/bookings', $bookingData, [
                'X-CS-Token: ' . $expiredToken
            ]);
            if ($response['status'] === 403 && str_contains($response['body'], 'expired')) {
                $this->pass("Expired token rejected with correct message");
            } elseif ($response['status'] === 403) {
                $this->pass("Expired token rejected");
            } else {
                $this->fail("Expired token was accepted");
            }
        }
    }

    private function testRaceConditions(string $consultantId): void
    {
        $this->section("RACE CONDITIONS");

        // Get a unique time slot for this test
        $availableSlot = $this->getAvailableSlot($consultantId);
        if (!$availableSlot) {
            $this->warn("No available slots found - skipping race condition test");
            return;
        }

        echo "Testing concurrent booking requests for same slot...\n";
        echo "   Date: {$availableSlot['date']}, Time: {$availableSlot['time']}\n";

        // Prepare multiple requests for the same slot
        $bookingData = [
            'consultant_id' => $consultantId,
            'customer_name' => 'Race Test',
            'customer_email' => 'race' . time() . '@test.com',
            'booking_date' => $availableSlot['date'],
            'booking_time' => $availableSlot['time'],
        ];

        // Launch concurrent requests using curl_multi
        $mh = curl_multi_init();
        $handles = [];
        $numRequests = 5;

        for ($i = 0; $i < $numRequests; $i++) {
            $data = $bookingData;
            $data['customer_email'] = "race{$i}_" . time() . "@test.com";

            $ch = curl_init($this->apiUrl . '/bookings');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($data),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                ],
            ]);
            curl_multi_add_handle($mh, $ch);
            $handles[] = $ch;
        }

        // Execute all requests simultaneously
        $running = null;
        do {
            curl_multi_exec($mh, $running);
            curl_multi_select($mh);
        } while ($running > 0);

        // Collect results
        $results = [];
        foreach ($handles as $ch) {
            $response = curl_multi_getcontent($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $results[] = ['status' => $httpCode, 'body' => $response];
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        curl_multi_close($mh);

        // Analyze results
        $successful = array_filter($results, fn($r) => $r['status'] === 201);
        $conflicts = array_filter($results, fn($r) => $r['status'] === 409);
        $rateLimited = array_filter($results, fn($r) => $r['status'] === 429);

        echo "   Results: " . count($successful) . " success, " . count($conflicts) . " conflicts, " . count($rateLimited) . " rate limited\n";

        if (count($successful) <= 1) {
            $this->pass("Race condition prevented - only " . count($successful) . " booking(s) succeeded");
        } else {
            $this->fail("Race condition vulnerability - " . count($successful) . " duplicate bookings created!");
        }

        // Verify with database unique constraint
        if (count($conflicts) > 0) {
            $this->pass("Database unique constraint working (409 Conflict returned)");
        }
    }

    private function testCorsHeaders(): void
    {
        $this->section("CORS HEADERS");

        // Test preflight OPTIONS request
        $ch = curl_init($this->apiUrl . '/team-members');
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'OPTIONS',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_HTTPHEADER => [
                'Origin: https://malicious-site.com',
                'Access-Control-Request-Method: POST',
            ],
        ]);
        $response = curl_exec($ch);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = substr($response, 0, $headerSize);
        curl_close($ch);

        // Check if malicious origin is rejected
        if (!str_contains($headers, 'Access-Control-Allow-Origin: https://malicious-site.com')) {
            $this->pass("Malicious origin rejected in CORS");
        } else {
            $this->fail("CORS allows malicious origin!");
        }

        // Test with legitimate origin (same site)
        $ch = curl_init($this->apiUrl . '/team-members');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_HTTPHEADER => [
                'Origin: ' . $this->baseUrl,
            ],
        ]);
        $response = curl_exec($ch);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = substr($response, 0, $headerSize);
        curl_close($ch);

        if (str_contains($headers, 'Access-Control-Allow-Origin')) {
            $this->pass("Same-origin allowed in CORS");

            // Check for credentials header
            if (str_contains($headers, 'Access-Control-Allow-Credentials: true')) {
                echo "   Allow-Credentials: true (sessions work cross-origin)\n";
            }
        }

        // Check allowed methods
        if (str_contains($headers, 'Access-Control-Allow-Methods')) {
            preg_match('/Access-Control-Allow-Methods: ([^\r\n]+)/', $headers, $matches);
            echo "   Allowed methods: " . ($matches[1] ?? 'unknown') . "\n";
        }

        // Check allowed headers
        if (str_contains($headers, 'Access-Control-Allow-Headers')) {
            preg_match('/Access-Control-Allow-Headers: ([^\r\n]+)/', $headers, $matches);
            echo "   Allowed headers: " . ($matches[1] ?? 'unknown') . "\n";
        }
    }

    private function testInputValidation(string $consultantId): void
    {
        $this->section("INPUT VALIDATION");

        // Test invalid date format
        $response = $this->request('POST', '/bookings', [
            'consultant_id' => $consultantId,
            'customer_name' => 'Test User',
            'customer_email' => 'test@example.com',
            'booking_date' => 'invalid-date',
            'booking_time' => '10:00',
        ]);
        if ($response['status'] === 400 && str_contains($response['body'], 'invalid_date')) {
            $this->pass("Invalid date format rejected");
        } else {
            $this->fail("Invalid date format accepted");
        }

        // Test past date
        $response = $this->request('POST', '/bookings', [
            'consultant_id' => $consultantId,
            'customer_name' => 'Test User',
            'customer_email' => 'test@example.com',
            'booking_date' => '2020-01-01',
            'booking_time' => '10:00',
        ]);
        if ($response['status'] === 400 && str_contains($response['body'], 'past_date')) {
            $this->pass("Past date rejected");
        } else {
            $this->fail("Past date accepted");
        }

        // Test invalid time format
        $response = $this->request('POST', '/bookings', [
            'consultant_id' => $consultantId,
            'customer_name' => 'Test User',
            'customer_email' => 'test@example.com',
            'booking_date' => date('Y-m-d', strtotime('+1 day')),
            'booking_time' => '25:99',
        ]);
        if ($response['status'] === 400 && str_contains($response['body'], 'invalid_time')) {
            $this->pass("Invalid time rejected");
        } else {
            $this->fail("Invalid time accepted");
        }

        // Test invalid email
        $response = $this->request('POST', '/bookings', [
            'consultant_id' => $consultantId,
            'customer_name' => 'Test User',
            'customer_email' => 'not-an-email',
            'booking_date' => date('Y-m-d', strtotime('+1 day')),
            'booking_time' => '10:00',
        ]);
        if ($response['status'] === 400 && str_contains($response['body'], 'invalid_email')) {
            $this->pass("Invalid email rejected");
        } else {
            $this->fail("Invalid email accepted");
        }

        // Test invalid consultant ID
        $response = $this->request('POST', '/bookings', [
            'consultant_id' => 'nonexistent123',
            'customer_name' => 'Test User',
            'customer_email' => 'test@example.com',
            'booking_date' => date('Y-m-d', strtotime('+1 day')),
            'booking_time' => '10:00',
        ]);
        if ($response['status'] === 400 && str_contains($response['body'], 'invalid_consultant')) {
            $this->pass("Invalid consultant ID rejected");
        } else {
            $this->fail("Invalid consultant ID accepted");
        }

        // Test date too far in future
        $response = $this->request('POST', '/bookings', [
            'consultant_id' => $consultantId,
            'customer_name' => 'Test User',
            'customer_email' => 'test@example.com',
            'booking_date' => date('Y-m-d', strtotime('+365 days')),
            'booking_time' => '10:00',
        ]);
        if ($response['status'] === 400 && str_contains($response['body'], 'date_too_far')) {
            $this->pass("Future date limit enforced");
        } else {
            $this->warn("Future date limit may not be enforced or configured differently");
        }
    }

    private function testHoneypot(string $consultantId): void
    {
        $this->section("HONEYPOT BOT DETECTION");

        $availableSlot = $this->getAvailableSlot($consultantId);
        if (!$availableSlot) {
            $this->warn("No available slots - skipping honeypot test");
            return;
        }

        // Test with honeypot field filled (bot behavior)
        $response = $this->request('POST', '/bookings', [
            'consultant_id' => $consultantId,
            'customer_name' => 'Bot Test',
            'customer_email' => 'bot@test.com',
            'booking_date' => $availableSlot['date'],
            'booking_time' => $availableSlot['time'],
            'website' => 'https://spam-site.com', // Honeypot field
        ]);

        // Honeypot should return fake success (201) but not actually create booking
        if ($response['status'] === 201) {
            // Verify booking wasn't actually created by trying to book same slot
            $response2 = $this->request('POST', '/bookings', [
                'consultant_id' => $consultantId,
                'customer_name' => 'Real User',
                'customer_email' => 'real@test.com',
                'booking_date' => $availableSlot['date'],
                'booking_time' => $availableSlot['time'],
            ]);

            if ($response2['status'] === 201) {
                $this->pass("Honeypot working - fake success returned, booking not created");
            } else {
                $this->fail("Honeypot returned 201 but may have created booking");
            }
        } else {
            $this->fail("Honeypot field not handled correctly (status: {$response['status']})");
        }
    }

    private function testSqlInjection(string $consultantId): void
    {
        $this->section("SQL INJECTION");

        $payloads = [
            "'; DROP TABLE wp_cs_bookings; --",
            "1' OR '1'='1",
            "1; DELETE FROM wp_cs_bookings WHERE 1=1; --",
            "' UNION SELECT * FROM wp_users --",
        ];

        foreach ($payloads as $payload) {
            // Test in customer_name
            $response = $this->request('POST', '/bookings', [
                'consultant_id' => $consultantId,
                'customer_name' => $payload,
                'customer_email' => 'test@example.com',
                'booking_date' => date('Y-m-d', strtotime('+1 day')),
                'booking_time' => '10:00',
            ]);

            // Should either reject or sanitize, not execute SQL
            if ($response['status'] >= 500) {
                $this->fail("SQL injection may have caused server error: " . substr($payload, 0, 30) . "...");
                return;
            }
        }

        $this->pass("SQL injection payloads handled safely");
    }

    private function testXss(string $consultantId): void
    {
        $this->section("XSS PREVENTION");

        $payloads = [
            '<script>alert("XSS")</script>',
            '"><img src=x onerror=alert("XSS")>',
            "javascript:alert('XSS')",
            '<svg onload=alert("XSS")>',
        ];

        $availableSlot = $this->getAvailableSlot($consultantId);
        if (!$availableSlot) {
            $this->warn("No available slots - skipping XSS test");
            return;
        }

        foreach ($payloads as $payload) {
            $response = $this->request('POST', '/bookings', [
                'consultant_id' => $consultantId,
                'customer_name' => $payload,
                'customer_email' => 'xss@test.com',
                'booking_date' => $availableSlot['date'],
                'booking_time' => $availableSlot['time'],
            ]);

            // Check if payload appears unescaped in response
            if (str_contains($response['body'], '<script>') || str_contains($response['body'], 'onerror=')) {
                $this->fail("XSS payload not sanitized in response");
                return;
            }
        }

        $this->pass("XSS payloads sanitized in responses");
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function getValidConsultantId(): ?string
    {
        $response = $this->request('GET', '/team-members');
        if ($response['status'] !== 200) {
            return null;
        }

        $data = json_decode($response['body'], true);
        return $data[0]['id'] ?? null;
    }

    private function getAvailableSlot(string $consultantId): ?array
    {
        // Try next 7 days
        for ($i = 1; $i <= 7; $i++) {
            $date = date('Y-m-d', strtotime("+{$i} days"));
            $response = $this->request('GET', "/availability?consultant_id={$consultantId}&date={$date}");

            if ($response['status'] === 200) {
                $data = json_decode($response['body'], true);
                if (!empty($data['slots'])) {
                    return [
                        'date' => $date,
                        'time' => $data['slots'][0],
                    ];
                }
            }
        }

        return null;
    }

    private function getValidBookingData(string $consultantId): array
    {
        return [
            'consultant_id' => $consultantId,
            'customer_name' => 'Security Test',
            'customer_email' => 'security' . time() . '@test.com',
            'booking_date' => date('Y-m-d', strtotime('+1 day')),
            'booking_time' => '10:00',
        ];
    }

    private function request(string $method, string $endpoint, array $data = [], array $headers = []): array
    {
        $url = $this->apiUrl . $endpoint;

        $ch = curl_init($url);
        $defaultHeaders = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_HTTPHEADER => array_merge($defaultHeaders, $headers),
        ];

        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = json_encode($data);
        }

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

        $headerString = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);

        // Parse headers
        $headers = [];
        foreach (explode("\r\n", $headerString) as $line) {
            if (str_contains($line, ':')) {
                [$key, $value] = explode(':', $line, 2);
                $headers[trim($key)] = trim($value);
            }
        }

        curl_close($ch);

        return [
            'status' => $httpCode,
            'headers' => $headers,
            'body' => $body,
        ];
    }

    private function section(string $title): void
    {
        echo "\n┌─────────────────────────────────────────────────────────────┐\n";
        echo "│ {$title}" . str_repeat(' ', 60 - strlen($title)) . "│\n";
        echo "└─────────────────────────────────────────────────────────────┘\n";
    }

    private function pass(string $message): void
    {
        echo "  ✓ PASS: {$message}\n";
        $this->passed++;
        $this->results[] = ['status' => 'pass', 'message' => $message];
    }

    private function fail(string $message): void
    {
        echo "  ✗ FAIL: {$message}\n";
        $this->failed++;
        $this->results[] = ['status' => 'fail', 'message' => $message];
    }

    private function warn(string $message): void
    {
        echo "  ⚠ WARN: {$message}\n";
        $this->results[] = ['status' => 'warn', 'message' => $message];
    }

    private function printSummary(): void
    {
        echo "\n╔══════════════════════════════════════════════════════════════╗\n";
        echo "║                      TEST SUMMARY                            ║\n";
        echo "╚══════════════════════════════════════════════════════════════╝\n\n";

        $total = $this->passed + $this->failed;
        $percentage = $total > 0 ? round(($this->passed / $total) * 100) : 0;

        echo "  Passed: {$this->passed}\n";
        echo "  Failed: {$this->failed}\n";
        echo "  Total:  {$total}\n";
        echo "  Score:  {$percentage}%\n\n";

        if ($this->failed > 0) {
            echo "  ❌ SECURITY ISSUES DETECTED\n\n";
            echo "  Failed tests:\n";
            foreach ($this->results as $result) {
                if ($result['status'] === 'fail') {
                    echo "    - {$result['message']}\n";
                }
            }
        } else {
            echo "  ✅ ALL SECURITY TESTS PASSED\n";
        }

        echo "\n";
    }
}

// Run tests
if ($argc < 2) {
    echo "Usage: php security-test.php <site-url>\n";
    echo "Example: php security-test.php https://example.com\n";
    exit(1);
}

$test = new SecurityTest($argv[1]);
$test->run();
