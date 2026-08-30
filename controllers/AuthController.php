<?php

namespace app\controllers;

use app\models\Account;
use app\models\AccountToken;
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
                'message' => 'Invalid email or password'
            ];
        }

        if (!Yii::$app->security->validatePassword($password, $account->password_hash)) {
            return [
                'status' => 'error',
                'message' => 'Invalid email or password'
            ];
        }

        // Each login gets its own session token; the stored account row is
        // left alone so this login cannot log any other device out.
        $sessionToken = AccountToken::issueFor($account);

        $account = Account::find()->where(['id' => $account->id])->one();
        // Response-only: every client (old builds included) reads its session
        // token from this field. Not saved, so the legacy column keeps
        // whatever pre-deploy session may still be using it.
        $account->access_token = $sessionToken;

        unset($account['password_hash']);

        return [
            'status' => 'ok',
            'data' => $account
        ];
    }

    public function actionMemberLogin()
    {
        // Delegate to the regular login action since the functionality is the same
        return $this->actionLogin();
    }

    /**
     * Revokes only the presented session, leaving the account's other devices
     * signed in. Safe without auth: deleting a token requires knowing it.
     */
    public function actionLogout()
    {
        $authHeader = Yii::$app->request->headers->get('Authorization');
        $token = $authHeader ? str_replace('Bearer ', '', $authHeader) : '';
        AccountToken::revoke($token);

        return ['status' => 'ok'];
    }
}
