<?php

// Unit tests must never inherit the production PostgreSQL connection from
// config/db.php. SQLite's in-memory database is scoped to this PHP process and
// disappears as soon as the test application shuts down.
$config = require __DIR__ . '/web.php';

$config['id'] = 'basic-tests';
$config['components']['db'] = [
    'class' => yii\db\Connection::class,
    'dsn' => 'sqlite::memory:',
    'charset' => 'utf8',
    'enableSchemaCache' => false,
];
$config['components']['request']['cookieValidationKey'] = 'unit-test-only-key';
$config['components']['mailer']['useFileTransport'] = true;
$config['components']['assetManager'] = [
    'basePath' => dirname(__DIR__) . '/tests/_output',
    'baseUrl' => '/assets',
];

return $config;
