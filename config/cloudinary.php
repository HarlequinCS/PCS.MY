<?php
/**
 * Cloudinary delivery configuration.
 * Credentials come from .env via config/env.php.
 */

if (!defined('CLOUDINARY_CONFIG_LOADED')) {
    define('CLOUDINARY_CONFIG_LOADED', true);
}

require_once __DIR__ . '/env.php';

return [
    'cloud_name' => defined('CLOUDINARY_CLOUD_NAME') ? CLOUDINARY_CLOUD_NAME : '',
    'api_key' => defined('CLOUDINARY_API_KEY') ? CLOUDINARY_API_KEY : '',
    'api_secret' => defined('CLOUDINARY_API_SECRET') ? CLOUDINARY_API_SECRET : '',
    'folder' => 'pcs',
];
