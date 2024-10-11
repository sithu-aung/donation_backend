<?php

namespace app\controllers;

use app\models\ExpensesRecord;
use Yii;
use yii\web\Controller;

class ExpensesRecordController extends Controller
{
    public function actionIndex($page, $limit, $q = '')
    {
        $query = ExpensesRecord::find();
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
        $expensesRecord = ExpensesRecord::findOne($id);
        if ($expensesRecord === null) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'No ExpensesRecord Found.',
            ]);
        }

        return $this->asJson([
            'status' => 'ok',
            'data' => $expensesRecord,
        ]);
    }

    public function actionCreate()
    {
        $expensesRecord = new ExpensesRecord();
        $expensesRecord->amount = Yii::$app->request->post('amount');
        $expensesRecord->date = Yii::$app->request->post('date');
        $expensesRecord->name = Yii::$app->request->post('name');

        if (!$expensesRecord->save()) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'Failed to create ExpensesRecord.',
                'errors' => $expensesRecord->errors,
            ]);
        }

        return $this->asJson([
            'status' => 'ok',
            'data' => $expensesRecord
        ]);
    }

    public function actionUpdate($id)
    {
        $expensesRecord = ExpensesRecord::findOne($id);
        if ($expensesRecord === null) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'No ExpensesRecord Found.',
            ]);
        }

        $expensesRecord->amount = Yii::$app->request->post('amount');
        $expensesRecord->date = Yii::$app->request->post('date');
        $expensesRecord->name = Yii::$app->request->post('name');

        if (!$expensesRecord->save()) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'Failed to update ExpensesRecord.',
                'errors' => $expensesRecord->errors,
            ]);
        }

        return $this->asJson([
            'status' => 'ok',
            'data' => $expensesRecord
        ]);
    }

    public function actionDelete($id)
    {
        $expensesRecord = ExpensesRecord::findOne($id);
        if ($expensesRecord === null) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'No ExpensesRecord Found.',
            ]);
        }
        if (!$expensesRecord->delete()) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'Failed to delete ExpensesRecord.',
            ]);
        }

        return $this->asJson([
            'status' => 'ok',
            'message' => 'ExpensesRecord is deleted.'
        ]);
    }
}