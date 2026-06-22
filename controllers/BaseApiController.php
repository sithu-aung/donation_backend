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
                    // Allow all origins
                    'Origin' => ['https://redjuniors-b81b4.web.app','https://donation-coral-five.vercel.app'],
                    'Access-Control-Request-Method' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'],
                    'Access-Control-Request-Headers' => ['*'],
                    'Access-Control-Allow-Credentials' => true,
                    'Access-Control-Max-Age' => 3600, // Cache preflight for 1 hour
                    'Access-Control-Expose-Headers' => ['X-Pagination-Current-Page', 'X-Pagination-Page-Count', 'X-Pagination-Per-Page', 'X-Pagination-Total-Count'],
                ],
            ],
        ];
    }

    /**
     * Handle OPTIONS requests for CORS preflight
     */
    public function actionOptions()
    {
        \Yii::$app->response->statusCode = 200;
        return;
    }

    /**
     * Tell clients not to cache API responses. Without this the API sends no
     * cache headers, so a browser/service worker can keep showing an outdated
     * denormalized snapshot (e.g. a just-corrected patient address) until its
     * own disk cache expires, even after a refresh.
     */
    public function afterAction($action, $result)
    {
        $result = parent::afterAction($action, $result);
        $headers = \Yii::$app->response->headers;
        $headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $headers->set('Pragma', 'no-cache');
        return $result;
    }
}
