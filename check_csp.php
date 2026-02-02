<?php
/**
 * CSP Diagnostic Tool
 * Use this to check what CSP headers are actually being sent in production
 * 
 * Instructions:
 * 1. Upload this file to your production server
 * 2. Visit: https://projectcutisem.com/check_csp.php
 * 3. Check the output to see all CSP-related headers
 * 4. Delete this file after debugging (security)
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>CSP Diagnostic Tool</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .header { background: #fff; padding: 15px; margin: 10px 0; border-left: 4px solid #007cba; }
        .header-name { font-weight: bold; color: #007cba; }
        .header-value { color: #333; word-break: break-all; }
        .warning { background: #fff3cd; border-left-color: #ffc107; }
        .error { background: #f8d7da; border-left-color: #dc3545; }
        .success { background: #d4edda; border-left-color: #28a745; }
        h1 { color: #333; }
    </style>
</head>
<body>
    <h1>Content Security Policy (CSP) Diagnostic Tool</h1>
    <p><strong>⚠️ DELETE THIS FILE AFTER DEBUGGING - Security Risk!</strong></p>
    
    <h2>All Response Headers</h2>
    <?php
    $headers = headers_list();
    $cspFound = false;
    
    foreach ($headers as $header) {
        if (stripos($header, 'Content-Security-Policy') !== false) {
            $cspFound = true;
            echo '<div class="header error">';
            echo '<div class="header-name">PHP Header (headers_list):</div>';
            echo '<div class="header-value">' . htmlspecialchars($header) . '</div>';
            echo '</div>';
        }
    }
    
    // Check what headers will be sent
    echo '<h2>Headers to be Sent</h2>';
    if (function_exists('apache_response_headers')) {
        $apacheHeaders = apache_response_headers();
        foreach ($apacheHeaders as $name => $value) {
            if (stripos($name, 'Content-Security-Policy') !== false) {
                $cspFound = true;
                echo '<div class="header warning">';
                echo '<div class="header-name">Apache Response Header:</div>';
                echo '<div class="header-value"><strong>' . htmlspecialchars($name) . ':</strong> ' . htmlspecialchars($value) . '</div>';
                echo '</div>';
            }
        }
    }
    
    // Check .htaccess CSP
    echo '<h2>.htaccess CSP Configuration</h2>';
    $htaccessFile = __DIR__ . '/.htaccess';
    if (file_exists($htaccessFile)) {
        $htaccessContent = file_get_contents($htaccessFile);
        if (preg_match('/Content-Security-Policy[^"]*"([^"]+)"/i', $htaccessContent, $matches)) {
            echo '<div class="header success">';
            echo '<div class="header-name">.htaccess CSP Found:</div>';
            echo '<div class="header-value">' . htmlspecialchars($matches[1]) . '</div>';
            echo '</div>';
            
            // Extract form-action
            if (preg_match('/form-action\s+([^;]+)/i', $matches[1], $formAction)) {
                echo '<div class="header">';
                echo '<div class="header-name">form-action directive:</div>';
                echo '<div class="header-value">' . htmlspecialchars(trim($formAction[1])) . '</div>';
                echo '</div>';
            }
        } else {
            echo '<div class="header error">No CSP found in .htaccess</div>';
        }
    } else {
        echo '<div class="header error">.htaccess file not found</div>';
    }
    
    // Check checkout.php form action
    echo '<h2>checkout.php Form Configuration</h2>';
    $checkoutFile = __DIR__ . '/checkout.php';
    if (file_exists($checkoutFile)) {
        $checkoutContent = file_get_contents($checkoutFile);
        if (preg_match('/<form[^>]*action=["\']([^"\']+)["\']/i', $checkoutContent, $matches)) {
            echo '<div class="header success">';
            echo '<div class="header-name">Form action attribute:</div>';
            echo '<div class="header-value">' . htmlspecialchars($matches[1]) . '</div>';
            echo '</div>';
        } else {
            echo '<div class="header warning">No form action found (might be empty)</div>';
        }
    }
    
    if (!$cspFound) {
        echo '<div class="header error">';
        echo '<div class="header-name">⚠️ WARNING:</div>';
        echo '<div class="header-value">No Content-Security-Policy header found in PHP headers. Check server-level configuration (Cloudflare, cPanel, Apache config, etc.)</div>';
        echo '</div>';
    }
    
    echo '<h2>Recommendations</h2>';
    echo '<div class="header">';
    echo '<ol>';
    echo '<li>Check browser DevTools → Network → checkout.php → Headers to see actual CSP being sent</li>';
    echo '<li>If CSP is set at server level (Cloudflare, cPanel), you may need to configure it there</li>';
    echo '<li>Ensure form-action allows \'self\' or the exact form action URL</li>';
    echo '<li>Clear browser cache and test again</li>';
    echo '</ol>';
    echo '</div>';
    ?>
    
    <h2>Test Form Submission</h2>
    <form method="POST" action="/checkout.php" style="background: #fff; padding: 15px; border-left: 4px solid #007cba;">
        <p>This form uses the same action as checkout.php:</p>
        <input type="hidden" name="test" value="1">
        <button type="submit" style="padding: 10px 20px; background: #007cba; color: white; border: none; cursor: pointer;">
            Test Form Submission
        </button>
        <p><small>Note: This will redirect to checkout.php. Check browser console for CSP errors.</small></p>
    </form>
    
    <hr>
    <p><strong>⚠️ Remember to delete this file after debugging!</strong></p>
</body>
</html>
