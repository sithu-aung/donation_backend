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
    public function actionIndex($page = 0, $limit = 20, $q = '', $is_organization = '', $order = 'asc')
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
     * Normalize a donor name for comparison
     * - Remove parentheses and replace with space
     * - Remove extra whitespace
     * - Trim
     */
    private function normalizeName($name)
    {
        // Replace parentheses with space
        $name = str_replace(['(', ')'], ' ', $name);
        // Replace multiple spaces with single space
        $name = preg_replace('/\s+/', ' ', $name);
        // Trim
        return trim($name);
    }

    /**
     * Find potential duplicate donors
     */
    public function actionFindDuplicates()
    {
        $donors = MoneyDonor::find()->orderBy(['id' => SORT_ASC])->all();

        // Group by normalized name
        $groups = [];
        foreach ($donors as $donor) {
            $normalized = $this->normalizeName($donor->name);
            if (!isset($groups[$normalized])) {
                $groups[$normalized] = [];
            }
            $groups[$normalized][] = $donor;
        }

        // Find groups with more than one donor
        $duplicates = array_filter($groups, function($group) {
            return count($group) > 1;
        });

        $result = [];
        foreach ($duplicates as $normalized => $group) {
            $groupData = [
                'normalized_name' => $normalized,
                'donors' => [],
            ];

            foreach ($group as $donor) {
                $count = $donor->getDonationCount();
                $total = $donor->getTotalAmount();
                $groupData['donors'][] = [
                    'id' => $donor->id,
                    'name' => $donor->name,
                    'phone' => $donor->phone,
                    'address' => $donor->address,
                    'donation_count' => $count,
                    'total_amount' => $total,
                ];
            }

            $result[] = $groupData;
        }

        return $this->asJson([
            'status' => 'ok',
            'data' => $result,
            'count' => count($result),
        ]);
    }

    /**
     * Merge duplicate donors
     * Keeps the donor with the lowest ID and merges all donation records
     */
    public function actionMergeDuplicates()
    {
        $donors = MoneyDonor::find()->orderBy(['id' => SORT_ASC])->all();

        // Group by normalized name
        $groups = [];
        foreach ($donors as $donor) {
            $normalized = $this->normalizeName($donor->name);
            if (!isset($groups[$normalized])) {
                $groups[$normalized] = [];
            }
            $groups[$normalized][] = $donor;
        }

        // Find groups with more than one donor
        $duplicates = array_filter($groups, function($group) {
            return count($group) > 1;
        });

        if (empty($duplicates)) {
            return $this->asJson([
                'status' => 'ok',
                'message' => 'No duplicates found.',
                'merged' => 0,
                'deleted' => 0,
            ]);
        }

        $mergedCount = 0;
        $deletedCount = 0;
        $mergeLog = [];

        $transaction = Yii::$app->db->beginTransaction();

        try {
            foreach ($duplicates as $normalized => $group) {
                // Sort by ID to keep the lowest
                usort($group, function($a, $b) {
                    return $a->id - $b->id;
                });

                $keepDonor = $group[0]; // Keep the one with lowest ID
                $duplicateDonors = array_slice($group, 1);

                $logEntry = [
                    'kept_id' => $keepDonor->id,
                    'kept_name' => $keepDonor->name,
                    'merged_from' => [],
                ];

                foreach ($duplicateDonors as $duplicate) {
                    // Count donations to be moved
                    $donationsCount = DonarRecord::find()
                        ->where(['money_donor_id' => $duplicate->id])
                        ->count();

                    // Update all donation records to point to the kept donor
                    $updated = DonarRecord::updateAll(
                        ['money_donor_id' => $keepDonor->id],
                        ['money_donor_id' => $duplicate->id]
                    );

                    // Merge any additional info if the kept donor is missing it
                    if (empty($keepDonor->phone) && !empty($duplicate->phone)) {
                        $keepDonor->phone = $duplicate->phone;
                    }
                    if (empty($keepDonor->address) && !empty($duplicate->address)) {
                        $keepDonor->address = $duplicate->address;
                    }
                    if (empty($keepDonor->note) && !empty($duplicate->note)) {
                        $keepDonor->note = $duplicate->note;
                    }

                    $logEntry['merged_from'][] = [
                        'id' => $duplicate->id,
                        'name' => $duplicate->name,
                        'donations_moved' => $updated,
                    ];

                    // Delete the duplicate donor
                    $duplicate->delete();
                    $deletedCount++;
                    $mergedCount++;
                }

                // Save any merged info on the kept donor
                $keepDonor->save(false);
                $mergeLog[] = $logEntry;
            }

            $transaction->commit();

            return $this->asJson([
                'status' => 'ok',
                'message' => 'Merge complete.',
                'merged' => $mergedCount,
                'deleted' => $deletedCount,
                'log' => $mergeLog,
            ]);

        } catch (\Exception $e) {
            $transaction->rollBack();
            return $this->asJson([
                'status' => 'error',
                'message' => 'Merge failed: ' . $e->getMessage(),
            ]);
        }
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
