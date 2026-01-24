<?php

namespace app\commands;

use app\models\Patient;
use app\models\Donation;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Migrates patient data from the donation table to the new patient table.
 * Deduplication: Patients with the same name, gender, and similar township/address are merged.
 */
class MigratePatientDataController extends Controller
{
    /**
     * Migrate patient data from donation table to patient table
     * Usage: php yii migrate-patient-data
     */
    public function actionIndex()
    {
        ini_set('memory_limit', '1024M');

        $this->stdout("Starting patient data migration...\n", Console::FG_CYAN);

        $transaction = Yii::$app->db->beginTransaction();
        try {
            // Get unique patients from donation table
            $sql = "
                SELECT DISTINCT
                    patient_name,
                    patient_age,
                    patient_address,
                    patient_disease,
                    owner_id
                FROM donation
                WHERE patient_name IS NOT NULL
                AND patient_name != ''
                ORDER BY patient_name
            ";

            $donations = Yii::$app->db->createCommand($sql)->queryAll();
            $this->stdout("Found " . count($donations) . " donation records with patient names\n", Console::FG_YELLOW);

            // Group by patient name and address for deduplication
            $patientGroups = [];
            foreach ($donations as $donation) {
                $name = trim($donation['patient_name']);
                $address = trim($donation['patient_address'] ?? '');

                // Extract township/district from address for matching
                $township = $this->extractTownship($address);

                // Create a deduplication key based on name and township
                $key = strtolower($name) . '|' . strtolower($township);

                if (!isset($patientGroups[$key])) {
                    $patientGroups[$key] = [
                        'name' => $name,
                        'address' => $address,
                        'age' => $donation['patient_age'],
                        'owner_id' => $donation['owner_id'] ?? '1',
                        'diseases' => [],
                    ];
                }

                // Collect all diseases for medical notes
                if (!empty($donation['patient_disease'])) {
                    $patientGroups[$key]['diseases'][] = $donation['patient_disease'];
                }

                // Keep the most complete address
                if (strlen($address) > strlen($patientGroups[$key]['address'])) {
                    $patientGroups[$key]['address'] = $address;
                }
            }

            $this->stdout("After deduplication: " . count($patientGroups) . " unique patients\n", Console::FG_GREEN);

            // Insert patients
            $insertedCount = 0;
            $patientNameToId = [];

            foreach ($patientGroups as $key => $patientData) {
                $patient = new Patient();
                $patient->name = $patientData['name'];
                $patient->address = $patientData['address'];
                $patient->age = $patientData['age'];
                $patient->owner_id = $patientData['owner_id'];

                // Create medical notes from diseases
                $uniqueDiseases = array_unique($patientData['diseases']);
                if (!empty($uniqueDiseases)) {
                    $patient->medical_notes = implode(', ', $uniqueDiseases);
                }

                if ($patient->save()) {
                    $insertedCount++;
                    $patientNameToId[$key] = $patient->id;

                    if ($insertedCount % 500 === 0) {
                        $this->stdout("Inserted $insertedCount patients...\n", Console::FG_YELLOW);
                    }
                } else {
                    $this->stderr("Failed to save patient: " . $patientData['name'] . "\n", Console::FG_RED);
                    $this->stderr(print_r($patient->errors, true), Console::FG_RED);
                }
            }

            $this->stdout("Inserted $insertedCount patients\n", Console::FG_GREEN);

            // Update donation.patient_id references
            $this->stdout("Updating donation patient_id references...\n", Console::FG_CYAN);

            $updatedCount = 0;
            $donations = Donation::find()
                ->where(['not', ['patient_name' => null]])
                ->andWhere(['not', ['patient_name' => '']])
                ->all();

            foreach ($donations as $donation) {
                $name = trim($donation->patient_name);
                $address = trim($donation->patient_address ?? '');
                $township = $this->extractTownship($address);
                $key = strtolower($name) . '|' . strtolower($township);

                if (isset($patientNameToId[$key])) {
                    $donation->patient_id = $patientNameToId[$key];
                    if ($donation->save(false)) {
                        $updatedCount++;
                        if ($updatedCount % 500 === 0) {
                            $this->stdout("Updated $updatedCount donations...\n", Console::FG_YELLOW);
                        }
                    }
                }
            }

            $this->stdout("Updated $updatedCount donations with patient_id\n", Console::FG_GREEN);

            $transaction->commit();
            $this->stdout("\nPatient migration completed successfully!\n", Console::FG_GREEN);
            $this->stdout("Summary:\n", Console::FG_CYAN);
            $this->stdout("  - Patients created: $insertedCount\n");
            $this->stdout("  - Donations linked: $updatedCount\n");

            return ExitCode::OK;
        } catch (\Exception $e) {
            $transaction->rollBack();
            $this->stderr("Error during migration: " . $e->getMessage() . "\n", Console::FG_RED);
            $this->stderr($e->getTraceAsString() . "\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    /**
     * Extract township/district from address for better matching
     */
    protected function extractTownship($address)
    {
        if (empty($address)) {
            return '';
        }

        // Common Myanmar township patterns
        $patterns = [
            '/မြို့နယ်/',
            '/Township/i',
            '/Tsp\./i',
            '/\(([^)]+)\)/', // Text in parentheses often indicates township
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $address, $matches)) {
                return isset($matches[1]) ? $matches[1] : $matches[0];
            }
        }

        // If no township found, return last part of address (often township)
        $parts = preg_split('/[,၊]/', $address);
        if (count($parts) > 1) {
            return trim(end($parts));
        }

        return $address;
    }

    /**
     * Preview migration without making changes
     * Usage: php yii migrate-patient-data/preview
     */
    public function actionPreview()
    {
        $this->stdout("Patient Migration Preview\n", Console::FG_CYAN);
        $this->stdout("=========================\n\n");

        // Count unique patient names
        $uniqueNames = Yii::$app->db->createCommand("
            SELECT COUNT(DISTINCT patient_name) as count
            FROM donation
            WHERE patient_name IS NOT NULL AND patient_name != ''
        ")->queryScalar();

        // Get sample of duplicates
        $duplicates = Yii::$app->db->createCommand("
            SELECT patient_name, COUNT(*) as count
            FROM donation
            WHERE patient_name IS NOT NULL AND patient_name != ''
            GROUP BY patient_name
            HAVING COUNT(*) > 1
            ORDER BY count DESC
            LIMIT 10
        ")->queryAll();

        $this->stdout("Unique patient names in donation table: $uniqueNames\n\n");

        $this->stdout("Top 10 patients with multiple donations:\n", Console::FG_YELLOW);
        foreach ($duplicates as $dup) {
            $this->stdout("  - {$dup['patient_name']}: {$dup['count']} donations\n");
        }

        return ExitCode::OK;
    }

    /**
     * Rollback migration - clear patient table and unset patient_id in donations
     * Usage: php yii migrate-patient-data/rollback
     */
    public function actionRollback()
    {
        $this->stdout("Rolling back patient migration...\n", Console::FG_YELLOW);

        $confirm = $this->confirm("This will delete all patients and unlink donations. Continue?");
        if (!$confirm) {
            $this->stdout("Rollback cancelled.\n");
            return ExitCode::OK;
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            // Unset patient_id in donations
            Donation::updateAll(['patient_id' => null]);
            $this->stdout("Unset patient_id in all donations\n", Console::FG_GREEN);

            // Delete all patients
            Patient::deleteAll();
            $this->stdout("Deleted all patients\n", Console::FG_GREEN);

            // Reset sequence
            Yii::$app->db->createCommand("ALTER SEQUENCE patient_id_seq RESTART WITH 1")->execute();

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
