<?php
/**
 * Cloudinary media URL helper.
 * Maps local paths (e.g. images/hijaufront.svg) to CDN URLs when configured.
 */

if (!defined('CLOUDINARY_HELPER_LOADED')) {
    define('CLOUDINARY_HELPER_LOADED', true);
}

require_once __DIR__ . '/../config/cloudinary.php';

function cloudinary_enabled(): bool
{
    return defined('CLOUDINARY_CLOUD_NAME') && CLOUDINARY_CLOUD_NAME !== '';
}

function media_public_id(string $path): string
{
    $filename = pathinfo($path, PATHINFO_FILENAME);
    $filename = str_replace(' ', '_', $filename);

    return 'pcs/' . $filename;
}

function media_url(string $path, array $options = []): string
{
    $path = trim($path);
    if ($path === '') {
        return '';
    }

    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    $normalized = ltrim(str_replace('\\', '/', $path), '/');

    if (!cloudinary_enabled()) {
        return '/' . $normalized;
    }

    $isVideo = (bool) preg_match('/\.(mp4|webm|mov)$/i', $path);
    $resource = $isVideo ? 'video' : 'image';
    $transform = $options['transform'] ?? ($isVideo ? 'q_auto' : 'f_auto,q_auto');
    $publicId = media_public_id($path);

    return 'https://res.cloudinary.com/' . rawurlencode(CLOUDINARY_CLOUD_NAME)
        . '/' . $resource . '/upload/' . $transform . '/' . $publicId;
}

function e_media_url(string $path, array $options = []): string
{
    return htmlspecialchars(media_url($path, $options), ENT_QUOTES, 'UTF-8');
}
