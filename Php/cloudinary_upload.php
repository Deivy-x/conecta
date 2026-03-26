<?php

function cloudinary_upload(string $filePath, string $folder = 'quibdoconecta'): array {
    $cloudName  = getenv('CLOUDINARY_CLOUD_NAME');
    $apiKey     = getenv('CLOUDINARY_API_KEY');
    $apiSecret  = getenv('CLOUDINARY_API_SECRET');

    if (!$cloudName || !$apiKey || !$apiSecret) {
        return ['ok' => false, 'msg' => 'Cloudinary no configurado.'];
    }

    $timestamp = time();
    $params    = "folder={$folder}&timestamp={$timestamp}{$apiSecret}";
    $signature = sha1($params);

    $url = "https://api.cloudinary.com/v1_1/{$cloudName}/image/upload";

    $postFields = [
        'file'      => new CURLFile($filePath),
        'folder'    => $folder,
        'timestamp' => $timestamp,
        'api_key'   => $apiKey,
        'signature' => $signature,
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['ok' => false, 'msg' => 'Error cURL: ' . $error];
    }

    $data = json_decode($response, true);

    if (isset($data['secure_url'])) {
        return ['ok' => true, 'url' => $data['secure_url'], 'public_id' => $data['public_id']];
    }

    return ['ok' => false, 'msg' => $data['error']['message'] ?? 'Error desconocido de Cloudinary.'];
}
