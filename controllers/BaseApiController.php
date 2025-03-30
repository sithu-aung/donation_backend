<?php

namespace app\controllers;

use yii\filters\ContentNegotiator;
use yii\filters\Cors;
use yii\web\Controller;
use yii\web\Response;

class BaseApiController extends Controller
{
    public $enableCsrfValidation = false;

    public function behaviors(): array
    {
        return [
            'contentNegotiator' => [
                'class' => ContentNegotiator::class,
                'formats' => [
                    'application/json' => Response::FORMAT_JSON,
                ],
            ],
            'corsFilter' => [
                'class' => Cors::class,
                'cors' => [
                    // restrict access to domains:
                    'Origin' => ['https://firebase.redjuniors.moo.com', 'http://16.176.19.197', 
                        'http://localhost:5173',
                        'http://localhost:5174',
                        'http://localhost:5175',
                        'http://170.64.231.139',
                        'https://todolist.mooo.com',
                        'http://localhost:49707'
                    ],
                    'Access-Control-Request-Method' => ['POST', 'GET', 'OPTIONS'],
                    'Access-Control-Allow-Credentials' => true,
                    'Access-Control-Request-Headers' => ['Access-Control-Allow-Headers', 'Origin', 'X-Api-Key', 'X-Requested-With', 'Content-Type', 'Accept', 'Authorization', 'X-Custom-Header'],
                    'Access-Control-Allow-Headers' => ['Access-Control-Allow-Headers', 'Origin', 'X-Api-Key', 'X-Requested-With', 'Content-Type', 'Accept', 'Authorization', 'X-Custom-Header'],
                ],
            ],
        ];
    }
}
