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
        // Build query with LEFT JOIN to get donation stats
        $query = MoneyDonor::find()
            ->select([
                'money_donor.*',
                'COUNT(donar_record.id) as donation_count',
                'COALESCE(SUM(donar_record.amount), 0) as total_amount'
            ])
            ->leftJoin('donar_record', 'donar_record.money_donor_id = money_donor.id')
            ->groupBy('money_donor.id');

        // Apply search filter
        if ($q) {
            $query->andWhere(['or',
                ['ilike', 'money_donor.name', $q],
                ['ilike', 'money_donor.phone', $q],
                ['ilike', 'money_donor.address', $q],
            ]);
        }

        // Apply organization filter
        if ($is_organization !== '') {
            $query->andWhere(['money_donor.is_organization' => $is_organization === 'true' || $is_organization === '1']);
        }

        // Get total count after applying filters
        // Clone query for count to avoid interference with main query
        $countQuery = clone $query;
        $count = $countQuery->count();

        // Sort by total_amount DESC, then donation_count DESC (largest first)
        $donors = $query
            ->offset($page * $limit)
            ->limit($limit)
            ->orderBy([
                'total_amount' => SORT_DESC,
                'donation_count' => SORT_DESC,
                'money_donor.id' => SORT_ASC
            ])
            ->asArray()
            ->all();

        // Convert aggregated values to integers
        foreach ($donors as &$donor) {
            $donor['donation_count'] = (int)($donor['donation_count'] ?? 0);
            $donor['total_amount'] = (int)($donor['total_amount'] ?? 0);
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

        // Set default owner_id if not provided
        if (empty($donor->owner_id)) {
            $donor->owner_id = 'system';
        }

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
     * Extract base name for fuzzy matching
     * Removes common suffixes like "မိသားစု", "နှင့် ဇနီး", spaces after +
     */
    private function extractBaseName($name)
    {
        // Remove common suffixes
        $suffixes = ['မိသားစု', 'နှင့်ဇနီး', 'နှင့် ဇနီး', 'မိသားစု'];
        foreach ($suffixes as $suffix) {
            $name = str_replace($suffix, '', $name);
        }

        // Normalize spaces around + symbol
        $name = preg_replace('/\s*\+\s*/', '+', $name);

        // Remove parentheses
        $name = str_replace(['(', ')'], '', $name);

        // Remove extra whitespace
        $name = preg_replace('/\s+/', ' ', $name);

        return trim($name);
    }

    /**
     * Calculate similarity between two names
     */
    private function nameSimilarity($name1, $name2)
    {
        $base1 = $this->extractBaseName($name1);
        $base2 = $this->extractBaseName($name2);

        // Check if one starts with the other (covers cases like truncation)
        if (mb_strpos($base1, $base2) === 0 || mb_strpos($base2, $base1) === 0) {
            return 0.95;
        }

        // Check if names contain each other
        if (mb_strpos($base1, $base2) !== false || mb_strpos($base2, $base1) !== false) {
            return 0.85;
        }

        // Calculate character similarity using similar_text
        similar_text($base1, $base2, $percent);
        return $percent / 100;
    }

    /**
     * Find potential duplicate donors with fuzzy matching
     */
    public function actionFindDuplicates($threshold = 0.75)
    {
        $donors = MoneyDonor::find()->orderBy(['id' => SORT_ASC])->all();
        $donorCount = count($donors);

        // Find similar pairs using fuzzy matching
        $processed = [];
        $duplicateGroups = [];

        for ($i = 0; $i < $donorCount; $i++) {
            if (isset($processed[$donors[$i]->id])) continue;

            $group = [$donors[$i]];
            $processed[$donors[$i]->id] = true;

            for ($j = $i + 1; $j < $donorCount; $j++) {
                if (isset($processed[$donors[$j]->id])) continue;

                $similarity = $this->nameSimilarity($donors[$i]->name, $donors[$j]->name);

                if ($similarity >= $threshold) {
                    $group[] = $donors[$j];
                    $processed[$donors[$j]->id] = true;
                }
            }

            if (count($group) > 1) {
                $duplicateGroups[] = $group;
            }
        }

        $result = [];
        foreach ($duplicateGroups as $group) {
            $groupData = [
                'donors' => [],
                'similarity' => round($this->nameSimilarity($group[0]->name, $group[1]->name) * 100, 1),
            ];

            foreach ($group as $donor) {
                $stats = DonarRecord::find()
                    ->where(['money_donor_id' => $donor->id])
                    ->select(['COUNT(*) as count', 'COALESCE(SUM(amount), 0) as total'])
                    ->asArray()
                    ->one();

                $groupData['donors'][] = [
                    'id' => $donor->id,
                    'name' => $donor->name,
                    'phone' => $donor->phone,
                    'address' => $donor->address,
                    'donation_count' => (int)($stats['count'] ?? 0),
                    'total_amount' => (int)($stats['total'] ?? 0),
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
     * Merge specific donors by IDs
     * @param string $ids Comma-separated IDs to merge
     * @param int $keepId The ID of the donor to keep (name will be preserved from this donor)
     */
    public function actionMergeSpecific($ids, $keepId = null)
    {
        $idArray = array_map('intval', explode(',', $ids));

        if (count($idArray) < 2) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'At least 2 donor IDs required.',
            ]);
        }

        // Get all donors
        $donors = MoneyDonor::find()
            ->where(['id' => $idArray])
            ->orderBy(['id' => SORT_ASC])
            ->all();

        if (count($donors) < 2) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'Could not find all specified donors.',
            ]);
        }

        $transaction = Yii::$app->db->beginTransaction();

        try {
            // Determine which donor to keep
            $keepDonor = null;
            $duplicateDonors = [];

            if ($keepId !== null) {
                // Find the donor with the specified keepId
                foreach ($donors as $donor) {
                    if ($donor->id == $keepId) {
                        $keepDonor = $donor;
                    } else {
                        $duplicateDonors[] = $donor;
                    }
                }

                if ($keepDonor === null) {
                    return $this->asJson([
                        'status' => 'error',
                        'message' => 'Keep ID not found in the provided IDs.',
                    ]);
                }
            } else {
                // Default: keep the first donor (lowest ID)
                $keepDonor = $donors[0];
                $duplicateDonors = array_slice($donors, 1);
            }

            $mergedRecords = 0;
            $deletedDonors = [];

            foreach ($duplicateDonors as $duplicate) {
                // Move donation records
                $updated = DonarRecord::updateAll(
                    ['money_donor_id' => $keepDonor->id],
                    ['money_donor_id' => $duplicate->id]
                );
                $mergedRecords += $updated;

                // Merge any missing info
                if (empty($keepDonor->phone) && !empty($duplicate->phone)) {
                    $keepDonor->phone = $duplicate->phone;
                }
                if (empty($keepDonor->address) && !empty($duplicate->address)) {
                    $keepDonor->address = $duplicate->address;
                }
                if (empty($keepDonor->note) && !empty($duplicate->note)) {
                    $keepDonor->note = $duplicate->note;
                }

                $deletedDonors[] = [
                    'id' => $duplicate->id,
                    'name' => $duplicate->name,
                    'records_moved' => $updated,
                ];

                // Delete the duplicate
                $duplicate->delete();
            }

            // Save any merged info
            $keepDonor->save(false);

            $transaction->commit();

            // Get updated stats
            $stats = DonarRecord::find()
                ->where(['money_donor_id' => $keepDonor->id])
                ->select(['COUNT(*) as count', 'COALESCE(SUM(amount), 0) as total'])
                ->asArray()
                ->one();

            return $this->asJson([
                'status' => 'ok',
                'message' => 'Merge successful.',
                'kept_donor' => [
                    'id' => $keepDonor->id,
                    'name' => $keepDonor->name,
                    'donation_count' => (int)($stats['count'] ?? 0),
                    'total_amount' => (int)($stats['total'] ?? 0),
                ],
                'deleted_donors' => $deletedDonors,
                'total_records_merged' => $mergedRecords,
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
