<?php

namespace app\commands;

use yii\console\Controller;
use app\models\MoneyDonor;
use app\models\DonarRecord;
use Yii;

/**
 * Console command to merge duplicate money donors
 */
class MergeController extends Controller
{
    /**
     * Extract base name for fuzzy matching
     */
    private function extractBaseName($name)
    {
        $suffixes = ['မိသားစု', 'နှင့်ဇနီး', 'နှင့် ဇနီး'];
        foreach ($suffixes as $suffix) {
            $name = str_replace($suffix, '', $name);
        }
        $name = preg_replace('/\s*\+\s*/', '+', $name);
        $name = str_replace(['(', ')'], '', $name);
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

        if (mb_strpos($base1, $base2) === 0 || mb_strpos($base2, $base1) === 0) {
            return 0.95;
        }
        if (mb_strpos($base1, $base2) !== false || mb_strpos($base2, $base1) !== false) {
            return 0.85;
        }
        similar_text($base1, $base2, $percent);
        return $percent / 100;
    }

    /**
     * Merge all duplicates, keeping the longest name for each group
     */
    public function actionMergeAll($threshold = 0.75)
    {
        $donors = MoneyDonor::find()->orderBy(['id' => SORT_ASC])->all();
        $donorCount = count($donors);

        echo "Found $donorCount donors\n";
        echo "Finding duplicates with threshold: $threshold\n\n";

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

        echo "Found " . count($duplicateGroups) . " duplicate groups\n\n";

        if (empty($duplicateGroups)) {
            echo "No duplicates to merge.\n";
            return;
        }

        $totalMerged = 0;
        $totalDeleted = 0;

        foreach ($duplicateGroups as $index => $group) {
            // Find the donor with the longest name
            $longestNameDonor = $group[0];
            foreach ($group as $donor) {
                if (mb_strlen($donor->name) > mb_strlen($longestNameDonor->name)) {
                    $longestNameDonor = $donor;
                }
            }

            // Get stats for display
            $groupNames = array_map(function($d) { return $d->name . " (ID: " . $d->id . ")"; }, $group);
            $keepingName = $longestNameDonor->name . " (ID: " . $longestNameDonor->id . ")";

            echo "Group " . ($index + 1) . ":\n";
            echo "  Donors: " . implode(", ", $groupNames) . "\n";
            echo "  Keeping: $keepingName\n";

            $transaction = Yii::$app->db->beginTransaction();

            try {
                $mergedRecords = 0;
                $deletedDonors = [];

                foreach ($group as $donor) {
                    if ($donor->id == $longestNameDonor->id) continue;

                    // Move donation records
                    $updated = DonarRecord::updateAll(
                        ['money_donor_id' => $longestNameDonor->id],
                        ['money_donor_id' => $donor->id]
                    );
                    $mergedRecords += $updated;

                    // Merge missing info
                    if (empty($longestNameDonor->phone) && !empty($donor->phone)) {
                        $longestNameDonor->phone = $donor->phone;
                    }
                    if (empty($longestNameDonor->address) && !empty($donor->address)) {
                        $longestNameDonor->address = $donor->address;
                    }
                    if (empty($longestNameDonor->note) && !empty($donor->note)) {
                        $longestNameDonor->note = $donor->note;
                    }

                    $deletedDonors[] = $donor->name;
                    $donor->delete();
                    $totalDeleted++;
                }

                $longestNameDonor->save(false);
                $transaction->commit();

                $totalMerged += $mergedRecords;
                echo "  Merged $mergedRecords records, deleted " . count($deletedDonors) . " duplicate donors\n\n";

            } catch (\Exception $e) {
                $transaction->rollBack();
                echo "  ERROR: " . $e->getMessage() . "\n\n";
            }
        }

        echo "=== SUMMARY ===\n";
        echo "Total groups merged: " . count($duplicateGroups) . "\n";
        echo "Total records moved: $totalMerged\n";
        echo "Total donors deleted: $totalDeleted\n";
    }
}
