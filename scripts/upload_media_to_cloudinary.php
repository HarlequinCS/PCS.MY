<?php
/**
 * One-time (re-runnable) upload of allowlisted /images files to Cloudinary.
 * Usage: php scripts/upload_media_to_cloudinary.php
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only be run from the command line.\n");
    exit(1);
}

$root = dirname(__DIR__);
$config = require $root . '/config/cloudinary.php';

$cloudName = $config['cloud_name'] ?? '';
$apiKey = $config['api_key'] ?? '';
$apiSecret = $config['api_secret'] ?? '';

if ($cloudName === '' || $apiKey === '' || $apiSecret === '') {
    fwrite(STDERR, "Missing CLOUDINARY_CLOUD_NAME, CLOUDINARY_API_KEY, or CLOUDINARY_API_SECRET in .env\n");
    exit(1);
}

$files = [
    'images/hijaufront.svg',
    'images/hijauback.svg',
    'images/hitamdepan.svg',
    'images/hitamback.svg',
    'images/merahdepan.svg',
    'images/merahback.svg',
    'images/halfzip.png',
    'images/halfzip2.png',
    'images/pcs.png',
    'images/pcs2.png',
    'images/lead.png',
    'images/lead2.png',
    'images/etudiants.png',
    'images/etudiants2.png',
    'images/morph1.png',
    'images/morph2.png',
    'images/morph3.png',
    'images/story.svg',
    'images/MockUp size.svg',
    'images/1.svg',
    'images/2.svg',
    'images/3.svg',
    'images/4.svg',
    'images/5.svg',
    'images/6.svg',
    'images/sulam.mp4',
];

function cloudinary_ca_bundle(): ?string
{
    $candidates = [
        getenv('SSL_CERT_FILE') ?: '',
        ini_get('curl.cainfo') ?: '',
        ini_get('openssl.cafile') ?: '',
        'C:\\laragon\\etc\\ssl\\cacert.pem',
        '/etc/ssl/certs/ca-certificates.crt',
        '/etc/pki/tls/certs/ca-bundle.crt',
    ];

    foreach ($candidates as $path) {
        if ($path !== '' && is_file($path)) {
            return $path;
        }
    }

    return null;
}

function cloudinary_sign(array $params, string $apiSecret): string
{
    ksort($params);
    $toSign = [];
    foreach ($params as $key => $value) {
        if ($value === '' || $value === null) {
            continue;
        }
        $toSign[] = $key . '=' . $value;
    }

    return sha1(implode('&', $toSign) . $apiSecret);
}

function upload_to_cloudinary(string $fullPath, string $publicId, string $cloudName, string $apiKey, string $apiSecret): array
{
    $timestamp = time();
    $params = [
        'overwrite' => 'true',
        'public_id' => $publicId,
        'timestamp' => $timestamp,
    ];
    $signature = cloudinary_sign($params, $apiSecret);

    $mime = @mime_content_type($fullPath) ?: 'application/octet-stream';
    $post = $params + [
        'api_key' => $apiKey,
        'signature' => $signature,
        'file' => new CURLFile($fullPath, $mime, basename($fullPath)),
    ];

    $ch = curl_init('https://api.cloudinary.com/v1_1/' . rawurlencode($cloudName) . '/auto/upload');
    $curlOpts = [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $post,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 180,
    ];
    $caBundle = cloudinary_ca_bundle();
    if ($caBundle) {
        $curlOpts[CURLOPT_CAINFO] = $caBundle;
    }
    curl_setopt_array($ch, $curlOpts);

    $raw = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false) {
        return ['ok' => false, 'error' => "cURL {$errno}: {$error}"];
    }

    $json = json_decode($raw, true);
    if ($http >= 200 && $http < 300 && !empty($json['secure_url'])) {
        return ['ok' => true, 'url' => $json['secure_url'], 'public_id' => $json['public_id'] ?? $publicId];
    }

    $message = $json['error']['message'] ?? $raw;
    return ['ok' => false, 'error' => "HTTP {$http}: {$message}"];
}

$ok = 0;
$fail = 0;

foreach ($files as $relative) {
    $fullPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    $publicId = 'pcs/' . str_replace(' ', '_', pathinfo($relative, PATHINFO_FILENAME));

    if (!is_file($fullPath)) {
        fwrite(STDERR, "MISSING  {$relative}\n");
        $fail++;
        continue;
    }

    echo "Uploading {$relative} -> {$publicId} ... ";
    $result = upload_to_cloudinary($fullPath, $publicId, $cloudName, $apiKey, $apiSecret);

    if ($result['ok']) {
        echo "OK {$result['url']}\n";
        $ok++;
    } else {
        echo "FAIL {$result['error']}\n";
        $fail++;
    }
}

echo "\nDone. Uploaded: {$ok}, failed: {$fail}\n";
exit($fail > 0 ? 1 : 0);
