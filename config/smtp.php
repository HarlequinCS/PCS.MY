<?php
/**
 * SMTP Email Configuration
 * Hostinger SMTP settings for order confirmation emails
 * 
 * SECURITY: Do not commit this file with real credentials to version control
 * Use environment variables or secure configuration management in production
 */

// Prevent direct access
if (!defined('SMTP_CONFIG_LOADED')) {
    define('SMTP_CONFIG_LOADED', true);
}

// Load from environment variables if available, otherwise use config array
// Priority: Environment variables > Config array
return [
    // Hostinger SMTP Server Settings
    'host' => getenv('SMTP_HOST') ?: 'smtp.hostinger.com',
    'port' => (int)(getenv('SMTP_PORT') ?: 465),
    'encryption' => getenv('SMTP_ENCRYPTION') ?: 'ssl',
    
    // SMTP Authentication (FROM email must match username for Hostinger)
    'username' => getenv('SMTP_USERNAME') ?: 'info@projectcutisem.com',
    'password' => getenv('SMTP_PASSWORD') ?: 'Infopcs@2025', // Set via environment variable
    
    // Email Settings
    'from_email' => getenv('SMTP_FROM_EMAIL') ?: getenv('SMTP_USERNAME') ?: 'info@projectcutisem.com',
    'from_name' => getenv('SMTP_FROM_NAME') ?: 'Projek Cuti Sem',
    
    // Configuration Notes:
    // - Hostinger requires FROM email to match SMTP username
    // - Port 465 uses SSL encryption
    // - Use environment variables for production (more secure)
    // - Set SMTP_PASSWORD environment variable on server
];

