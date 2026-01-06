<?php

declare(strict_types=1);

namespace CallScheduler\Admin\Settings\Modules;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Interface for settings modules
 *
 * Each module is responsible for one settings card/section
 */
interface SettingsModuleInterface
{
    /**
     * Get the unique module ID (used for option keys)
     */
    public function getId(): string;

    /**
     * Get the module title
     */
    public function getTitle(): string;

    /**
     * Get the module icon (dashicons class without 'dashicons-' prefix)
     */
    public function getIcon(): string;

    /**
     * Register module settings with WordPress Settings API
     */
    public function registerSettings(): void;

    /**
     * Render the module card HTML
     *
     * @param array<string, mixed> $options Current options
     */
    public function render(array $options): void;

    /**
     * Sanitize module options
     *
     * @param array<string, mixed> $input Raw input
     * @return array<string, mixed> Sanitized options
     */
    public function sanitize(array $input): array;

    /**
     * Get default values for this module
     *
     * @return array<string, mixed>
     */
    public function getDefaults(): array;
}
