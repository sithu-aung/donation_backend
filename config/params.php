<?php

// DigitalOcean Spaces credentials live in the gitignored config/spaces-keys.php
// (deployed out-of-band). Falls back to environment variables.
$spacesKeys = @include __DIR__ . '/spaces-keys.php';
if (!is_array($spacesKeys)) {
    $spacesKeys = [];
}

return [
    'adminEmail' => 'admin@example.com',
    'senderEmail' => 'noreply@example.com',
    'senderName' => 'Example.com mailer',
    'digitalocean' => [
        'spaces' => [
            'region' => 'sfo3',
            'bucket' => 'medico',
            'folder' => 'redjuniors/members',
            'cdn_url' => 'https://medico.sfo3.cdn.digitaloceanspaces.com',
            'key' => $spacesKeys['key'] ?? (getenv('DO_SPACES_KEY') ?: ''),
            'secret' => $spacesKeys['secret'] ?? (getenv('DO_SPACES_SECRET') ?: ''),
        ],
    ],
];
