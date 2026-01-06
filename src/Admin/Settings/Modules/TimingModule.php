<?php

declare(strict_types=1);

namespace CallScheduler\Admin\Settings\Modules;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Timing settings module - slot duration and buffer time
 */
final class TimingModule extends AbstractSettingsModule
{
    public function getId(): string
    {
        return 'timing';
    }

    public function getTitle(): string
    {
        return __('Časování rezervací', 'call-scheduler');
    }

    public function getIcon(): string
    {
        return 'clock';
    }

    public function getDefaults(): array
    {
        return [
            'slot_duration' => 60,
            'buffer_time' => 0,
        ];
    }

    public function sanitize(array $input): array
    {
        $defaults = $this->getDefaults();
        $output = [];

        // Slot duration - must be positive and in valid list
        $slot_duration = isset($input['slot_duration']) ? absint($input['slot_duration']) : $defaults['slot_duration'];
        $valid_durations = [15, 30, 60, 90, 120];
        $output['slot_duration'] = in_array($slot_duration, $valid_durations, true) ? $slot_duration : $defaults['slot_duration'];

        // Buffer time - must be non-negative and less than slot duration
        $buffer_time = isset($input['buffer_time']) ? absint($input['buffer_time']) : $defaults['buffer_time'];
        $output['buffer_time'] = $buffer_time < $output['slot_duration'] ? $buffer_time : 0;

        return $output;
    }

    public function render(array $options): void
    {
        $this->renderCardStart();

        // Slot Duration
        $this->renderFormRowStart(
            'slot_duration',
            __('Délka rezervace', 'call-scheduler'),
            __('Jak dlouho trvá jedna schůzka.', 'call-scheduler')
        );

        $durations = [
            15 => __('15 minut', 'call-scheduler'),
            30 => __('30 minut', 'call-scheduler'),
            60 => __('1 hodina', 'call-scheduler'),
            90 => __('1,5 hodiny', 'call-scheduler'),
            120 => __('2 hodiny', 'call-scheduler'),
        ];
        $this->renderSelect('slot_duration', $options['slot_duration'] ?? 60, $durations);

        $this->renderFormRowEnd();

        // Buffer Time
        $this->renderFormRowStart(
            'buffer_time',
            __('Mezičas', 'call-scheduler'),
            __('Pauza mezi schůzkami pro přípravu.', 'call-scheduler')
        );

        $this->renderNumberInput('buffer_time', (int) ($options['buffer_time'] ?? 0), 0, 60, 5);
        echo '<span class="cs-unit">' . esc_html__('minut', 'call-scheduler') . '</span>';

        $this->renderFormRowEnd();

        $this->renderCardEnd();
    }
}
