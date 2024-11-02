<?php

namespace app\controllers;

use app\models\DonarRecord;
use Yii;
use yii\web\Controller;

class DonarRecordController extends BaseAuthController
{
    public function actionIndex($page, $limit, $q = '')
    {
        $query = DonarRecord::find();
        if ($q) {
            $query = $query->where(['like', 'name', $q]);
        }
        $query = $query->offset($page * $limit)->limit($limit)->orderBy("id");

        return $this->asJson([
            'status' => 'ok',
            'data' => $query->all(),
        ]);
    }

    public function actionView($id)
    {
        $donarRecord = DonarRecord::findOne($id);
        if ($donarRecord === null) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'No DonarRecord Found.',
            ]);
        }

        return $this->asJson([
            'status' => 'ok',
            'data' => $donarRecord,
        ]);
    }

    public function actionCreate()
    {
        $donarRecord = new DonarRecord();
        $donarRecord->amount = Yii::$app->request->post('amount');
        $donarRecord->date = Yii::$app->request->post('date');
        $donarRecord->name = Yii::$app->request->post('name');

        if (!$donarRecord->save()) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'Failed to create DonarRecord.',
                'errors' => $donarRecord->errors,
            ]);
        }

        return $this->asJson([
            'status' => 'ok',
            'data' => $donarRecord
        ]);
    }

    public function actionUpdate($id)
    {
        $donarRecord = DonarRecord::findOne($id);
        if ($donarRecord === null) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'No DonarRecord Found.',
            ]);
        }

        $donarRecord->amount = Yii::$app->request->post('amount');
        $donarRecord->date = Yii::$app->request->post('date');
        $donarRecord->name = Yii::$app->request->post('name');

        if (!$donarRecord->save()) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'Failed to update DonarRecord.',
                'errors' => $donarRecord->errors,
            ]);
        }

        return $this->asJson([
            'status' => 'ok',
            'data' => $donarRecord
        ]);
    }

    public function actionDelete($id)
    {
        $donarRecord = DonarRecord::findOne($id);
        if ($donarRecord === null) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'No DonarRecord Found.',
            ]);
        }
        if (!$donarRecord->delete()) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'Failed to delete DonarRecord.',
            ]);
        }

        return $this->asJson([
            'status' => 'ok',
            'message' => 'DonarRecord is deleted.'
        ]);
    }
}