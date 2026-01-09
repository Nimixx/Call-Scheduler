<?php
/**
 * Base Email Layout (Generic/Unbranded)
 *
 * Master template that all emails extend. Provides consistent
 * header, footer, and styling across all email templates.
 *
 * Required variables:
 * @var string   $email_title    - Email title for <title> tag
 * @var callable $email_content  - Closure that renders the email body
 *
 * Optional variables:
 * @var string $siteName    - Website name (default: WordPress site name)
 * @var string $logoUrl     - Logo URL (optional - shows text if missing)
 * @var string $adminEmail  - Contact email (optional)
 * @var string $accentColor - Primary accent color (default: #6366f1)
 * @var string $preheader   - Email preheader text (optional)
 */

if (!defined('ABSPATH')) {
    exit;
}

// Defaults with WordPress integration
$siteName    = $siteName ?? get_bloginfo('name');
$accentColor = $accentColor ?? '#6366f1';
$adminEmail  = $adminEmail ?? get_option('admin_email');
$logoUrl     = $logoUrl ?? '';
$preheader   = $preheader ?? '';
$currentYear = wp_date('Y');

// Design tokens (centralized styling)
$colors = [
    'accent'     => $accentColor,
    'text'       => '#1f2937',
    'textLight'  => '#6b7280',
    'background' => '#f9fafb',
    'white'      => '#ffffff',
    'border'     => '#e5e7eb',
];

$fonts = [
    'family' => "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif",
    'size'   => '16px',
    'lineHeight' => '1.6',
];
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr(get_locale()); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title><?php echo esc_html($email_title); ?></title>
    <!--[if mso]>
    <style type="text/css">
        body, table, td { font-family: Arial, Helvetica, sans-serif !important; }
        table { border-collapse: collapse; }
    </style>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style>
        /* Reset */
        body { margin: 0; padding: 0; width: 100%; }
        table { border-spacing: 0; }
        td { padding: 0; }
        img { border: 0; display: block; }

        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            .email-body { background-color: #1f2937 !important; }
            .email-container { background-color: #374151 !important; }
            .email-text { color: #f9fafb !important; }
            .email-text-light { color: #d1d5db !important; }
        }

        /* Mobile responsive */
        @media only screen and (max-width: 600px) {
            .email-container { width: 100% !important; max-width: 100% !important; }
            .email-padding { padding: 24px 16px !important; }
            .mobile-full-width { width: 100% !important; }
        }
    </style>
</head>
<body style="
    margin: 0;
    padding: 0;
    background-color: <?php echo $colors['background']; ?>;
    font-family: <?php echo $fonts['family']; ?>;
    font-size: <?php echo $fonts['size']; ?>;
    line-height: <?php echo $fonts['lineHeight']; ?>;
    color: <?php echo $colors['text']; ?>;
    -webkit-font-smoothing: antialiased;
    -webkit-text-size-adjust: 100%;
    -ms-text-size-adjust: 100%;
" class="email-body">
    <!-- Preheader (hidden preview text) -->
    <?php if (!empty($preheader)): ?>
    <div style="display: none; max-height: 0; overflow: hidden; font-size: 1px; line-height: 1px; color: #ffffff;">
        <?php echo esc_html($preheader); ?>
        &nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;
    </div>
    <?php endif; ?>

    <!-- Wrapper Table -->
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"
           style="background-color: <?php echo $colors['background']; ?>;">
        <tr>
            <td align="center" style="padding: 40px 20px;" class="email-padding">

                <!-- Email Container -->
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0"
                       class="email-container" style="
                    max-width: 600px;
                    width: 100%;
                    background-color: <?php echo $colors['white']; ?>;
                    border-radius: 12px;
                    overflow: hidden;
                    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
                ">
                    <!-- Header -->
                    <tr>
                        <td align="center" style="padding: 32px 40px; border-bottom: 1px solid <?php echo $colors['border']; ?>;" class="email-padding">
                            <?php if (!empty($logoUrl)): ?>
                                <img
                                    src="<?php echo esc_url($logoUrl); ?>"
                                    alt="<?php echo esc_attr($siteName); ?>"
                                    width="140"
                                    style="display: block; max-width: 140px; height: auto;"
                                >
                            <?php else: ?>
                                <span style="
                                    font-size: 24px;
                                    font-weight: 700;
                                    color: <?php echo $colors['accent']; ?>;
                                    letter-spacing: -0.5px;
                                " class="email-text"><?php echo esc_html($siteName); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px;" class="email-padding">
                            <?php
                            if (isset($email_content) && is_callable($email_content)) {
                                ($email_content)();
                            }
                            ?>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="
                            padding: 24px 40px;
                            background-color: <?php echo $colors['background']; ?>;
                            border-top: 1px solid <?php echo $colors['border']; ?>;
                        " class="email-padding">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td align="center" style="font-size: 14px; color: <?php echo $colors['textLight']; ?>;" class="email-text-light">
                                        <?php if (!empty($adminEmail)): ?>
                                            <p style="margin: 0 0 8px 0;">
                                                <?php echo esc_html__('Questions? Contact us at', 'call-scheduler'); ?>
                                                <a href="mailto:<?php echo esc_attr($adminEmail); ?>"
                                                   style="color: <?php echo $colors['accent']; ?>; text-decoration: none;">
                                                    <?php echo esc_html($adminEmail); ?>
                                                </a>
                                            </p>
                                        <?php endif; ?>
                                        <p style="margin: 0; color: <?php echo $colors['textLight']; ?>;">
                                            &copy; <?php echo esc_html($currentYear); ?> <?php echo esc_html($siteName); ?>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
                <!-- /Email Container -->

            </td>
        </tr>
    </table>
    <!-- /Wrapper Table -->
</body>
</html>
