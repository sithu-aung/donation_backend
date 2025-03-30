<?php

namespace app\controllers;

class TestController extends BaseApiController
{
    public function actionIndex()
    {
        return [
            'status' => 'ok',
            'message' => 'API is working',
        ];
    }
}
