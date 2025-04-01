<?php

namespace app\modules\api;

use yii\base\Module as BaseModule;

class Module extends BaseModule
{
    public $controllerNamespace = 'app\modules\api\controllers';

    public function init()
    {
        parent::init();

        // Set default response format to JSON
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        // Configure CORS
        \Yii::$app->response->headers->set('Access-Control-Allow-Origin', '*');
        \Yii::$app->response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, HEAD, OPTIONS');
        \Yii::$app->response->headers->set('Access-Control-Allow-Headers', '*');
    }
}
