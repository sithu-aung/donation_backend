<?php

namespace app\commands;

use app\models\MoneyDonor;
use app\models\DonarRecord;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Migrates money donor data from the donar_record table to the new money_donor table.
 * Deduplication: Donors with similar names (format variations) are merged.
 */
class MigrateMoneyDonorDataController extends Controller
{
    /**
     * Migrate money donor data from donar_record table to money_donor table
     * Usage: php yii migrate-money-donor-data
     */
    public function actionIndex()
    {
        ini_set('memory_limit', '1024M');

        $this->stdout("Starting money donor data migration...\n", Console::FG_CYAN);

        $transaction = Yii::$app->db->beginTransaction();
        try {
            // Get unique donor names from donar_record table
            $sql = "
                SELECT DISTINCT name
                FROM donar_record
                WHERE name IS NOT NULL
                AND name != ''
                ORDER BY name
            ";

            $donorNames = Yii::$app->db->createCommand($sql)->queryColumn();
            $this->stdout("Found " . count($donorNames) . " unique donor names\n", Console::FG_YELLOW);

            // Group by normalized name for deduplication
            $donorGroups = [];
            foreach ($donorNames as $name) {
                $normalizedName = $this->normalizeName($name);

                if (!isset($donorGroups[$normalizedName])) {
                    $donorGroups[$normalizedName] = [
                        'display_name' => $name,
                        'original_names' => [],
                        'is_organization' => $this->isOrganization($name),
                    ];
                }

                $donorGroups[$normalizedName]['original_names'][] = $name;

                // Keep the longest/most complete name as display name
                if (strlen($name) > strlen($donorGroups[$normalizedName]['display_name'])) {
                    $donorGroups[$normalizedName]['display_name'] = $name;
                }
            }

            $this->stdout("After deduplication: " . count($donorGroups) . " unique donors\n", Console::FG_GREEN);

            // Insert money donors
            $insertedCount = 0;
            $nameToId = []; // Map original names to donor IDs

            foreach ($donorGroups as $normalizedName => $donorData) {
                $donor = new MoneyDonor();
                $donor->name = $donorData['display_name'];
                $donor->is_organization = $donorData['is_organization'];
                $donor->owner_id = '1'; // Default owner

                // If there were multiple name variations, add as note
                if (count($donorData['original_names']) > 1) {
                    $donor->note = 'Also known as: ' . implode(', ', array_unique($donorData['original_names']));
                }

                if ($donor->save()) {
                    $insertedCount++;

                    // Map all original names to this donor ID
                    foreach ($donorData['original_names'] as $originalName) {
                        $nameToId[$originalName] = $donor->id;
                    }

                    if ($insertedCount % 100 === 0) {
                        $this->stdout("Inserted $insertedCount donors...\n", Console::FG_YELLOW);
                    }
                } else {
                    $this->stderr("Failed to save donor: " . $donorData['display_name'] . "\n", Console::FG_RED);
                    $this->stderr(print_r($donor->errors, true), Console::FG_RED);
                }
            }

            $this->stdout("Inserted $insertedCount money donors\n", Console::FG_GREEN);

            // Update donar_record.money_donor_id references
            $this->stdout("Updating donar_record money_donor_id references...\n", Console::FG_CYAN);

            $updatedCount = 0;
            $records = DonarRecord::find()
                ->where(['not', ['name' => null]])
                ->andWhere(['not', ['name' => '']])
                ->all();

            foreach ($records as $record) {
                $name = $record->name;

                if (isset($nameToId[$name])) {
                    $record->money_donor_id = $nameToId[$name];
                    if ($record->save(false)) {
                        $updatedCount++;
                        if ($updatedCount % 100 === 0) {
                            $this->stdout("Updated $updatedCount records...\n", Console::FG_YELLOW);
                        }
                    }
                }
            }

            $this->stdout("Updated $updatedCount donar_records with money_donor_id\n", Console::FG_GREEN);

            $transaction->commit();
            $this->stdout("\nMoney donor migration completed successfully!\n", Console::FG_GREEN);
            $this->stdout("Summary:\n", Console::FG_CYAN);
            $this->stdout("  - Money donors created: $insertedCount\n");
            $this->stdout("  - Records linked: $updatedCount\n");

            return ExitCode::OK;
        } catch (\Exception $e) {
            $transaction->rollBack();
            $this->stderr("Error during migration: " . $e->getMessage() . "\n", Console::FG_RED);
            $this->stderr($e->getTraceAsString() . "\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    /**
     * Normalize name for deduplication
     */
    protected function normalizeName($name)
    {
        // Convert to lowercase
        $normalized = mb_strtolower($name, 'UTF-8');

        // Only remove honorific prefixes (u/ဦး = Mr., daw/ဒေါ် = Mrs.)
        // Keep gender prefixes: ko (ကို) and ma (မ) as they indicate different people
        $normalized = preg_replace('/^(u |ဦး|daw |ဒေါ် |dr\.? |ဒေါက်တာ )/iu', '', $normalized);

        // Remove extra whitespace
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        $normalized = trim($normalized);

        // Remove special characters except Myanmar characters
        $normalized = preg_replace('/[^\p{L}\p{N}\s]/u', '', $normalized);

        return $normalized;
    }

    /**
     * Check if name appears to be an organization
     */
    protected function isOrganization($name)
    {
        $orgPatterns = [
            '/company/i',
            '/co\.,? ?ltd/i',
            '/corporation/i',
            '/foundation/i',
            '/organization/i',
            '/association/i',
            '/group/i',
            '/club/i',
            '/society/i',
            '/committee/i',
            '/ကုမ္ပဏီ/',
            '/အသင်း/',
            '/ဖောင်ဒေးရှင်း/',
            '/အဖွဲ့/',
        ];

        foreach ($orgPatterns as $pattern) {
            if (preg_match($pattern, $name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Preview migration without making changes
     * Usage: php yii migrate-money-donor-data/preview
     */
    public function actionPreview()
    {
        $this->stdout("Money Donor Migration Preview\n", Console::FG_CYAN);
        $this->stdout("=============================\n\n");

        // Count unique donor names
        $uniqueNames = Yii::$app->db->createCommand("
            SELECT COUNT(DISTINCT name) as count
            FROM donar_record
            WHERE name IS NOT NULL AND name != ''
        ")->queryScalar();

        // Get top donors by donation count
        $topDonors = Yii::$app->db->createCommand("
            SELECT name, COUNT(*) as count, SUM(amount) as total
            FROM donar_record
            WHERE name IS NOT NULL AND name != ''
            GROUP BY name
            ORDER BY total DESC
            LIMIT 10
        ")->queryAll();

        $this->stdout("Unique donor names in donar_record table: $uniqueNames\n\n");

        $this->stdout("Top 10 donors by total amount:\n", Console::FG_YELLOW);
        foreach ($topDonors as $donor) {
            $this->stdout("  - {$donor['name']}: {$donor['count']} donations, total: {$donor['total']}\n");
        }

        return ExitCode::OK;
    }

    /**
     * Rollback migration - clear money_donor table and unset money_donor_id in donar_records
     * Usage: php yii migrate-money-donor-data/rollback
     */
    public function actionRollback()
    {
        $this->stdout("Rolling back money donor migration...\n", Console::FG_YELLOW);

        $confirm = $this->confirm("This will delete all money donors and unlink records. Continue?");
        if (!$confirm) {
            $this->stdout("Rollback cancelled.\n");
            return ExitCode::OK;
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            // Unset money_donor_id in donar_records
            DonarRecord::updateAll(['money_donor_id' => null]);
            $this->stdout("Unset money_donor_id in all donar_records\n", Console::FG_GREEN);

            // Delete all money donors
            MoneyDonor::deleteAll();
            $this->stdout("Deleted all money donors\n", Console::FG_GREEN);

            // Reset sequence
            Yii::$app->db->createCommand("ALTER SEQUENCE money_donor_id_seq RESTART WITH 1")->execute();

            $transaction->commit();
            $this->stdout("Rollback completed successfully!\n", Console::FG_GREEN);

            return ExitCode::OK;
        } catch (\Exception $e) {
            $transaction->rollBack();
            $this->stderr("Error during rollback: " . $e->getMessage() . "\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }
}
