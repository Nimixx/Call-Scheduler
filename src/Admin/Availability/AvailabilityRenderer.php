<?php

declare(strict_types=1);

namespace CallScheduler\Admin\Availability;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles HTML rendering for availability pages
 */
final class AvailabilityRenderer
{
    private const DAYS_OF_WEEK = [
        0 => 'Neděle',
        1 => 'Pondělí',
        2 => 'Úterý',
        3 => 'Středa',
        4 => 'Čtvrtek',
        5 => 'Pátek',
        6 => 'Sobota',
    ];

    private AvailabilityService $service;

    public function __construct(AvailabilityService $service)
    {
        $this->service = $service;
    }

    public function renderPage(array $data): void
    {
        ?>
        <div class="wrap cs-availability-page">
            <?php $this->renderHeader($data); ?>
            <?php $this->renderSuccessNotice($data); ?>
            <?php $this->renderErrorNotice($data); ?>

            <?php if (empty($data['team_members'])): ?>
                <?php $this->renderNoTeamMembersNotice(); ?>
            <?php else: ?>
                <?php $this->renderMemberCard($data); ?>
                <?php $this->renderInstructions(); ?>
                <?php $this->renderAvailabilityForm($data); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    public function renderInstallationError(): void
    {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Nastavení dostupnosti', 'call-scheduler'); ?></h1>
            <div class="notice notice-error">
                <p>
                    <?php echo esc_html__('Databázové tabulky pluginu nejsou nainstalovány. Prosím deaktivujte a znovu aktivujte plugin.', 'call-scheduler'); ?>
                </p>
            </div>
        </div>
        <?php
    }

    private function renderHeader(array $data): void
    {
        ?>
        <h1 class="wp-heading-inline"><?php echo esc_html__('Nastavení dostupnosti', 'call-scheduler'); ?></h1>

        <?php if (!empty($data['team_members'])): ?>
            <a href="<?php echo esc_url(admin_url('users.php')); ?>" class="page-title-action">
                <?php echo esc_html__('Spravovat členy týmu', 'call-scheduler'); ?>
            </a>
        <?php endif; ?>

        <hr class="wp-header-end">
        <?php
    }

    private function renderSuccessNotice(array $data): void
    {
        if (!$data['show_success']) {
            return;
        }
        ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <span class="dashicons dashicons-yes-alt"></span>
                <?php echo esc_html__('Dostupnost byla úspěšně uložena!', 'call-scheduler'); ?>
            </p>
        </div>
        <?php
    }

    private function renderErrorNotice(array $data): void
    {
        if (!$data['show_error']) {
            return;
        }
        ?>
        <div class="notice notice-error is-dismissible">
            <p>
                <span class="dashicons dashicons-warning"></span>
                <?php echo esc_html__('Chyba při ukládání dostupnosti. Některé změny se nemusely uložit. Zkuste to prosím znovu.', 'call-scheduler'); ?>
            </p>
        </div>
        <?php
    }

    private function renderNoTeamMembersNotice(): void
    {
        ?>
        <div class="notice notice-warning">
            <p>
                <strong><?php echo esc_html__('Nebyli nalezeni žádní členové týmu.', 'call-scheduler'); ?></strong><br>
                <?php echo esc_html__('Vytvořte WordPress uživatele a nastavte jeho user meta "cs_is_team_member" na "1", aby byl dostupný pro rezervace.', 'call-scheduler'); ?>
            </p>
            <p>
                <a href="<?php echo esc_url(admin_url('user-new.php')); ?>" class="button button-primary">
                    <?php echo esc_html__('Přidat nového uživatele', 'call-scheduler'); ?>
                </a>
            </p>
        </div>
        <?php
    }

    private function renderMemberCard(array $data): void
    {
        ?>
        <div class="cs-member-card">
            <form method="get" action="" class="cs-member-selector">
                <input type="hidden" name="page" value="cs-availability" />

                <div class="cs-member-info">
                    <label for="user_id" class="cs-label">
                        <span class="dashicons dashicons-businessperson"></span>
                        <?php echo esc_html__('Člen týmu', 'call-scheduler'); ?>
                    </label>
                    <select name="user_id" id="user_id" class="cs-select" onchange="this.form.submit()">
                        <?php foreach ($data['team_members'] as $member): ?>
                            <option value="<?php echo esc_attr($member->ID); ?>" <?php selected($data['selected_user_id'], $member->ID); ?>>
                                <?php echo esc_html($member->display_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>

            <?php $this->renderMemberSummary($data); ?>
        </div>
        <?php
    }

    private function renderMemberSummary(array $data): void
    {
        $active_days = count($data['availability']);
        ?>
        <div class="cs-summary-bar">
            <?php if ($active_days > 0): ?>
                <div class="cs-summary">
                    <span class="dashicons dashicons-calendar-alt"></span>
                    <?php
                    printf(
                        esc_html(_n('%d den nakonfigurován', '%d dnů nakonfigurováno', $active_days, 'call-scheduler')),
                        $active_days
                    );
                    ?>
                </div>
            <?php else: ?>
                <div class="cs-summary cs-summary-empty">
                    <span class="dashicons dashicons-calendar-alt"></span>
                    <?php echo esc_html__('Není nakonfigurována žádná dostupnost', 'call-scheduler'); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    private function renderInstructions(): void
    {
        ?>
        <div class="cs-instructions">
            <p>
                <span class="dashicons dashicons-info"></span>
                <?php echo esc_html__('Nastavte pracovní dobu pro každý den. Nezaškrtnuté dny nebudou dostupné pro rezervace.', 'call-scheduler'); ?>
            </p>
        </div>
        <?php
    }

    private function renderAvailabilityForm(array $data): void
    {
        ?>
        <form method="post" action="" class="cs-availability-form">
            <?php wp_nonce_field('cs_save_availability', 'cs_availability_nonce'); ?>
            <input type="hidden" name="user_id" value="<?php echo esc_attr($data['selected_user_id']); ?>" />

            <?php $this->renderQuickActions(); ?>
            <?php $this->renderDaysTable($data); ?>

            <p class="submit">
                <input type="submit"
                       name="cs_save_availability"
                       class="button button-primary button-large"
                       value="<?php echo esc_attr__('Uložit dostupnost', 'call-scheduler'); ?>" />
                <span class="spinner"></span>
            </p>
        </form>
        <?php
    }

    private function renderQuickActions(): void
    {
        ?>
        <div class="cs-quick-actions">
            <button type="button" class="button" onclick="wbToggleWeekdays(true)">
                <?php echo esc_html__('Povolit pracovní dny', 'call-scheduler'); ?>
            </button>
            <button type="button" class="button" onclick="wbToggleAll(false)">
                <?php echo esc_html__('Vypnout vše', 'call-scheduler'); ?>
            </button>
            <button type="button" class="button" onclick="wbSetAllTimes('09:00', '17:00')">
                <?php echo esc_html__('Nastavit vše na 9-17', 'call-scheduler'); ?>
            </button>
        </div>
        <?php
    }

    private function renderDaysTable(array $data): void
    {
        ?>
        <table class="wp-list-table widefat fixed striped cs-days-table">
            <thead>
                <tr>
                    <th class="cs-col-enable"><?php echo esc_html__('Dostupný', 'call-scheduler'); ?></th>
                    <th class="cs-col-day"><?php echo esc_html__('Den v týdnu', 'call-scheduler'); ?></th>
                    <th class="cs-col-time"><?php echo esc_html__('Začátek', 'call-scheduler'); ?></th>
                    <th class="cs-col-time"><?php echo esc_html__('Konec', 'call-scheduler'); ?></th>
                    <th class="cs-col-hours"><?php echo esc_html__('Hodin', 'call-scheduler'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (self::DAYS_OF_WEEK as $day_num => $day_name): ?>
                    <?php $this->renderDayRow($day_num, $day_name, $data['availability']); ?>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    private function renderDayRow(int $day_num, string $day_name, array $availability): void
    {
        $has_availability = isset($availability[$day_num]);
        $start_time = $has_availability ? substr($availability[$day_num]->start_time, 0, 5) : '09:00';
        $end_time = $has_availability ? substr($availability[$day_num]->end_time, 0, 5) : '17:00';
        $row_class = $has_availability ? 'cs-row-enabled' : 'cs-row-disabled';
        ?>
        <tr class="<?php echo esc_attr($row_class); ?>" data-day="<?php echo esc_attr($day_num); ?>">
            <td class="cs-col-enable">
                <label class="cs-toggle">
                    <input type="checkbox"
                           name="days[<?php echo esc_attr($day_num); ?>][enabled]"
                           value="1"
                           <?php checked($has_availability); ?>
                           class="cs-day-checkbox"
                           data-day="<?php echo esc_attr($day_num); ?>" />
                    <span class="cs-toggle-slider"></span>
                </label>
            </td>
            <td class="cs-col-day">
                <strong class="cs-day-name">
                    <?php echo esc_html__($day_name, 'call-scheduler'); ?>
                </strong>
            </td>
            <td class="cs-col-time">
                <input type="time"
                       name="days[<?php echo esc_attr($day_num); ?>][start_time]"
                       value="<?php echo esc_attr($start_time); ?>"
                       class="cs-time-input"
                       data-day="<?php echo esc_attr($day_num); ?>" />
            </td>
            <td class="cs-col-time">
                <input type="time"
                       name="days[<?php echo esc_attr($day_num); ?>][end_time]"
                       value="<?php echo esc_attr($end_time); ?>"
                       class="cs-time-input"
                       data-day="<?php echo esc_attr($day_num); ?>" />
            </td>
            <td class="cs-col-hours">
                <span class="cs-hours-display" data-day="<?php echo esc_attr($day_num); ?>">
                    <?php echo $has_availability ? $this->service->calculateHours($start_time, $end_time) : '-'; ?>
                </span>
            </td>
        </tr>
        <?php
    }
}
