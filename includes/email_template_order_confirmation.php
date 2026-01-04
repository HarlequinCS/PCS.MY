<?php
/**
 * Order Confirmation Email Template
 * Professional HTML email template for order confirmation emails
 * 
 * Email-safe: Table-based layout, inline CSS, no JavaScript
 * Compatible: Gmail, Outlook, iOS Mail, major email clients
 * 
 * Variables required:
 * - $customerName: Customer's full name
 * - $orderReference: Order reference number (billExternalReferenceNo)
 * - $orderDate: Order date (formatted)
 * - $orderType: 'delivery' or 'pickup'
 * - $orderItems: Array of items with variant_display_name, size, quantity, final_amount
 * - $subtotal: Subtotal amount
 * - $totalDiscount: Total discount (if any)
 * - $totalDeliveryFee: Delivery fee (if delivery)
 * - $finalTotal: Final total amount
 * - $deliveryAddress: Delivery address array (if delivery)
 * - $logoUrl: URL to brand logo (HTTPS)
 * - $supportEmail: Support contact email
 */

// Prevent direct access
if (!defined('EMAIL_TEMPLATE_LOADED')) {
    define('EMAIL_TEMPLATE_LOADED', true);
}

// Default values if not provided
// Note: Using PNG instead of SVG for better email client compatibility
$logoUrl = $logoUrl ?? 'https://projectcutisem.com/images/android-chrome-512x512.png';
$supportEmail = $supportEmail ?? 'info@projectcutisem.com';
?>
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="x-apple-disable-message-reformatting">
    <title>Order Confirmation - Projek Cuti Sem</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
</head>
<body style="margin: 0; padding: 0; background-color: #f5f5f5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">
    <!-- Email Wrapper Table -->
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #f5f5f5;">
        <tr>
            <td align="center" style="padding: 20px 0;">
                <!-- Main Email Container -->
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" style="max-width: 600px; width: 100%; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    
                    <!-- Logo Header -->
                    <tr>
                        <td align="center" style="padding: 40px 30px 30px; background-color: #ffffff;">
                            <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="Projek Cuti Sem" width="120" border="0" style="display: block; max-width: 120px; height: auto; margin: 0 auto; font-family: Arial, sans-serif; font-size: 16px; color: #1a1a1a;" />
                        </td>
                    </tr>
                    
                    <!-- Header Section -->
                    <tr>
                        <td style="padding: 0 30px 30px; background-color: #ffffff;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td style="padding: 0 0 15px;">
                                        <h1 style="margin: 0; font-size: 28px; font-weight: 600; color: #1a1a1a; line-height: 1.2;">Order Confirmed</h1>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 0 0 20px;">
                                        <p style="margin: 0; font-size: 16px; color: #666666; line-height: 1.6;">Thank you for your order! We've received your payment and are preparing your items.</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Customer Greeting -->
                    <tr>
                        <td style="padding: 0 30px 25px; background-color: #ffffff;">
                            <p style="margin: 0; font-size: 16px; color: #333333; line-height: 1.6;">Dear <?php echo htmlspecialchars($customerName); ?>,</p>
                        </td>
                    </tr>
                    
                    <!-- Order Summary Box -->
                    <tr>
                        <td style="padding: 0 30px 25px; background-color: #ffffff;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #f9f9f9; border-left: 4px solid #1a1a1a; border-radius: 4px;">
                                <tr>
                                    <td style="padding: 25px;">
                                        <!-- Order Reference -->
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                            <tr>
                                                <td style="padding: 0 0 15px;">
                                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                                        <tr>
                                                            <td style="width: 140px; padding: 0 0 8px; font-size: 14px; font-weight: 600; color: #555555; vertical-align: top;">Order Reference:</td>
                                                            <td style="padding: 0 0 8px; font-size: 14px; color: #333333; vertical-align: top;"><?php echo htmlspecialchars($orderReference); ?></td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 0 0 15px;">
                                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                                        <tr>
                                                            <td style="width: 140px; padding: 0 0 8px; font-size: 14px; font-weight: 600; color: #555555; vertical-align: top;">Payment Status:</td>
                                                            <td style="padding: 0 0 8px; font-size: 14px; color: #28a745; font-weight: 600; vertical-align: top;">Paid</td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 0 0 15px;">
                                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                                        <tr>
                                                            <td style="width: 140px; padding: 0 0 8px; font-size: 14px; font-weight: 600; color: #555555; vertical-align: top;">Order Date:</td>
                                                            <td style="padding: 0 0 8px; font-size: 14px; color: #333333; vertical-align: top;"><?php echo htmlspecialchars($orderDate); ?></td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 0 0 20px;">
                                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                                        <tr>
                                                            <td style="width: 140px; padding: 0 0 8px; font-size: 14px; font-weight: 600; color: #555555; vertical-align: top;">Order Type:</td>
                                                            <td style="padding: 0 0 8px; font-size: 14px; color: #333333; vertical-align: top;"><?php echo ucfirst($orderType ?? 'delivery'); ?></td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                        
                                        <!-- Items Header -->
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                            <tr>
                                                <td style="padding: 0 0 15px; border-bottom: 2px solid #1a1a1a;">
                                                    <h2 style="margin: 0; font-size: 18px; font-weight: 600; color: #1a1a1a; line-height: 1.2;">Items Ordered</h2>
                                                </td>
                                            </tr>
                                        </table>
                                        
                                        <!-- Order Items -->
                                        <?php foreach ($orderItems as $item): ?>
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-top: 20px;">
                                            <tr>
                                                <td style="padding: 15px 0; border-bottom: 1px solid #e8e8e8;">
                                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                                        <tr>
                                                            <td style="padding: 0 0 8px;">
                                                                <p style="margin: 0; font-size: 15px; font-weight: 600; color: #1a1a1a; line-height: 1.4;"><?php echo htmlspecialchars($item['variant_display_name'] ?? $item['variant'] ?? 'N/A'); ?></p>
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td style="padding: 0 0 8px;">
                                                                <p style="margin: 0; font-size: 14px; color: #666666; line-height: 1.5;">Size: <?php echo htmlspecialchars($item['size'] ?? 'N/A'); ?> | Quantity: <?php echo ($item['quantity'] ?? 0); ?></p>
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td>
                                                                <p style="margin: 0; font-size: 15px; font-weight: 600; color: #1a1a1a; line-height: 1.4;">RM <?php echo number_format($item['final_amount'] ?? 0, 2); ?></p>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                        <?php endforeach; ?>
                                        
                                        <!-- Totals Section -->
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-top: 20px; border-top: 2px solid #1a1a1a;">
                                            <tr>
                                                <td style="padding: 20px 0 8px;">
                                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                                        <tr>
                                                            <td style="padding: 0; font-size: 15px; color: #333333;">Subtotal</td>
                                                            <td align="right" style="padding: 0; font-size: 15px; color: #333333;">RM <?php echo number_format($subtotal, 2); ?></td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                            <?php if ($totalDiscount > 0): ?>
                                            <tr>
                                                <td style="padding: 8px 0;">
                                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                                        <tr>
                                                            <td style="padding: 0; font-size: 15px; color: #28a745;">Discount</td>
                                                            <td align="right" style="padding: 0; font-size: 15px; color: #28a745; font-weight: 600;">-RM <?php echo number_format($totalDiscount, 2); ?></td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                            <?php endif; ?>
                                            <?php if (($orderType ?? 'delivery') === 'delivery' && $totalDeliveryFee > 0): ?>
                                            <tr>
                                                <td style="padding: 8px 0;">
                                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                                        <tr>
                                                            <td style="padding: 0; font-size: 15px; color: #333333;">Delivery Fee</td>
                                                            <td align="right" style="padding: 0; font-size: 15px; color: #333333;">RM <?php echo number_format($totalDeliveryFee, 2); ?></td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                            <?php endif; ?>
                                            <tr>
                                                <td style="padding: 15px 0 0; border-top: 2px solid #333333;">
                                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                                        <tr>
                                                            <td style="padding: 0; font-size: 20px; font-weight: 700; color: #1a1a1a;">Total</td>
                                                            <td align="right" style="padding: 0; font-size: 20px; font-weight: 700; color: #1a1a1a;">RM <?php echo number_format($finalTotal, 2); ?></td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Delivery Address (only for delivery orders) -->
                    <?php if (($orderType ?? 'delivery') === 'delivery' && !empty($deliveryAddress)): ?>
                    <tr>
                        <td style="padding: 0 30px 25px; background-color: #ffffff;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #f9f9f9; border-left: 4px solid #1a1a1a; border-radius: 4px;">
                                <tr>
                                    <td style="padding: 25px;">
                                        <h2 style="margin: 0 0 15px; font-size: 18px; font-weight: 600; color: #1a1a1a; line-height: 1.2;">Delivery Address</h2>
                                        <p style="margin: 0; font-size: 15px; color: #333333; line-height: 1.8;">
                                            <?php echo htmlspecialchars($deliveryAddress['fullname'] ?? $deliveryAddress['name'] ?? ''); ?><br>
                                            <?php echo htmlspecialchars($deliveryAddress['address1'] ?? ''); ?><br>
                                            <?php if (!empty($deliveryAddress['address2'])): ?>
                                                <?php echo htmlspecialchars($deliveryAddress['address2']); ?><br>
                                            <?php endif; ?>
                                            <?php if (!empty($deliveryAddress['address3'])): ?>
                                                <?php echo htmlspecialchars($deliveryAddress['address3']); ?><br>
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($deliveryAddress['postcode'] ?? ''); ?> <?php echo htmlspecialchars($deliveryAddress['district'] ?? ''); ?><br>
                                            <?php echo htmlspecialchars($deliveryAddress['state'] ?? ''); ?><br>
                                            Malaysia
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <?php endif; ?>
                    
                    <!-- Reassurance Message -->
                    <tr>
                        <td style="padding: 0 30px 30px; background-color: #ffffff;">
                            <p style="margin: 0 0 15px; font-size: 16px; color: #333333; line-height: 1.6;">We will notify you once your order is ready for <?php echo ($orderType ?? 'delivery') === 'pickup' ? 'pickup' : 'shipment'; ?>.</p>
                            <p style="margin: 0; font-size: 16px; color: #333333; line-height: 1.6;">If you have any questions, please don't hesitate to contact us.</p>
                        </td>
                    </tr>
                    
                    <!-- Closing Message -->
                    <tr>
                        <td style="padding: 0 30px 40px; background-color: #ffffff;">
                            <p style="margin: 0; font-size: 16px; color: #333333; line-height: 1.6;">Thank you for choosing <strong>Projek Cuti Sem</strong>!</p>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="padding: 30px; background-color: #f9f9f9; border-top: 1px solid #e8e8e8;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td align="center" style="padding: 0 0 15px;">
                                        <p style="margin: 0; font-size: 14px; font-weight: 600; color: #1a1a1a;">Projek Cuti Sem</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="center" style="padding: 0 0 15px;">
                                        <p style="margin: 0; font-size: 13px; color: #666666; line-height: 1.6;">Your new everyday outfit for class and beyond</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="center" style="padding: 0;">
                                        <p style="margin: 0; font-size: 12px; color: #999999;">
                                            Need help? Contact us at <a href="mailto:<?php echo htmlspecialchars($supportEmail); ?>" style="color: #1a1a1a; text-decoration: underline;"><?php echo htmlspecialchars($supportEmail); ?></a>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="center" style="padding: 15px 0 0;">
                                        <p style="margin: 0; font-size: 11px; color: #999999;">&copy; <?php echo date('Y'); ?> Projek Cuti Sem. All rights reserved.</p>
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

