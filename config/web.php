<?php

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';

$config = [
    'id' => 'basic',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'components' => [
        'request' => [
            // \!\!\! insert a secret key in the following (if it is empty) - this is required by cookie validation
            'cookieValidationKey' => 'YdV9SRSCjrH10hGxGFRTL8HV-qJtfBf1',
        ],
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
        'i18n' => [
            'translations' => [
                'yii/bootstrap5*' => [
                    'class' => 'yii\i18n\PhpMessageSource',
                    'basePath' => '@app/messages',
                ],
            ],
        ],
        'corsFilter' => [
            'class' => \yii\filters\Cors::class,
            'cors' => [
                'Origin' => ['https://redjuniors.mooo.com'],
                'Access-Control-Allow-Origin' => ['https://redjuniors.mooo.com'],
                'Access-Control-Request-Method' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'],
                'Access-Control-Request-Headers' => ['*'],
                'Access-Control-Allow-Credentials' => true,
            ],
        ],
        'response' => [
        'class' => 'yii\web\Response',
        'on beforeSend' => function ($event) {
            $response = $event->sender;
            $allowedOrigins = [
                'http://localhost:5173',
                'https://redjuniors.mooo.com',
                'https://firebase.redjuniors.moo.com',
                'http://16.176.19.197',
                'http://localhost:49707'
            ];
            $origin = Yii::$app->request->headers->get('origin');
            
            if (in_array($origin, $allowedOrigins)) {
                $response->headers->set('Access-Control-Allow-Origin', $origin);
            } else {
                $response->headers->set('Access-Control-Allow-Origin', '*');
            }
            
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS, PUT, DELETE');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, X-CSRF-Token');
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
            
            // Handle OPTIONS request
            if (Yii::$app->request->method === 'OPTIONS') {
                $response->statusCode = 200;
                $response->data = '';
                return;
            }
        },
        ],
        'user' => [
            'identityClass' => 'app\models\User',
            'enableAutoLogin' => true,
        ],
        'errorHandler' => [
            'errorAction' => 'site/error',
        ],
        'mailer' => [
            'class' => \yii\symfonymailer\Mailer::class,
            'viewPath' => '@app/mail',
            // send all mails to a file by default.
            'useFileTransport' => true,
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'db' => $db,
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [
                '<controller:\\w+>/<action:\\w+>' => '<controller>/<action>',
                '<controller:\\w+>/<action:\\w+>/<id:\\d+>' => '<controller>/<action>',
                'api/<controller:\\w+>/<action:\\w+>' => '<controller>/<action>',
                'members/<action:\\w+>' => 'member/<action>',
                'members/all' => 'member/index',
            ],
        ],
    ],
    'params' => $params,
];

if (YII_ENV_DEV) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => 'yii\debug\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];

    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];
}

return $config;
