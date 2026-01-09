<?php

declare(strict_types=1);

namespace CallScheduler\Admin;

use CallScheduler\Cache;
use CallScheduler\ConsultantRepository;
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

        $repository = new ConsultantRepository();
        $consultant = $repository->findByWpUserId($user->ID);

        $display_name = $consultant ? $consultant->displayName : $user->display_name;
        $title = $consultant ? ($consultant->title ?? '') : '';
        $bio = $consultant ? ($consultant->bio ?? '') : '';
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

        <div id="cs-consultant-fields" style="<?php echo $is_team_member ? '' : 'display:none;'; ?>">
            <h3><?php echo esc_html__('Profil konzultanta', 'call-scheduler'); ?></h3>
            <table class="form-table">
                <tr>
                    <th>
                        <label for="cs_consultant_display_name">
                            <?php echo esc_html__('Zobrazované jméno', 'call-scheduler'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="text"
                               name="cs_consultant_display_name"
                               id="cs_consultant_display_name"
                               value="<?php echo esc_attr($display_name); ?>"
                               class="regular-text" />
                        <p class="description">
                            <?php echo esc_html__('Jméno zobrazované zákazníkům při rezervaci.', 'call-scheduler'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <label for="cs_consultant_title">
                            <?php echo esc_html__('Titul / Pozice', 'call-scheduler'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="text"
                               name="cs_consultant_title"
                               id="cs_consultant_title"
                               value="<?php echo esc_attr($title); ?>"
                               class="regular-text"
                               placeholder="<?php echo esc_attr__('např. Obchodní konzultant', 'call-scheduler'); ?>" />
                    </td>
                </tr>
                <tr>
                    <th>
                        <label for="cs_consultant_bio">
                            <?php echo esc_html__('Krátký popis', 'call-scheduler'); ?>
                        </label>
                    </th>
                    <td>
                        <textarea name="cs_consultant_bio"
                                  id="cs_consultant_bio"
                                  rows="3"
                                  class="large-text"><?php echo esc_textarea($bio); ?></textarea>
                    </td>
                </tr>
            </table>
        </div>

        <script>
        jQuery(function($) {
            $('#cs_is_team_member').on('change', function() {
                $('#cs-consultant-fields').toggle(this.checked);
            });
        });
        </script>
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
        $was_team_member = get_user_meta($user_id, 'cs_is_team_member', true) === '1';

        update_user_meta($user_id, 'cs_is_team_member', $is_team_member);

        $repository = new ConsultantRepository();
        $consultant = $repository->findByWpUserId($user_id);

        if ($is_team_member === '1') {
            if ($consultant === null) {
                // Create consultant profile
                $title = isset($_POST['cs_consultant_title']) ? sanitize_text_field($_POST['cs_consultant_title']) : null;
                $bio = isset($_POST['cs_consultant_bio']) ? sanitize_textarea_field($_POST['cs_consultant_bio']) : null;
                $repository->createForUser($user_id, $title, $bio);
            } else {
                // Update existing consultant
                $displayName = isset($_POST['cs_consultant_display_name'])
                    ? sanitize_text_field($_POST['cs_consultant_display_name'])
                    : $consultant->displayName;
                $title = isset($_POST['cs_consultant_title']) ? sanitize_text_field($_POST['cs_consultant_title']) : null;
                $bio = isset($_POST['cs_consultant_bio']) ? sanitize_textarea_field($_POST['cs_consultant_bio']) : null;

                $repository->updateProfile($consultant->id, $displayName, $title, $bio);

                // Reactivate if was deactivated
                if (!$consultant->isActive) {
                    $repository->setActive($consultant->id, true);
                }
            }
        } elseif ($consultant !== null && $was_team_member) {
            // Deactivate consultant when team member disabled
            $repository->setActive($consultant->id, false);
        }

        // Invalidate caches
        $this->cache->delete('team_members');
        $this->cache->delete('consultants_active');
    }
}
