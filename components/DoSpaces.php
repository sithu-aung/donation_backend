<?php

namespace app\components;

/**
 * Minimal DigitalOcean Spaces (S3-compatible) uploader using AWS Signature V4.
 *
 * No SDK dependency — a single signed PUT via cURL, so it deploys with a plain
 * `git pull` (no composer install). Objects are written virtual-hosted style
 * (bucket.region.digitaloceanspaces.com) with public-read ACL.
 */
class DoSpaces
{
    private string $key;
    private string $secret;
    private string $region; // e.g. "sfo3"
    private string $bucket; // e.g. "medico"
    private string $cdnUrl; // e.g. "https://medico.sfo3.cdn.digitaloceanspaces.com"

    public function __construct(array $cfg)
    {
        $this->key = (string)($cfg['key'] ?? '');
        $this->secret = (string)($cfg['secret'] ?? '');
        $this->region = (string)($cfg['region'] ?? 'sfo3');
        $this->bucket = (string)($cfg['bucket'] ?? '');
        $this->cdnUrl = rtrim((string)($cfg['cdn_url'] ?? ''), '/');
    }

    public function isConfigured(): bool
    {
        return $this->key !== '' && $this->secret !== '' && $this->bucket !== '';
    }

    /**
     * Upload raw bytes to $key (e.g. "redjuniors/members/abc.jpg") and return
     * the public URL.
     *
     * @throws \Exception on a non-2xx response.
     */
    public function putObject(string $key, string $body, string $contentType): string
    {
        $key = ltrim($key, '/');
        $host = $this->bucket . '.' . $this->region . '.digitaloceanspaces.com';
        $service = 's3';
        $acl = 'public-read';
        $amzDate = gmdate('Ymd\THis\Z');
        $dateStamp = gmdate('Ymd');
        $payloadHash = hash('sha256', $body);

        // Canonical URI: encode each path segment, keep the "/" separators.
        $canonicalUri = '/' . implode('/', array_map('rawurlencode', explode('/', $key)));

        $canonicalHeaders =
            "content-type:{$contentType}\n" .
            "host:{$host}\n" .
            "x-amz-acl:{$acl}\n" .
            "x-amz-content-sha256:{$payloadHash}\n" .
            "x-amz-date:{$amzDate}\n";
        $signedHeaders = 'content-type;host;x-amz-acl;x-amz-content-sha256;x-amz-date';

        $canonicalRequest =
            "PUT\n" .
            "{$canonicalUri}\n" .
            "\n" . // empty canonical query string
            "{$canonicalHeaders}\n" .
            "{$signedHeaders}\n" .
            "{$payloadHash}";

        $algorithm = 'AWS4-HMAC-SHA256';
        $credentialScope = "{$dateStamp}/{$this->region}/{$service}/aws4_request";
        $stringToSign =
            "{$algorithm}\n" .
            "{$amzDate}\n" .
            "{$credentialScope}\n" .
            hash('sha256', $canonicalRequest);

        $kDate = hash_hmac('sha256', $dateStamp, 'AWS4' . $this->secret, true);
        $kRegion = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        $authorization = "{$algorithm} Credential={$this->key}/{$credentialScope}, " .
            "SignedHeaders={$signedHeaders}, Signature={$signature}";

        $url = "https://{$host}{$canonicalUri}";
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: {$authorization}",
                "x-amz-acl: {$acl}",
                "x-amz-content-sha256: {$payloadHash}",
                "x-amz-date: {$amzDate}",
                "Content-Type: {$contentType}",
                "Content-Length: " . strlen($body),
            ],
            CURLOPT_TIMEOUT => 30,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new \Exception("Spaces upload failed (HTTP {$httpCode}): {$response} {$err}");
        }

        if ($this->cdnUrl !== '') {
            return "{$this->cdnUrl}/{$key}";
        }
        return "https://{$host}/{$key}";
    }
}
