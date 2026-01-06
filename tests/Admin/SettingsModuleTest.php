<?php

declare(strict_types=1);

namespace CallScheduler\Tests\Admin;

use CallScheduler\Admin\Settings\Modules\TimingModule;
use CallScheduler\Admin\Settings\Modules\WhitelabelModule;
use CallScheduler\Admin\Settings\Modules\AbstractSettingsModule;
use CallScheduler\Admin\Settings\SettingsPage;
use WP_UnitTestCase;

class SettingsModuleTest extends WP_UnitTestCase
{
    private TimingModule $timingModule;
    private WhitelabelModule $whitelabelModule;

    public function set_up(): void
    {
        parent::set_up();
        $this->timingModule = new TimingModule();
        $this->whitelabelModule = new WhitelabelModule();

        // Clear any existing options
        delete_option(AbstractSettingsModule::OPTION_NAME);
    }

    public function tear_down(): void
    {
        delete_option(AbstractSettingsModule::OPTION_NAME);
        parent::tear_down();
    }

    // ========================================
    // TimingModule Tests
    // ========================================

    public function test_timing_module_returns_correct_id(): void
    {
        $this->assertEquals('timing', $this->timingModule->getId());
    }

    public function test_timing_module_returns_correct_icon(): void
    {
        $this->assertEquals('clock', $this->timingModule->getIcon());
    }

    public function test_timing_module_returns_correct_defaults(): void
    {
        $defaults = $this->timingModule->getDefaults();

        $this->assertArrayHasKey('slot_duration', $defaults);
        $this->assertArrayHasKey('buffer_time', $defaults);
        $this->assertEquals(60, $defaults['slot_duration']);
        $this->assertEquals(0, $defaults['buffer_time']);
    }

    public function test_timing_module_sanitizes_valid_slot_duration(): void
    {
        $input = ['slot_duration' => 30, 'buffer_time' => 5];
        $result = $this->timingModule->sanitize($input);

        $this->assertEquals(30, $result['slot_duration']);
    }

    public function test_timing_module_rejects_invalid_slot_duration(): void
    {
        $input = ['slot_duration' => 45, 'buffer_time' => 0]; // 45 is not in valid list
        $result = $this->timingModule->sanitize($input);

        $this->assertEquals(60, $result['slot_duration']); // Falls back to default
    }

    public function test_timing_module_accepts_all_valid_durations(): void
    {
        $validDurations = [15, 30, 60, 90, 120];

        foreach ($validDurations as $duration) {
            $input = ['slot_duration' => $duration, 'buffer_time' => 0];
            $result = $this->timingModule->sanitize($input);
            $this->assertEquals($duration, $result['slot_duration'], "Duration {$duration} should be accepted");
        }
    }

    public function test_timing_module_sanitizes_buffer_time(): void
    {
        $input = ['slot_duration' => 60, 'buffer_time' => 15];
        $result = $this->timingModule->sanitize($input);

        $this->assertEquals(15, $result['buffer_time']);
    }

    public function test_timing_module_rejects_buffer_time_exceeding_slot_duration(): void
    {
        $input = ['slot_duration' => 30, 'buffer_time' => 60]; // Buffer > slot
        $result = $this->timingModule->sanitize($input);

        $this->assertEquals(0, $result['buffer_time']); // Falls back to 0
    }

    public function test_timing_module_converts_string_to_int(): void
    {
        $input = ['slot_duration' => '30', 'buffer_time' => '10'];
        $result = $this->timingModule->sanitize($input);

        $this->assertSame(30, $result['slot_duration']);
        $this->assertSame(10, $result['buffer_time']);
    }

    public function test_timing_module_handles_negative_values(): void
    {
        $input = ['slot_duration' => -30, 'buffer_time' => -5];
        $result = $this->timingModule->sanitize($input);

        // absint converts negative to positive, but 30 is valid
        $this->assertEquals(30, $result['slot_duration']);
        $this->assertEquals(5, $result['buffer_time']);
    }

    // ========================================
    // WhitelabelModule Tests
    // ========================================

    public function test_whitelabel_module_returns_correct_id(): void
    {
        $this->assertEquals('whitelabel', $this->whitelabelModule->getId());
    }

    public function test_whitelabel_module_returns_correct_icon(): void
    {
        $this->assertEquals('admin-customizer', $this->whitelabelModule->getIcon());
    }

    public function test_whitelabel_module_returns_correct_defaults(): void
    {
        $defaults = $this->whitelabelModule->getDefaults();

        $this->assertArrayHasKey('whitelabel_enabled', $defaults);
        $this->assertArrayHasKey('whitelabel_plugin_name', $defaults);
        $this->assertFalse($defaults['whitelabel_enabled']);
        $this->assertEquals('', $defaults['whitelabel_plugin_name']);
    }

    public function test_whitelabel_module_sanitizes_enabled_checkbox(): void
    {
        $input = ['whitelabel_enabled' => '1', 'whitelabel_plugin_name' => 'Test'];
        $result = $this->whitelabelModule->sanitize($input);

        $this->assertTrue($result['whitelabel_enabled']);
    }

    public function test_whitelabel_module_sanitizes_disabled_checkbox(): void
    {
        $input = ['whitelabel_plugin_name' => 'Test']; // No enabled key
        $result = $this->whitelabelModule->sanitize($input);

        $this->assertFalse($result['whitelabel_enabled']);
    }

    public function test_whitelabel_module_sanitizes_plugin_name(): void
    {
        $input = ['whitelabel_enabled' => '1', 'whitelabel_plugin_name' => '  Custom Name  '];
        $result = $this->whitelabelModule->sanitize($input);

        $this->assertEquals('Custom Name', $result['whitelabel_plugin_name']); // Trimmed
    }

    public function test_whitelabel_module_strips_html_from_plugin_name(): void
    {
        $input = ['whitelabel_enabled' => '1', 'whitelabel_plugin_name' => '<script>alert("xss")</script>Booking'];
        $result = $this->whitelabelModule->sanitize($input);

        $this->assertStringNotContainsString('<script>', $result['whitelabel_plugin_name']);
    }

    public function test_whitelabel_get_plugin_name_returns_default_when_disabled(): void
    {
        update_option(AbstractSettingsModule::OPTION_NAME, [
            'whitelabel_enabled' => false,
            'whitelabel_plugin_name' => 'Custom Name',
        ]);

        $name = WhitelabelModule::getPluginName();

        // Should return default "Rezervace" when disabled
        $this->assertEquals('Rezervace', $name);
    }

    public function test_whitelabel_get_plugin_name_returns_custom_when_enabled(): void
    {
        update_option(AbstractSettingsModule::OPTION_NAME, [
            'whitelabel_enabled' => true,
            'whitelabel_plugin_name' => 'My Booking App',
        ]);

        $name = WhitelabelModule::getPluginName();

        $this->assertEquals('My Booking App', $name);
    }

    public function test_whitelabel_get_plugin_name_returns_default_when_name_empty(): void
    {
        update_option(AbstractSettingsModule::OPTION_NAME, [
            'whitelabel_enabled' => true,
            'whitelabel_plugin_name' => '',
        ]);

        $name = WhitelabelModule::getPluginName();

        // Should return default when name is empty even if enabled
        $this->assertEquals('Rezervace', $name);
    }

    public function test_whitelabel_get_plugin_name_returns_default_when_no_options(): void
    {
        // No options set at all
        $name = WhitelabelModule::getPluginName();

        $this->assertEquals('Rezervace', $name);
    }

    // ========================================
    // SettingsPage Tests
    // ========================================

    public function test_settings_page_get_options_returns_defaults(): void
    {
        $options = SettingsPage::getOptions();

        $this->assertEquals(60, $options['slot_duration']);
        $this->assertEquals(0, $options['buffer_time']);
        $this->assertFalse($options['whitelabel_enabled']);
        $this->assertEquals('', $options['whitelabel_plugin_name']);
    }

    public function test_settings_page_get_options_merges_with_saved(): void
    {
        update_option(AbstractSettingsModule::OPTION_NAME, [
            'slot_duration' => 30,
            'whitelabel_enabled' => true,
        ]);

        $options = SettingsPage::getOptions();

        // Saved values
        $this->assertEquals(30, $options['slot_duration']);
        $this->assertTrue($options['whitelabel_enabled']);

        // Default values (not saved)
        $this->assertEquals(0, $options['buffer_time']);
        $this->assertEquals('', $options['whitelabel_plugin_name']);
    }

    public function test_settings_page_sanitize_options_processes_all_modules(): void
    {
        $settingsPage = new SettingsPage();

        $input = [
            'slot_duration' => 30,
            'buffer_time' => 10,
            'whitelabel_enabled' => '1',
            'whitelabel_plugin_name' => 'Test App',
        ];

        $result = $settingsPage->sanitizeOptions($input);

        // Timing module
        $this->assertEquals(30, $result['slot_duration']);
        $this->assertEquals(10, $result['buffer_time']);

        // Whitelabel module
        $this->assertTrue($result['whitelabel_enabled']);
        $this->assertEquals('Test App', $result['whitelabel_plugin_name']);
    }

    public function test_settings_page_sanitize_options_returns_defaults_for_invalid_input(): void
    {
        $settingsPage = new SettingsPage();

        $result = $settingsPage->sanitizeOptions('not an array');

        // Should return defaults
        $this->assertEquals(60, $result['slot_duration']);
        $this->assertEquals(0, $result['buffer_time']);
        $this->assertFalse($result['whitelabel_enabled']);
        $this->assertEquals('', $result['whitelabel_plugin_name']);
    }

    // ========================================
    // Integration Tests
    // ========================================

    public function test_option_name_is_consistent_across_modules(): void
    {
        $this->assertEquals('cs_options', AbstractSettingsModule::OPTION_NAME);
    }

    public function test_all_modules_implement_interface(): void
    {
        $this->assertInstanceOf(
            \CallScheduler\Admin\Settings\Modules\SettingsModuleInterface::class,
            $this->timingModule
        );
        $this->assertInstanceOf(
            \CallScheduler\Admin\Settings\Modules\SettingsModuleInterface::class,
            $this->whitelabelModule
        );
    }
}
