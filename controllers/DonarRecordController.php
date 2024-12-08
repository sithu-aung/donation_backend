<?php

namespace app\controllers;

use app\models\DonarRecord;
use app\models\ExpensesRecord;
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
        
        // Get all unique years from both tables
        $years = array_unique(array_merge(
            DonarRecord::find()
                ->select(['EXTRACT(YEAR FROM date) as year'])
                ->distinct()
                ->column(),
            ExpensesRecord::find()
                ->select(['EXTRACT(YEAR FROM date) as year'])
                ->distinct()
                ->column()
        ));
        rsort($years);

        // Get expenses
        $expenses = ExpensesRecord::find()
            ->select([
                'EXTRACT(YEAR FROM date) as year',
                'EXTRACT(MONTH FROM date) as month',
                'SUM(amount) as total_expense'
            ])
            ->groupBy(['EXTRACT(YEAR FROM date)', 'EXTRACT(MONTH FROM date)'])
            ->where(['EXTRACT(YEAR FROM date)' => $year])
            ->orderBy([
                'EXTRACT(YEAR FROM date)' => SORT_DESC,
                'EXTRACT(MONTH FROM date)' => SORT_DESC
            ])
            ->asArray()
            ->all();

        // Get donations
        $donations = DonarRecord::find()
            ->select([
                'EXTRACT(YEAR FROM date) as year',
                'EXTRACT(MONTH FROM date) as month',
                'SUM(amount) as total_donation'
            ])
            ->groupBy(['EXTRACT(YEAR FROM date)', 'EXTRACT(MONTH FROM date)'])
            ->where(['EXTRACT(YEAR FROM date)' => $year])
            ->orderBy([
                'EXTRACT(YEAR FROM date)' => SORT_DESC,
                'EXTRACT(MONTH FROM date)' => SORT_DESC
            ])
            ->asArray()
            ->all();

        // Combine the results
        $monthlyStats = [];
        foreach ($expenses as $expense) {
            $key = $expense['year'] . '-' . $expense['month'];
            $monthlyStats[$key] = [
                'year' => $expense['year'],
                'month' => $expense['month'],
                'total_expense' => (float)$expense['total_expense'],
                'total_donation' => 0
            ];
        }

        foreach ($donations as $donation) {
            $key = $donation['year'] . '-' . $donation['month'];
            if (!isset($monthlyStats[$key])) {
                $monthlyStats[$key] = [
                    'year' => $donation['year'],
                    'month' => $donation['month'],
                    'total_expense' => 0,
                    'total_donation' => (float)$donation['total_donation']
                ];
            } else {
                $monthlyStats[$key]['total_donation'] = (float)$donation['total_donation'];
            }
        }

        // Sort by year and month descending
        krsort($monthlyStats);

        return $this->asJson([
            'status' => 'ok',
            'data' => array_values($monthlyStats),
            'years' => $years
        ]);
    }

    public function actionYearlyStats()
    {
        $startYear = Yii::$app->request->get('startYear');
        $endYear = Yii::$app->request->get('endYear', date('Y'));
        
        // Get expenses
        $expenseQuery = ExpensesRecord::find()
            ->select([
                'EXTRACT(YEAR FROM date) as year',
                'SUM(amount) as total_expense'
            ])
            ->groupBy(['EXTRACT(YEAR FROM date)'])
            ->orderBy(['EXTRACT(YEAR FROM date)' => SORT_DESC]);
            
        // Get donations
        $donationQuery = DonarRecord::find()
            ->select([
                'EXTRACT(YEAR FROM date) as year',
                'SUM(amount) as total_donation'
            ])
            ->groupBy(['EXTRACT(YEAR FROM date)'])
            ->orderBy(['EXTRACT(YEAR FROM date)' => SORT_DESC]);
        
        // Apply year filters to both queries
        if ($startYear) {
            $expenseQuery->andWhere(['>=', 'EXTRACT(YEAR FROM date)', $startYear]);
            $donationQuery->andWhere(['>=', 'EXTRACT(YEAR FROM date)', $startYear]);
        }
        if ($endYear) {
            $expenseQuery->andWhere(['<=', 'EXTRACT(YEAR FROM date)', $endYear]);
            $donationQuery->andWhere(['<=', 'EXTRACT(YEAR FROM date)', $endYear]);
        }

        $expenses = $expenseQuery->asArray()->all();
        $donations = $donationQuery->asArray()->all();

        // Combine the results
        $yearlyStats = [];
        foreach ($expenses as $expense) {
            $yearlyStats[$expense['year']] = [
                'year' => $expense['year'],
                'total_expense' => (float)$expense['total_expense'],
                'total_donation' => 0
            ];
        }

        foreach ($donations as $donation) {
            if (!isset($yearlyStats[$donation['year']])) {
                $yearlyStats[$donation['year']] = [
                    'year' => $donation['year'],
                    'total_expense' => 0,
                    'total_donation' => (float)$donation['total_donation']
                ];
            } else {
                $yearlyStats[$donation['year']]['total_donation'] = (float)$donation['total_donation'];
            }
        }

        // Sort by year descending
        krsort($yearlyStats);

        return $this->asJson([
            'status' => 'ok',
            'data' => array_values($yearlyStats)
        ]);
    }

    // public function actionMonthlyStats()
    // {
    //     $year = Yii::$app->request->get('year', date('Y'));
    //     $stats = DonarRecord::find()
    //         ->select([
    //             'EXTRACT(YEAR FROM date) as year',
    //             'EXTRACT(MONTH FROM date) as month',
    //             'SUM(amount) as total'
    //         ])
    //         ->groupBy(['EXTRACT(YEAR FROM date)', 'EXTRACT(MONTH FROM date)'])
    //         ->where(['EXTRACT(YEAR FROM date)' => $year])
    //         ->orderBy([
    //             'EXTRACT(YEAR FROM date)' => SORT_DESC,
    //             'EXTRACT(MONTH FROM date)' => SORT_DESC
    //         ])
    //         ->asArray()
    //         ->all();

    //     return $this->asJson([
    //         'status' => 'ok',
    //         'data' => $stats
    //     ]);
    // }

    // public function actionYearlyStats()
    // {
    //     $startYear = Yii::$app->request->get('startYear');
    //     $endYear = Yii::$app->request->get('endYear', date('Y'));
        
    //     $query = DonarRecord::find()
    //         ->select([
    //             'EXTRACT(YEAR FROM date) as year',
    //             'SUM(amount) as total'
    //         ])
    //         ->groupBy(['EXTRACT(YEAR FROM date)'])
    //         ->orderBy(['EXTRACT(YEAR FROM date)' => SORT_DESC]);
            
    //     if ($startYear) {
    //         $query->andWhere(['>=', 'EXTRACT(YEAR FROM date)', $startYear]);
    //     }
    //     if ($endYear) {
    //         $query->andWhere(['<=', 'EXTRACT(YEAR FROM date)', $endYear]);
    //     }

    //     $stats = $query->asArray()->all();

    //     return $this->asJson([
    //         'status' => 'ok',
    //         'data' => $stats
    //     ]);
    // }
}