<?php

declare(strict_types=1);

namespace CallScheduler\Rest;

use CallScheduler\Config;
use CallScheduler\Security\AuditLogger;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Base REST controller with shared functionality
 *
 * Provides:
 * - Rate limiting with configurable limits
 * - Team member validation
 * - JSON error helpers
 * - Token verification
 */
abstract class RestController
{
    protected const NAMESPACE = 'cs/v1';

    /** @var array<string, RateLimiter> Cache of rate limiters per endpoint */
    private array $rateLimiters = [];

    abstract public function register(): void;

    // =========================================================================
    // Rate Limiting
    // =========================================================================

    /**
     * Check rate limit for read endpoints
     */
    protected function checkReadRateLimit(string $endpoint): ?WP_Error
    {
        return $this->checkRateLimit($endpoint, Config::getRateLimitRead());
    }

    /**
     * Check rate limit for write endpoints
     */
    protected function checkWriteRateLimit(string $endpoint): ?WP_Error
    {
        return $this->checkRateLimit($endpoint, Config::getRateLimitWrite());
    }

    /**
     * Check rate limit with custom limit
     */
    protected function checkRateLimit(string $endpoint, int $limit): ?WP_Error
    {
        $limiter = $this->getRateLimiter($endpoint, $limit);
        $error = $limiter->check();

        if ($error !== null) {
            AuditLogger::rateLimitHit($endpoint, $limit);
        }

        return $error;
    }

    /**
     * Add rate limit headers to response
     */
    protected function addRateLimitHeaders(WP_REST_Response $response, string $endpoint, ?int $limit = null): WP_REST_Response
    {
        $limit = $limit ?? Config::getRateLimitRead();
        $limiter = $this->getRateLimiter($endpoint, $limit);
        return $limiter->addHeaders($response);
    }

    private function getRateLimiter(string $endpoint, int $limit): RateLimiter
    {
        $key = "{$endpoint}_{$limit}";

        if (!isset($this->rateLimiters[$key])) {
            $this->rateLimiters[$key] = new RateLimiter($endpoint, $limit, Config::getRateLimitWindow());
        }

        return $this->rateLimiters[$key];
    }

    // =========================================================================
    // Consultant Validation
    // =========================================================================

    /**
     * Validate consultant exists and is active
     *
     * @param string $publicId The consultant's public ID
     * @return \CallScheduler\Consultant|WP_Error
     */
    protected function validateConsultant(string $publicId): \CallScheduler\Consultant|WP_Error
    {
        $repository = new \CallScheduler\ConsultantRepository();
        $consultant = $repository->findByPublicId($publicId);

        if ($consultant === null) {
            return $this->errorResponse('invalid_consultant', 'Invalid consultant.', 400);
        }

        if (!$consultant->isActive) {
            return $this->errorResponse('consultant_inactive', 'Consultant is not available.', 400);
        }

        return $consultant;
    }

    // =========================================================================
    // Token Verification
    // =========================================================================

    /**
     * Verify booking token if token verification is enabled
     *
     * Token format: timestamp:hash where hash = HMAC-SHA256(timestamp, secret)
     * Token is valid for 5 minutes
     */
    protected function verifyToken(WP_REST_Request $request): ?WP_Error
    {
        $secret = Config::getBookingSecret();
        if ($secret === null) {
            return null; // Token verification disabled
        }

        $token = $request->get_header('X-CS-Token');

        if (empty($token)) {
            AuditLogger::invalidToken('missing');
            return $this->errorResponse('missing_token', 'Security token required.', 403);
        }

        $parts = explode(':', $token);
        if (count($parts) !== 2) {
            AuditLogger::invalidToken('malformed');
            return $this->errorResponse('invalid_token', 'Invalid security token format.', 403);
        }

        [$timestamp, $hash] = $parts;

        // Check timestamp is within 5 minutes
        $now = time();
        $token_time = (int) $timestamp;
        if (abs($now - $token_time) > 300) {
            AuditLogger::invalidToken('expired');
            return $this->errorResponse('expired_token', 'Security token expired.', 403);
        }

        // Verify hash
        $expected_hash = hash_hmac('sha256', $timestamp, $secret);
        if (!hash_equals($expected_hash, $hash)) {
            AuditLogger::invalidToken('invalid_hash');
            return $this->errorResponse('invalid_token', 'Invalid security token.', 403);
        }

        return null;
    }

    // =========================================================================
    // Error Helpers
    // =========================================================================

    /**
     * Create a standardized error response
     */
    protected function errorResponse(string $code, string $message, int $status = 400): WP_Error
    {
        return new WP_Error($code, $message, ['status' => $status]);
    }

    /**
     * Create a success response with rate limit headers
     */
    protected function successResponse(mixed $data, string $endpoint, int $status = 200, ?int $rateLimit = null): WP_REST_Response
    {
        $response = new WP_REST_Response($data, $status);
        return $this->addRateLimitHeaders($response, $endpoint, $rateLimit);
    }
}
