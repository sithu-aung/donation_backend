<?php

namespace app\controllers;

use app\models\RequestGive;
use Yii;

class RequestGiveController extends BaseAuthController
{
    public function actionIndex($page, $limit, $q = '')
    {
        $query = RequestGive::find();
        if ($q) {
            $query = $query->where(['like', 'request', $q]);
        }
        $query = $query->offset($page * $limit)->limit($limit)->orderBy("id");

        return $this->asJson([
            'status' => 'ok',
            'data' => $query->all(),
        ]);
    }

    public function actionView($id)
    {
        $requestGive = RequestGive::findOne($id);
        if ($requestGive === null) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'No RequestGive Found.',
            ]);
        }

        return $this->asJson([
            'status' => 'ok',
            'data' => $requestGive,
        ]);
    }

    public function actionCreate()
    {
        $requestGive = new RequestGive();
        $requestGive->request = Yii::$app->request->post('request');
        $requestGive->give = Yii::$app->request->post('give');
        $requestGive->date = Yii::$app->request->post('date');

        if (!$requestGive->save()) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'Failed to create RequestGive.',
                'errors' => $requestGive->errors,
            ]);
        }

        return $this->asJson([
            'status' => 'ok',
            'data' => $requestGive
        ]);
    }

    public function actionUpdate($id)
    {
        $requestGive = RequestGive::findOne($id);
        if ($requestGive === null) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'No RequestGive Found.',
            ]);
        }

        $requestGive->request = Yii::$app->request->post('request');
        $requestGive->give = Yii::$app->request->post('give');
        $requestGive->date = Yii::$app->request->post('date');

        if (!$requestGive->save()) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'Failed to update RequestGive.',
                'errors' => $requestGive->errors,
            ]);
        }

        return $this->asJson([
            'status' => 'ok',
            'data' => $requestGive
        ]);
    }

    public function actionDelete($id)
    {
        $requestGive = RequestGive::findOne($id);
        if ($requestGive === null) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'No RequestGive Found.',
            ]);
        }
        if (!$requestGive->delete()) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'Failed to delete RequestGive.',
            ]);
        }

        return $this->asJson([
            'status' => 'ok',
            'message' => 'RequestGive is deleted.'
        ]);
    }
}