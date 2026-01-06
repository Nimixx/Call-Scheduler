<?php

declare(strict_types=1);

namespace CallScheduler\Admin\Settings\Modules;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Base class for settings modules with common rendering
 */
abstract class AbstractSettingsModule implements SettingsModuleInterface
{
    public const OPTION_NAME = 'cs_options';

    public function registerSettings(): void
    {
        // Individual modules don't register - SettingsPage handles registration
    }

    /**
     * Generate a unique field ID
     */
    protected function getFieldId(string $name): string
    {
        return 'cs_' . $name;
    }

    /**
     * Get field name for form submission
     */
    protected function getFieldName(string $name): string
    {
        return self::OPTION_NAME . "[$name]";
    }

    /**
     * Render a toggle switch
     */
    protected function renderToggle(string $name, bool $checked, string $label = ''): void
    {
        $field_id = $this->getFieldId($name);
        $field_name = $this->getFieldName($name);
        ?>
        <label class="cs-toggle" for="<?php echo esc_attr($field_id); ?>">
            <input type="checkbox"
                   id="<?php echo esc_attr($field_id); ?>"
                   name="<?php echo esc_attr($field_name); ?>"
                   value="1"
                   <?php checked($checked); ?>
                   class="cs-module-toggle"
                   data-toggle="<?php echo esc_attr($name); ?>" />
            <span class="cs-toggle-slider"></span>
        </label>
        <?php if ($label): ?>
            <span class="cs-toggle-label"><?php echo esc_html($label); ?></span>
        <?php endif;
    }

    /**
     * Render a text input
     */
    protected function renderTextInput(string $name, string $value, string $placeholder = ''): void
    {
        $field_id = $this->getFieldId($name);
        $field_name = $this->getFieldName($name);
        ?>
        <input type="text"
               id="<?php echo esc_attr($field_id); ?>"
               name="<?php echo esc_attr($field_name); ?>"
               class="cs-input cs-text-input"
               value="<?php echo esc_attr($value); ?>"
               placeholder="<?php echo esc_attr($placeholder); ?>" />
        <?php
    }

    /**
     * Render a number input
     */
    protected function renderNumberInput(string $name, int $value, int $min = 0, int $max = 100, int $step = 1): void
    {
        $field_id = $this->getFieldId($name);
        $field_name = $this->getFieldName($name);
        ?>
        <input type="number"
               id="<?php echo esc_attr($field_id); ?>"
               name="<?php echo esc_attr($field_name); ?>"
               class="cs-input cs-number-input"
               value="<?php echo esc_attr((string) $value); ?>"
               min="<?php echo esc_attr((string) $min); ?>"
               max="<?php echo esc_attr((string) $max); ?>"
               step="<?php echo esc_attr((string) $step); ?>" />
        <?php
    }

    /**
     * Render a select dropdown
     *
     * @param array<int|string, string> $options
     */
    protected function renderSelect(string $name, mixed $value, array $options): void
    {
        $field_id = $this->getFieldId($name);
        $field_name = $this->getFieldName($name);
        ?>
        <select id="<?php echo esc_attr($field_id); ?>" name="<?php echo esc_attr($field_name); ?>" class="cs-input cs-select-input">
            <?php foreach ($options as $option_value => $option_label): ?>
                <option value="<?php echo esc_attr((string) $option_value); ?>" <?php selected($value, $option_value); ?>>
                    <?php echo esc_html($option_label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    /**
     * Start a module card
     */
    protected function renderCardStart(): void
    {
        ?>
        <div class="cs-settings-card" data-module="<?php echo esc_attr($this->getId()); ?>">
            <div class="cs-settings-header">
                <h2 class="cs-settings-title">
                    <span class="dashicons dashicons-<?php echo esc_attr($this->getIcon()); ?>"></span>
                    <?php echo esc_html($this->getTitle()); ?>
                </h2>
            </div>
            <div class="cs-settings-body">
        <?php
    }

    /**
     * End a module card
     */
    protected function renderCardEnd(): void
    {
        ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render a form row with label
     *
     * @param string $name Field name (used for label 'for' attribute)
     * @param string $label Label text
     * @param string $description Optional description
     */
    protected function renderFormRowStart(string $name, string $label, string $description = ''): void
    {
        $field_id = $this->getFieldId($name);
        ?>
        <div class="cs-form-row">
            <div class="cs-form-label">
                <label for="<?php echo esc_attr($field_id); ?>"><?php echo esc_html($label); ?></label>
                <?php if ($description): ?>
                    <p class="description"><?php echo esc_html($description); ?></p>
                <?php endif; ?>
            </div>
            <div class="cs-form-field">
        <?php
    }

    /**
     * End a form row
     */
    protected function renderFormRowEnd(): void
    {
        ?>
            </div>
        </div>
        <?php
    }
}
