<?php

declare(strict_types=1);

namespace CallScheduler\Admin;

use CallScheduler\Cache;
use WP_User;

if (!defined('ABSPATH')) {
    exit;
}

final class UserProfile
{
    private Cache $cache;

    public function __construct(?Cache $cache = null)
    {
        $this->cache = $cache ?? new Cache();
    }

    public function register(): void
    {
        add_action('show_user_profile', [$this, 'renderField']);
        add_action('edit_user_profile', [$this, 'renderField']);
        add_action('personal_options_update', [$this, 'saveField']);
        add_action('edit_user_profile_update', [$this, 'saveField']);
    }

    public function renderField(WP_User $user): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $is_team_member = get_user_meta($user->ID, 'cs_is_team_member', true) === '1';
        ?>
        <h3><?php echo esc_html__('Nastavení rezervací', 'call-scheduler'); ?></h3>
        <table class="form-table">
            <tr>
                <th>
                    <label for="cs_is_team_member">
                        <?php echo esc_html__('Dostupnost pro rezervace', 'call-scheduler'); ?>
                    </label>
                </th>
                <td>
                    <label>
                        <input type="checkbox"
                               name="cs_is_team_member"
                               id="cs_is_team_member"
                               value="1"
                               <?php checked($is_team_member); ?> />
                        <?php echo esc_html__('Tento uživatel je dostupný pro rezervace', 'call-scheduler'); ?>
                    </label>
                    <p class="description">
                        <?php echo esc_html__('Pokud je zaškrtnuto, tento uživatel se zobrazí jako možnost pro rezervace a bude možné nastavit jeho dostupnost.', 'call-scheduler'); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }

    public function saveField(int $user_id): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'update-user_' . $user_id)) {
            return;
        }

        $is_team_member = isset($_POST['cs_is_team_member']) ? '1' : '0';
        update_user_meta($user_id, 'cs_is_team_member', $is_team_member);

        // Invalidate team members cache when team member status changes
        $this->cache->delete('team_members');
    }
}
