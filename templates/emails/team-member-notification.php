<?php
/**
 * Team Member New Booking Notification Email Template
 *
 * Available variables:
 * @var string $customerName   - Customer's name
 * @var string $customerEmail  - Customer's email address
 * @var string $bookingDate    - Formatted booking date
 * @var string $bookingTime    - Booking time (HH:MM)
 * @var string $siteName       - Website name
 * @var string $adminEmail     - Admin email address
 * @var string $logoUrl        - Logo image URL
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($siteName); ?> - Nová rezervace</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
        }
        .header {
            background-color: #2c3e50;
            padding: 30px;
            text-align: center;
        }
        .header img {
            max-width: 180px;
            height: auto;
        }
        .content {
            padding: 40px 30px;
        }
        h1 {
            color: #2c3e50;
            margin: 0 0 20px 0;
            font-size: 24px;
        }
        .booking-details {
            background-color: #f8f9fa;
            border-left: 4px solid #3498db;
            padding: 20px;
            margin: 25px 0;
        }
        .booking-details p {
            margin: 8px 0;
        }
        .booking-details strong {
            color: #2c3e50;
        }
        .customer-info {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 20px;
            margin: 25px 0;
        }
        .customer-info a {
            color: #3498db;
            text-decoration: none;
        }
        .footer {
            background-color: #f8f9fa;
            padding: 20px 30px;
            text-align: center;
            font-size: 14px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <?php if (!empty($logoUrl)): ?>
                <img src="<?php echo esc_url($logoUrl); ?>" alt="<?php echo esc_attr($siteName); ?>">
            <?php else: ?>
                <h2 style="color: #ffffff; margin: 0;"><?php echo esc_html($siteName); ?></h2>
            <?php endif; ?>
        </div>

        <div class="content">
            <h1>Nová rezervace</h1>

            <p>Máte novou rezervaci hovoru.</p>

            <div class="customer-info">
                <p><strong>Zákazník:</strong> <?php echo esc_html($customerName); ?></p>
                <p><strong>Email:</strong> <a href="mailto:<?php echo esc_attr($customerEmail); ?>"><?php echo esc_html($customerEmail); ?></a></p>
            </div>

            <div class="booking-details">
                <p><strong>Datum:</strong> <?php echo esc_html($bookingDate); ?></p>
                <p><strong>Čas:</strong> <?php echo esc_html($bookingTime); ?></p>
            </div>

            <p>Nezapomeňte si hovor poznamenat do kalendáře.</p>
        </div>

        <div class="footer">
            <p><?php echo esc_html($siteName); ?></p>
        </div>
    </div>
</body>
</html>
