<?php

namespace app\controllers;

use app\models\MoneyDonor;
use app\models\DonarRecord;
use Yii;

class MoneyDonorController extends BaseApiController
{
    /**
     * List money donors with pagination and search
     */
    public function actionIndex($page = 0, $limit = 20, $q = '', $is_organization = '', $order = 'desc')
    {
        $query = MoneyDonor::find();

        // Apply search filter
        if ($q) {
            $query->andWhere(['or',
                ['ilike', 'name', $q],
                ['ilike', 'phone', $q],
                ['ilike', 'address', $q],
            ]);
        }

        // Apply organization filter
        if ($is_organization !== '') {
            $query->andWhere(['is_organization' => $is_organization === 'true' || $is_organization === '1']);
        }

        // Get total count after applying filters
        $count = $query->count();

        // Apply ordering and pagination
        $direction = strtolower($order) === 'desc' ? SORT_DESC : SORT_ASC;
        $donors = $query
            ->offset($page * $limit)
            ->limit($limit)
            ->orderBy(['id' => $direction])
            ->asArray()
            ->all();

        // Add donation stats for each donor
        foreach ($donors as &$donor) {
            $stats = DonarRecord::find()
                ->where(['money_donor_id' => $donor['id']])
                ->select(['COUNT(*) as count', 'COALESCE(SUM(amount), 0) as total'])
                ->asArray()
                ->one();

            $donor['donation_count'] = (int)($stats['count'] ?? 0);
            $donor['total_amount'] = (int)($stats['total'] ?? 0);
        }

        return $this->asJson([
            'status' => 'ok',
            'data' => $donors,
            'total' => $count,
            'page' => $page,
            'limit' => $limit,
            'hasMore' => ($page * $limit + $limit) < $count,
        ]);
    }

    /**
     * View money donor details with donation history
     */
    public function actionView($id)
    {
        $donor = MoneyDonor::find()
            ->where(['id' => $id])
            ->asArray()
            ->one();

        if ($donor === null) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'Money donor not found.',
            ]);
        }

        // Get donation history
        $donations = DonarRecord::find()
            ->where(['money_donor_id' => $id])
            ->orderBy(['date' => SORT_DESC])
            ->asArray()
            ->all();

        // Calculate stats
        $totalAmount = 0;
        foreach ($donations as $donation) {
            $totalAmount += (int)($donation['amount'] ?? 0);
        }

        $donor['donations'] = $donations;
        $donor['donation_count'] = count($donations);
        $donor['total_amount'] = $totalAmount;

        return $this->asJson([
            'status' => 'ok',
            'data' => $donor,
        ]);
    }

    /**
     * Create a new money donor
     */
    public function actionCreate()
    {
        $donor = new MoneyDonor();
        $data = Yii::$app->request->post();
        $donor->load($data, '');

        if (!$donor->save()) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'Failed to create money donor.',
                'errors' => $donor->errors,
            ]);
        }

        return $this->asJson([
            'status' => 'ok',
            'data' => $donor,
        ]);
    }

    /**
     * Update an existing money donor
     */
    public function actionUpdate($id)
    {
        $donor = MoneyDonor::findOne($id);
        if ($donor === null) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'Money donor not found.',
            ]);
        }

        $data = Yii::$app->request->post();
        $donor->load($data, '');

        if (!$donor->save()) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'Failed to update money donor.',
                'errors' => $donor->errors,
            ]);
        }

        return $this->asJson([
            'status' => 'ok',
            'data' => $donor,
        ]);
    }

    /**
     * Delete a money donor
     */
    public function actionDelete($id)
    {
        $donor = MoneyDonor::findOne($id);
        if ($donor === null) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'Money donor not found.',
            ]);
        }

        // Check if donor has records
        $recordCount = DonarRecord::find()->where(['money_donor_id' => $id])->count();
        if ($recordCount > 0) {
            // Unlink donor from records instead of preventing delete
            DonarRecord::updateAll(['money_donor_id' => null], ['money_donor_id' => $id]);
        }

        if (!$donor->delete()) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'Failed to delete money donor.',
            ]);
        }

        return $this->asJson([
            'status' => 'ok',
            'message' => 'Money donor deleted successfully.',
        ]);
    }

    /**
     * Search money donors for dropdown
     */
    public function actionSearch($q = '', $limit = 20)
    {
        $query = MoneyDonor::find()
            ->select(['id', 'name', 'phone', 'address', 'is_organization']);

        if ($q) {
            $query->andWhere(['or',
                ['ilike', 'name', $q],
                ['ilike', 'phone', $q],
            ]);
        }

        $donors = $query
            ->limit($limit)
            ->orderBy(['name' => SORT_ASC])
            ->asArray()
            ->all();

        return $this->asJson([
            'status' => 'ok',
            'data' => $donors,
        ]);
    }

    /**
     * Get donation report for a specific donor
     */
    public function actionReport($id)
    {
        $donor = MoneyDonor::findOne($id);
        if ($donor === null) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'Money donor not found.',
            ]);
        }

        // Get all donations
        $donations = DonarRecord::find()
            ->where(['money_donor_id' => $id])
            ->orderBy(['date' => SORT_DESC])
            ->asArray()
            ->all();

        // Calculate yearly summary
        $yearlyStats = [];
        $totalAmount = 0;
        foreach ($donations as $donation) {
            $year = date('Y', strtotime($donation['date']));
            if (!isset($yearlyStats[$year])) {
                $yearlyStats[$year] = ['year' => $year, 'count' => 0, 'total' => 0];
            }
            $yearlyStats[$year]['count']++;
            $yearlyStats[$year]['total'] += (int)($donation['amount'] ?? 0);
            $totalAmount += (int)($donation['amount'] ?? 0);
        }

        // Sort years descending
        krsort($yearlyStats);

        return $this->asJson([
            'status' => 'ok',
            'data' => [
                'donor' => $donor,
                'donations' => $donations,
                'yearly_stats' => array_values($yearlyStats),
                'total_donations' => count($donations),
                'total_amount' => $totalAmount,
            ],
        ]);
    }
}
