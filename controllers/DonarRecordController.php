<?php

namespace app\controllers;

use app\models\DonarRecord;
use Yii;
use yii\web\Controller;

class DonarRecordController extends BaseApiController
{
    public function actionIndex()
    {
        $page = Yii::$app->request->get('page', 0);
        $limit = Yii::$app->request->get('limit', 50);
        $startDate = Yii::$app->request->get('startDate');
        $endDate = Yii::$app->request->get('endDate');

        $query = DonarRecord::find();
        
        if ($startDate && $endDate) {
            $query->andWhere(['between', 'date', $startDate, $endDate]);
        }
        
        $count = $query->count();
        
        $records = $query
            ->offset($page * $limit)
            ->limit($limit)
            ->orderBy(['date' => SORT_DESC])
            ->all();

        return $this->asJson([
            'status' => 'ok',
            'data' => $records,
            'total' => $count,
        ]);
    }

    public function actionCreate()
    {
        $data = Yii::$app->request->post();
        $record = new DonarRecord();
        $record->load($data, '');

        if (!$record->save()) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'Failed to create donor record',
                'errors' => $record->errors
            ]);
        }

        return $this->asJson([
            'status' => 'ok',
            'data' => $record
        ]);
    }

    public function actionUpdate($id)
    {
        $record = DonarRecord::findOne($id);
        if (!$record) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'Record not found'
            ]);
        }

        $data = Yii::$app->request->post();
        $record->load($data, '');

        if (!$record->save()) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'Failed to update donor record',
                'errors' => $record->errors
            ]);
        }

        return $this->asJson([
            'status' => 'ok',
            'data' => $record
        ]);
    }

    public function actionDelete($id)
    {
        $record = DonarRecord::findOne($id);
        if (!$record) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'Record not found'
            ]);
        }

        if (!$record->delete()) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'Failed to delete donor record'
            ]);
        }

        return $this->asJson([
            'status' => 'ok',
            'message' => 'Record deleted successfully'
        ]);
    }

    public function actionMonthlyStats()
    {
        $year = Yii::$app->request->get('year', date('Y'));
        $stats = DonarRecord::find()
            ->select([
                'EXTRACT(YEAR FROM date) as year',
                'EXTRACT(MONTH FROM date) as month',
                'SUM(amount) as total'
            ])
            ->groupBy(['EXTRACT(YEAR FROM date)', 'EXTRACT(MONTH FROM date)'])
            ->where(['EXTRACT(YEAR FROM date)' => $year])
            ->orderBy([
                'EXTRACT(YEAR FROM date)' => SORT_DESC,
                'EXTRACT(MONTH FROM date)' => SORT_DESC
            ])
            ->asArray()
            ->all();

        return $this->asJson([
            'status' => 'ok',
            'data' => $stats
        ]);
    }

    public function actionYearlyStats()
    {
        $startYear = Yii::$app->request->get('startYear');
        $endYear = Yii::$app->request->get('endYear', date('Y'));
        
        $query = DonarRecord::find()
            ->select([
                'EXTRACT(YEAR FROM date) as year',
                'SUM(amount) as total'
            ])
            ->groupBy(['EXTRACT(YEAR FROM date)'])
            ->orderBy(['EXTRACT(YEAR FROM date)' => SORT_DESC]);
            
        if ($startYear) {
            $query->andWhere(['>=', 'EXTRACT(YEAR FROM date)', $startYear]);
        }
        if ($endYear) {
            $query->andWhere(['<=', 'EXTRACT(YEAR FROM date)', $endYear]);
        }

        $stats = $query->asArray()->all();

        return $this->asJson([
            'status' => 'ok',
            'data' => $stats
        ]);
    }
}