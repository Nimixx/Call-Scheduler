<?php

declare(strict_types=1);

namespace CallScheduler\Rest;

use CallScheduler\Config;
use WP_Error;
use WP_REST_Response;

if (!defined('ABSPATH')) {
    exit;
}

final class RateLimiter
{
    private string $ip;
    private string $endpoint;
    private int $limit;
    private int $window;

    public function __construct(string $endpoint, int $limit = 60, int $window = 60)
    {
        $this->ip = self::getClientIp();
        $this->endpoint = $endpoint;
        $this->limit = $limit;
        $this->window = $window;
    }

    /**
     * Check rate limit with atomic lock to prevent race conditions
     *
     * Uses wp_cache_add() for atomic lock acquisition to ensure accurate counting
     * during concurrent requests. Falls back to allowing request if lock can't be
     * acquired after retries (graceful degradation).
     */
    public function check(): ?WP_Error
    {
        $key = $this->getTransientKey();
        $lock_key = $key . '_lock';
        $max_retries = 3;
        $retry_count = 0;

        // Retry loop to handle race conditions
        while ($retry_count < $max_retries) {
            // Try to acquire lock (atomic operation)
            $lock_acquired = wp_cache_add($lock_key, '1', '', 1);

            if (!$lock_acquired) {
                // Lock held by another request, wait briefly and retry
                usleep(10000); // 10ms
                $retry_count++;
                continue;
            }

            // Lock acquired, perform rate limit check
            $data = get_transient($key);

            if ($data === false) {
                $data = ['count' => 0, 'reset' => time() + $this->window];
            }

            // Reset if window expired
            if (time() > $data['reset']) {
                $data = ['count' => 0, 'reset' => time() + $this->window];
            }

            $data['count']++;
            set_transient($key, $data, $this->window);

            // Release lock
            wp_cache_delete($lock_key);

            if ($data['count'] > $this->limit) {
                $retry_after = $data['reset'] - time();
                return new WP_Error(
                    'rate_limit_exceeded',
                    'Too many requests. Please try again later.',
                    [
                        'status' => 429,
                        'retry_after' => max(1, $retry_after),
                    ]
                );
            }

            return null;
        }

        // Failed to acquire lock after retries - allow request but log (only in debug mode, with hashed IP)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $hashed_ip = substr(hash('sha256', $this->ip), 0, 8);
            error_log('WB Rate Limiter: Failed to acquire lock after ' . $max_retries . ' retries for IP:' . $hashed_ip);
        }
        return null;
    }

    public function addHeaders(WP_REST_Response $response): WP_REST_Response
    {
        $key = $this->getTransientKey();
        $data = get_transient($key);

        if ($data === false) {
            $remaining = $this->limit;
            $reset = time() + $this->window;
        } else {
            $remaining = max(0, $this->limit - $data['count']);
            $reset = $data['reset'];
        }

        $response->header('X-RateLimit-Limit', (string) $this->limit);
        $response->header('X-RateLimit-Remaining', (string) $remaining);
        $response->header('X-RateLimit-Reset', (string) $reset);

        return $response;
    }

    private function getTransientKey(): string
    {
        $identifier = md5($this->ip . '|' . $this->endpoint);
        return 'cs_rate_' . substr($identifier, 0, 32);
    }

    private static function getClientIp(): string
    {
        // Option 1: Web server already restored real IP (recommended)
        // Configure nginx/Apache to set REMOTE_ADDR from X-Forwarded-For
        // Then this just works with no maintenance.

        // Option 2: Define CS_TRUST_PROXY in wp-config.php if behind proxy
        // define('CS_TRUST_PROXY', true);

        if (Config::shouldTrustProxy()) {
            // Check proxy headers in order of preference
            $headers = [
                'HTTP_CF_CONNECTING_IP',  // Cloudflare
                'HTTP_X_FORWARDED_FOR',   // Standard proxy header
                'HTTP_X_REAL_IP',         // Nginx
            ];

            foreach ($headers as $header) {
                if (!empty($_SERVER[$header])) {
                    $ip = $_SERVER[$header];
                    // X-Forwarded-For can have multiple IPs - first is client
                    if (strpos($ip, ',') !== false) {
                        $ip = trim(explode(',', $ip)[0]);
                    }
                    if (filter_var($ip, FILTER_VALIDATE_IP)) {
                        return $ip;
                    }
                }
            }
        }

        // Use REMOTE_ADDR (default, secure)
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }
}
