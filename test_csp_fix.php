<?php
/**
 * CSP Test & Fix Script
 * This script tests form submission and provides diagnostic information
 * 
 * Usage:
 * 1. Visit: https://projectcutisem.com/test_csp_fix.php
 * 2. Check the output
 * 3. Test the form at the bottom
 * 4. DELETE THIS FILE after use (security risk)
 */

// Set CSP header with form-action
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://fonts.googleapis.com https://static.cloudflareinsights.com https://cdn.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:; connect-src 'self' https://projectcutisem.com https://static.cloudflareinsights.com; frame-ancestors 'self'; base-uri 'self'; form-action 'self' https://projectcutisem.com/checkout.php http://projectcutisem.com/checkout.php;");

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>CSP Test & Fix</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; max-width: 1200px; margin: 0 auto; }
        .section { background: #f5f5f5; padding: 20px; margin: 20px 0; border-radius: 8px; }
        .success { background: #d4edda; border-left: 4px solid #28a745; }
        .error { background: #f8d7da; border-left: 4px solid #dc3545; }
        .warning { background: #fff3cd; border-left: 4px solid #ffc107; }
        .info { background: #d1ecf1; border-left: 4px solid #17a2b8; }
        h1 { color: #333; }
        h2 { color: #666; margin-top: 30px; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; }
        .test-form { background: white; padding: 20px; border: 2px solid #007cba; border-radius: 8px; }
        button { background: #007cba; color: white; padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        button:hover { background: #005a8b; }
        .result { margin-top: 20px; padding: 15px; border-radius: 4px; }
    </style>
</head>
<body>
    <h1>🔍 CSP Form-Action Diagnostic & Test</h1>
    <p><strong>⚠️ DELETE THIS FILE AFTER TESTING - Security Risk!</strong></p>

    <div class="section info">
        <h2>Current CSP Header (PHP Set)</h2>
        <p><strong>form-action:</strong> <code>'self' https://projectcutisem.com/checkout.php http://projectcutisem.com/checkout.php</code></p>
        <p>This page sets CSP via PHP header to test if it works.</p>
    </div>

    <div class="section">
        <h2>Test Form Submission</h2>
        <p>This form uses the same action as checkout.php. Submit it and check browser console for CSP errors.</p>
        
        <form method="POST" action="/checkout.php" class="test-form" id="testForm">
            <input type="hidden" name="test" value="csp_diagnostic">
            <p><strong>Form Action:</strong> <code>/checkout.php</code></p>
            <p><strong>Method:</strong> <code>POST</code></p>
            <button type="submit">Test Form Submission</button>
        </form>

        <div id="result" class="result" style="display: none;"></div>
    </div>

    <div class="section warning">
        <h2>Diagnostic Steps</h2>
        <ol>
            <li>Open browser DevTools (F12)</li>
            <li>Go to Console tab</li>
            <li>Click "Test Form Submission" above</li>
            <li>Check console for CSP errors</li>
            <li>If error appears, note the exact error message</li>
            <li>Go to Network tab → checkout.php → Headers</li>
            <li>Check what CSP header is actually being sent</li>
        </ol>
    </div>

    <div class="section">
        <h2>Next Steps Based on Results</h2>
        
        <h3>✅ If Form Submits Successfully:</h3>
        <p>CSP is working! The issue might be browser cache. Clear cache and test checkout.php again.</p>

        <h3>❌ If Form is Blocked:</h3>
        <p><strong>Check 1:</strong> Open Network tab → checkout.php → Headers → Look for Content-Security-Policy</p>
        <ul>
            <li>If CSP shows different form-action → Cloudflare or server is overriding</li>
            <li>If multiple CSP headers exist → Conflict issue</li>
            <li>If no CSP header → Server not sending it</li>
        </ul>

        <p><strong>Check 2:</strong> Cloudflare Dashboard</p>
        <ul>
            <li>Go to Rules → Transform Rules</li>
            <li>Check for CSP-modifying rules</li>
            <li>See <code>FIX_CLOUDFLARE_CSP.md</code> for instructions</li>
        </ul>

        <p><strong>Check 3:</strong> Hosting Control Panel</p>
        <ul>
            <li>Check cPanel/Plesk Security Headers</li>
            <li>Look for CSP configuration</li>
            <li>Disable or modify if needed</li>
        </ul>
    </div>

    <div class="section error">
        <h2>⚠️ Security Warning</h2>
        <p><strong>DELETE THIS FILE IMMEDIATELY AFTER TESTING!</strong></p>
        <p>This file exposes diagnostic information and should not be accessible in production.</p>
    </div>

    <script>
        document.getElementById('testForm').addEventListener('submit', function(e) {
            const resultDiv = document.getElementById('result');
            resultDiv.style.display = 'block';
            resultDiv.className = 'result info';
            resultDiv.innerHTML = '<p><strong>Form submitted!</strong> Check browser console (F12) for any CSP errors.</p><p>If you see a CSP error, note the exact message and check Network tab → Headers for the actual CSP being sent.</p>';
        });
    </script>
</body>
</html>
