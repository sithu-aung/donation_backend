<?php

namespace app\controllers;

use Yii;
use app\models\Admin;
use app\models\User;
use yii\filters\AccessControl;
use yii\helpers\ArrayHelper;

class BaseAuthController extends BaseApiController
{
    protected User|null $user = null;

    public function behaviors(): array
    {
        return ArrayHelper::merge(
            parent::behaviors(),
            [
                'access' => [
                    'class' => AccessControl::class,
                    'rules' => [
                        [
                            'allow' => $this->checkAuth(),
                            'roles' => ['?'],
                        ],
                    ],
                    'denyCallback' => function ($rule, $action) {
                        Yii::$app->response->statusCode = 401;
                        Yii::$app->response->data = ['status' => 'error', 'message' => 'Unauthorized!'];
                    },
                ],
            ]
        );
    }

    protected function checkAuth(): bool
    {
        $request = Yii::$app->request;
        $authHeader = $request->headers->get('Authorization');
        if ($authHeader === null) {
            return false;
        }
        $accessToken = str_replace('Bearer ', '', $authHeader);
        if (strlen($accessToken) !== 64) {
            return false;
        }
        // $this->user = User::findOne(['auth_key' => $accessToken]);
        return $this->user !== null;
    }
}
