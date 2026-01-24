<?php

namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use app\models\MoneyDonor;
use app\models\DonarRecord;

/**
 * Command to merge duplicate money donors with similar names
 */
class MergeDonorsController extends Controller
{
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
     * Find and list potential duplicates
     */
    public function actionFind()
    {
        echo "Finding potential duplicate money donors...\n\n";

        $donors = MoneyDonor::find()->orderBy(['name' => SORT_ASC])->all();

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
            echo "No duplicates found!\n";
            return ExitCode::OK;
        }

        echo "Found " . count($duplicates) . " groups of potential duplicates:\n\n";

        foreach ($duplicates as $normalized => $group) {
            echo "Normalized name: '{$normalized}'\n";
            echo str_repeat('-', 60) . "\n";

            foreach ($group as $donor) {
                $count = $donor->getDonationCount();
                $total = $donor->getTotalAmount();
                echo sprintf(
                    "  ID: %d | Name: %s | Donations: %d | Total: %s\n",
                    $donor->id,
                    $donor->name,
                    $count,
                    number_format($total)
                );
            }
            echo "\n";
        }

        return ExitCode::OK;
    }

    /**
     * Merge duplicate donors
     * Keeps the donor with the lowest ID and merges all donation records
     */
    public function actionMerge()
    {
        echo "Merging duplicate money donors...\n\n";

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
            echo "No duplicates found!\n";
            return ExitCode::OK;
        }

        $mergedCount = 0;
        $deletedCount = 0;

        $transaction = Yii::$app->db->beginTransaction();

        try {
            foreach ($duplicates as $normalized => $group) {
                // Sort by ID to keep the lowest
                usort($group, function($a, $b) {
                    return $a->id - $b->id;
                });

                $keepDonor = $group[0]; // Keep the one with lowest ID
                $duplicateDonors = array_slice($group, 1);

                echo "Processing group: '{$normalized}'\n";
                echo "  Keeping donor ID: {$keepDonor->id} ({$keepDonor->name})\n";

                foreach ($duplicateDonors as $duplicate) {
                    // Count donations to be moved
                    $donationsCount = DonarRecord::find()
                        ->where(['money_donor_id' => $duplicate->id])
                        ->count();

                    echo "  Merging donor ID: {$duplicate->id} ({$duplicate->name}) - {$donationsCount} donations\n";

                    // Update all donation records to point to the kept donor
                    $updated = DonarRecord::updateAll(
                        ['money_donor_id' => $keepDonor->id],
                        ['money_donor_id' => $duplicate->id]
                    );

                    echo "    Updated {$updated} donation records\n";

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

                    // Delete the duplicate donor
                    if ($duplicate->delete()) {
                        echo "    Deleted duplicate donor ID: {$duplicate->id}\n";
                        $deletedCount++;
                    }

                    $mergedCount++;
                }

                // Save any merged info on the kept donor
                $keepDonor->save(false);
                echo "\n";
            }

            $transaction->commit();

            echo str_repeat('=', 60) . "\n";
            echo "Merge complete!\n";
            echo "  Merged: {$mergedCount} duplicate entries\n";
            echo "  Deleted: {$deletedCount} duplicate donor records\n";

        } catch (\Exception $e) {
            $transaction->rollBack();
            echo "Error: " . $e->getMessage() . "\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        return ExitCode::OK;
    }

    /**
     * Dry run - show what would be merged without making changes
     */
    public function actionDryRun()
    {
        echo "DRY RUN - Showing what would be merged:\n\n";

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
            echo "No duplicates found!\n";
            return ExitCode::OK;
        }

        $totalDonations = 0;
        $totalAmount = 0;

        foreach ($duplicates as $normalized => $group) {
            // Sort by ID to keep the lowest
            usort($group, function($a, $b) {
                return $a->id - $b->id;
            });

            $keepDonor = $group[0];
            $duplicateDonors = array_slice($group, 1);

            echo "Group: '{$normalized}'\n";
            echo str_repeat('-', 60) . "\n";

            $groupDonations = 0;
            $groupAmount = 0;

            foreach ($group as $donor) {
                $count = $donor->getDonationCount();
                $total = $donor->getTotalAmount();
                $groupDonations += $count;
                $groupAmount += $total;

                $marker = ($donor->id === $keepDonor->id) ? '[KEEP]' : '[DELETE]';
                echo sprintf(
                    "  %s ID: %d | Name: %s | Donations: %d | Total: %s\n",
                    $marker,
                    $donor->id,
                    $donor->name,
                    $count,
                    number_format($total)
                );
            }

            echo sprintf(
                "  MERGED: Donations: %d | Total: %s\n\n",
                $groupDonations,
                number_format($groupAmount)
            );

            $totalDonations += $groupDonations;
            $totalAmount += $groupAmount;
        }

        echo str_repeat('=', 60) . "\n";
        echo "Summary:\n";
        echo "  Groups to merge: " . count($duplicates) . "\n";
        echo "  Total donations affected: {$totalDonations}\n";
        echo "  Total amount: " . number_format($totalAmount) . "\n";

        return ExitCode::OK;
    }
}
