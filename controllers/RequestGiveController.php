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

    /**
     * Get detailed report by year or by year and month
     */
    public function actionDetailedReport()
    {
        $year = Yii::$app->request->get('year');
        $month = Yii::$app->request->get('month');
        
        $query = RequestGive::find();
        
        if ($year) {
            $query->andWhere(['EXTRACT(YEAR FROM date)' => $year]);
            
            if ($month) {
                // Get data for specific month
                $query->andWhere(['EXTRACT(MONTH FROM date)' => $month]);
                
                $data = $query->orderBy('date ASC')->all();
                
                // Calculate totals for the month
                $totalRequest = 0;
                $totalGive = 0;
                foreach ($data as $item) {
                    $totalRequest += $item->request;
                    $totalGive += $item->give;
                }
                
                return $this->asJson([
                    'status' => 'ok',
                    'data' => [
                        'records' => $data,
                        'summary' => [
                            'year' => $year,
                            'month' => $month,
                            'totalRequest' => $totalRequest,
                            'totalGive' => $totalGive,
                            'count' => count($data)
                        ]
                    ]
                ]);
            } else {
                // Get monthly summary for the year
                $sql = "SELECT 
                    EXTRACT(MONTH FROM date) as month,
                    SUM(request) as totalRequest,
                    SUM(give) as totalGive,
                    COUNT(*) as count
                FROM request_give
                WHERE EXTRACT(YEAR FROM date) = :year
                GROUP BY EXTRACT(MONTH FROM date)
                ORDER BY month";
                
                $monthlyData = Yii::$app->db->createCommand($sql)
                    ->bindValue(':year', $year)
                    ->queryAll();
                
                // Get yearly totals
                $yearlyTotal = Yii::$app->db->createCommand(
                    "SELECT 
                        COALESCE(SUM(request), 0) as totalRequest,
                        COALESCE(SUM(give), 0) as totalGive,
                        COUNT(*) as count
                    FROM request_give
                    WHERE EXTRACT(YEAR FROM date) = :year"
                )
                ->bindValue(':year', $year)
                ->queryOne();
                
                // Ensure we have valid data even if empty
                if (!$yearlyTotal) {
                    $yearlyTotal = [
                        'totalRequest' => 0,
                        'totalGive' => 0,
                        'count' => 0
                    ];
                }
                
                return $this->asJson([
                    'status' => 'ok',
                    'data' => [
                        'monthlyData' => $monthlyData ?: [],
                        'yearlyTotal' => $yearlyTotal,
                        'year' => $year
                    ]
                ]);
            }
        } else {
            // Get all years summary
            $sql = "SELECT 
                EXTRACT(YEAR FROM date) as year,
                SUM(request) as totalRequest,
                SUM(give) as totalGive,
                COUNT(*) as count
            FROM request_give
            GROUP BY EXTRACT(YEAR FROM date)
            ORDER BY year DESC";
            
            $yearlyData = Yii::$app->db->createCommand($sql)->queryAll();
            
            return $this->asJson([
                'status' => 'ok',
                'data' => [
                    'yearlyData' => $yearlyData
                ]
            ]);
        }
    }

    /**
     * Get or create request/give record for a specific month
     */
    public function actionGetOrCreateMonthly()
    {
        $year = Yii::$app->request->get('year');
        $month = Yii::$app->request->get('month');
        
        if (!$year || !$month) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'Year and month are required.',
            ]);
        }
        
        // Create date for the first day of the month
        $date = sprintf('%04d-%02d-01', $year, $month);
        
        // Check if record exists for this month
        $existingRecord = RequestGive::find()
            ->where(['EXTRACT(YEAR FROM date)' => $year])
            ->andWhere(['EXTRACT(MONTH FROM date)' => $month])
            ->one();
        
        if ($existingRecord) {
            return $this->asJson([
                'status' => 'ok',
                'data' => $existingRecord,
                'isNew' => false
            ]);
        }
        
        // Create new record with default values
        $newRecord = new RequestGive();
        $newRecord->date = $date;
        $newRecord->request = 0;
        $newRecord->give = 0;
        
        return $this->asJson([
            'status' => 'ok',
            'data' => $newRecord,
            'isNew' => true
        ]);
    }
}