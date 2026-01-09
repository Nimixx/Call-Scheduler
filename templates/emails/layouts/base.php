<?php
/**
 * Base Email Layout (Light Theme - Clean & Modern)
 *
 * Required variables:
 * @var string   $email_title    - Email title for <title> tag
 * @var callable $email_content  - Closure that renders the email body
 *
 * Optional variables:
 * @var string $siteName    - Website name (default: WordPress site name)
 * @var string $logoUrl     - Logo URL (optional - shows text if missing)
 * @var string $adminEmail  - Contact email (optional)
 * @var string $accentColor - Primary accent color (default: #2563eb)
 * @var string $preheader   - Email preheader text (optional)
 */

if (!defined('ABSPATH')) {
    exit;
}

// Defaults with WordPress integration
$siteName    = $siteName ?? get_bloginfo('name');
$accentColor = $accentColor ?? '#2563eb';
$adminEmail  = $adminEmail ?? get_option('admin_email');
$logoUrl     = $logoUrl ?? '';
$preheader   = $preheader ?? '';
$currentYear = wp_date('Y');
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
    <![endif]-->
</head>
<body style="
    margin: 0;
    padding: 0;
    background-color: #f8fafc;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    font-size: 16px;
    line-height: 1.6;
    color: #334155;
    -webkit-font-smoothing: antialiased;
    -webkit-text-size-adjust: 100%;
">
    <!-- Preheader -->
    <?php if (!empty($preheader)): ?>
    <div style="display: none; max-height: 0; overflow: hidden; font-size: 1px; line-height: 1px; color: #f8fafc;">
        <?php echo esc_html($preheader); ?>
        &nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;
    </div>
    <?php endif; ?>

    <!-- Wrapper -->
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #f8fafc;">
        <tr>
            <td align="center" style="padding: 40px 20px;">

                <!-- Container -->
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="
                    max-width: 600px;
                    width: 100%;
                    background-color: #ffffff;
                    border-radius: 16px;
                    overflow: hidden;
                    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
                ">
                    <!-- Header -->
                    <tr>
                        <td align="center" style="padding: 32px 40px 24px 40px;">
                            <?php if (!empty($logoUrl)): ?>
                                <img
                                    src="<?php echo esc_url($logoUrl); ?>"
                                    alt="<?php echo esc_attr($siteName); ?>"
                                    width="120"
                                    style="display: block; max-width: 120px; height: auto;"
                                >
                            <?php else: ?>
                                <span style="
                                    font-size: 20px;
                                    font-weight: 600;
                                    color: <?php echo esc_attr($accentColor); ?>;
                                    letter-spacing: -0.3px;
                                "><?php echo esc_html($siteName); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td style="padding: 0 40px 40px 40px;">
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
                            background-color: #f8fafc;
                            border-top: 1px solid #e2e8f0;
                        ">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td align="center" style="font-size: 13px; color: #64748b;">
                                        <?php if (!empty($adminEmail)): ?>
                                            <p style="margin: 0 0 8px 0;">
                                                <?php echo esc_html__('Questions? Contact us at', 'call-scheduler'); ?>
                                                <a href="mailto:<?php echo esc_attr($adminEmail); ?>"
                                                   style="color: <?php echo esc_attr($accentColor); ?>; text-decoration: none;">
                                                    <?php echo esc_html($adminEmail); ?>
                                                </a>
                                            </p>
                                        <?php endif; ?>
                                        <p style="margin: 0; color: #94a3b8;">
                                            &copy; <?php echo esc_html($currentYear); ?> <?php echo esc_html($siteName); ?>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>

            </td>
        </tr>
    </table>
</body>
</html>
