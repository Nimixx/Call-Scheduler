<?php
/**
 * Base Email Layout
 *
 * Master template that all emails extend. Provides consistent
 * header, footer, and styling across all email templates.
 *
 * Required variables:
 * @var string   $email_title    - Email title for <title> tag
 * @var callable $email_content  - Closure that renders the email body
 *
 * Optional variables:
 * @var string $siteName   - Website name (default: 'Call Scheduler')
 * @var string $logoUrl    - Logo URL (optional)
 * @var string $adminEmail - Contact email (optional)
 * @var string $accentColor - Primary accent color (default: #6366f1)
 */

if (!defined('ABSPATH')) {
    exit;
}

// Defaults
$siteName    = $siteName ?? 'Call Scheduler';
$accentColor = $accentColor ?? '#6366f1';
$adminEmail  = $adminEmail ?? '';
$logoUrl     = $logoUrl ?? '';

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
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?php echo esc_html($email_title); ?></title>
    <!--[if mso]>
    <style type="text/css">
        body, table, td { font-family: Arial, Helvetica, sans-serif !important; }
    </style>
    <![endif]-->
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
">
    <!-- Wrapper Table -->
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: <?php echo $colors['background']; ?>;">
        <tr>
            <td align="center" style="padding: 40px 20px;">

                <!-- Email Container -->
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="
                    max-width: 600px;
                    width: 100%;
                    background-color: <?php echo $colors['white']; ?>;
                    border-radius: 12px;
                    overflow: hidden;
                    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
                ">
                    <!-- Header -->
                    <tr>
                        <td align="center" style="padding: 32px 40px; border-bottom: 1px solid <?php echo $colors['border']; ?>;">
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
                                "><?php echo esc_html($siteName); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px;">
                            <?php
                            // Render email-specific content
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
                        ">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td align="center" style="
                                        font-size: 14px;
                                        color: <?php echo $colors['textLight']; ?>;
                                    ">
                                        <?php if (!empty($adminEmail)): ?>
                                            <p style="margin: 0 0 8px 0;">
                                                Máte dotazy? Napište nám na
                                                <a href="mailto:<?php echo esc_attr($adminEmail); ?>" style="color: <?php echo $colors['accent']; ?>; text-decoration: none;">
                                                    <?php echo esc_html($adminEmail); ?>
                                                </a>
                                            </p>
                                        <?php endif; ?>
                                        <p style="margin: 0; color: <?php echo $colors['textLight']; ?>;">
                                            &copy; <?php echo date('Y'); ?> <?php echo esc_html($siteName); ?>
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
