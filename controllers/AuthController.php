<?php

namespace app\controllers;

use app\models\Account;
use app\models\Admin;
use Yii;

class AuthController extends BaseApiController
{

    public function actionLogin()
    {

        $request = Yii::$app->request;
        $rawBody = $request->getRawBody();
        $data = json_decode($rawBody, true);

        if (empty($data) || !isset($data['email'])) {
            return [
                'status' => 'error',
                'message' => 'Invalid input data',
                'received_data' => $data
            ];
        }

        $email = $data['email'];
        $password = $data['password'];
        $account = Account::findOne(['email' => $email]);

        if ($account == null) {
            return [
                'status' => 'error',
                'data' => 'Invalid email or password'
            ];
        }

        if (!Yii::$app->security->validatePassword($password, $account->password_hash)) {
            return [
                'status' => 'error',
                'data' => 'Invalid email or password'
            ];
        }

        $account->access_token = Yii::$app->security->generateRandomString(64);
        $account->save();

        $account = Account::find()->where(['id' => $account->id])->one();

        unset($account['password_hash']);

        return [
            'status' => 'ok',
            'data' => $account
        ];
    }
}
