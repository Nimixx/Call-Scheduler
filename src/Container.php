<?php

declare(strict_types=1);

namespace CallScheduler;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Lightweight service container
 *
 * Simple dependency injection container for shared services.
 * Supports lazy instantiation and singleton pattern.
 */
final class Container
{
    /** @var array<string, callable> Service factories */
    private array $factories = [];

    /** @var array<string, object> Cached singleton instances */
    private array $instances = [];

    /**
     * Register a service factory
     *
     * @param string $id Service identifier
     * @param callable $factory Factory function returning service instance
     */
    public function set(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
        unset($this->instances[$id]); // Clear cached instance if re-registering
    }

    /**
     * Get a service instance (lazy-loaded singleton)
     *
     * @param string $id Service identifier
     * @return object Service instance
     * @throws \RuntimeException If service not found
     */
    public function get(string $id): object
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (!isset($this->factories[$id])) {
            throw new \RuntimeException("Service not found: {$id}");
        }

        $this->instances[$id] = ($this->factories[$id])($this);

        return $this->instances[$id];
    }

    /**
     * Check if service is registered
     */
    public function has(string $id): bool
    {
        return isset($this->factories[$id]);
    }

    /**
     * Get typed service (convenience methods)
     */
    public function cache(): Cache
    {
        return $this->get('cache');
    }

    public function email(): Email\EmailService
    {
        return $this->get('email');
    }

    public function webhook(): Webhook
    {
        return $this->get('webhook');
    }
}
