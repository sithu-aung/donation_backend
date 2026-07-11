<?php

namespace app\controllers;

use app\models\Patient;
use app\models\Donation;
use Yii;

class PatientController extends BaseApiController
{
    /**
     * List patients with pagination and search
     */
    public function actionIndex($page = 0, $limit = 20, $q = '', $gender = '', $order = 'asc')
    {
        $query = Patient::find();

        // Apply search filter
        if ($q) {
            $query->andWhere(['or',
                ['ilike', 'name', $q],
                ['ilike', 'phone', $q],
                ['ilike', 'address', $q],
            ]);
        }

        // Apply gender filter
        if ($gender) {
            $query->andWhere(['gender' => $gender]);
        }

        // Get total count after applying filters
        $count = $query->count();

        // Get distinct genders for filter dropdown
        $genders = Patient::find()
            ->select('gender')
            ->distinct()
            ->where(['not', ['gender' => null]])
            ->andWhere(['not', ['gender' => '']])
            ->column();

        // Apply ordering and pagination
        $direction = strtolower($order) === 'desc' ? SORT_DESC : SORT_ASC;
        $patients = $query
            ->offset($page * $limit)
            ->limit($limit)
            ->orderBy(['id' => $direction])
            ->asArray()
            ->all();

        // Add donation count for each patient
        foreach ($patients as &$patient) {
            $patient['donation_count'] = Donation::find()
                ->where(['patient_id' => $patient['id']])
                ->count();
        }

        return $this->asJson([
            'status' => 'ok',
            'data' => $patients,
            'total' => $count,
            'genders' => $genders,
            'page' => $page,
            'limit' => $limit,
            'hasMore' => ($page * $limit + $limit) < $count,
        ]);
    }

    /**
     * View patient details with donation history
     */
    public function actionView($id)
    {
        $patient = Patient::find()
            ->where(['id' => $id])
            ->asArray()
            ->one();

        if ($patient === null) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'Patient not found.',
            ]);
        }

        // Get donation history
        $donations = Donation::find()
            ->with(['member0' => function($query) {
                $query->select(['id', 'name', 'blood_type', 'phone']);
            }])
            ->where(['patient_id' => $id])
            ->orderBy(['donation_date' => SORT_ASC, 'id' => SORT_ASC])
            ->asArray()
            ->all();

        // Map member data
        foreach ($donations as &$donation) {
            if (isset($donation['member0'])) {
                $donation['memberObj'] = $donation['member0'];
            }
        }

        $patient['donations'] = $donations;
        $patient['donation_count'] = count($donations);

        return $this->asJson([
            'status' => 'ok',
            'data' => $patient,
        ]);
    }

    /**
     * Create a new patient
     */
    public function actionCreate()
    {
        $patient = new Patient();
        $data = Yii::$app->request->post();
        $patient->load($data, '');

        // Auto-set owner_id if not provided
        if (empty($patient->owner_id)) {
            $patient->owner_id = $data['owner_id'] ?? 'system';
        }

        // Potential-match guard: warn only when another patient matches on ALL
        // of name, blood type, township and ward/village. A same-name neighbour
        // with a different blood group is a different person and must not
        // trigger the warning. The client shows every candidate and may resend
        // with force=1 after staff confirm this really is a different person.
        $force = filter_var($data['force'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if (!$force) {
            $matches = $this->findPotentialMatches(
                $patient->name,
                $patient->blood_type,
                $patient->township,
                $patient->ward,
                $patient->village
            );
            if (!empty($matches)) {
                return $this->asJson([
                    'status' => 'duplicate',
                    'message' => 'အမည်၊ သွေးအုပ်စု၊ မြို့နယ်နှင့် ရပ်ကွက်/ကျေးရွာ အားလုံး တူညီသော လူနာ ရှိနေပါသည်။',
                    // Keep data as the first row for older app versions while
                    // the new client reads and displays every match.
                    'data' => $matches[0],
                    'matches' => $matches,
                ]);
            }
        }

        if (!$patient->save()) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'Failed to create patient.',
                'errors' => $patient->errors,
            ]);
        }

        return $this->asJson([
            'status' => 'ok',
            'data' => $patient,
        ]);
    }

    /**
     * Find potential patient matches. Name, blood type, township and
     * ward/village must ALL be equal for a record to count as a match —
     * sharing just a name (or just a blood group) is not enough.
     *
     * Matching is whitespace-trimmed and case-insensitive. A blank blood type
     * only matches records that also have none, so an exact double-submit is
     * still caught. Empty structured locations cannot establish a location
     * match and are deliberately skipped instead of grouping unrelated legacy
     * rows. Returns deterministic candidates for the comparison dialog.
     */
    private function findPotentialMatches($name, $bloodType, $township, $ward, $village, $excludeId = null)
    {
        $name = trim((string) $name);
        $bloodType = trim((string) $bloodType);
        $township = trim((string) $township);
        $ward = trim((string) $ward);
        $village = trim((string) $village);

        if ($name === '' || $township === '' || ($ward === '' && $village === '')) {
            return [];
        }

        $query = Patient::find()
            ->andWhere('LOWER(TRIM(name)) = LOWER(:name)', [':name' => $name])
            ->andWhere("LOWER(TRIM(COALESCE(blood_type, ''))) = LOWER(:bloodType)", [':bloodType' => $bloodType])
            ->andWhere("LOWER(TRIM(COALESCE(township, ''))) = LOWER(:township)", [':township' => $township])
            ->andWhere("LOWER(TRIM(COALESCE(ward, ''))) = LOWER(:ward)", [':ward' => $ward])
            ->andWhere("LOWER(TRIM(COALESCE(village, ''))) = LOWER(:village)", [':village' => $village]);

        if ($excludeId !== null) {
            $query->andWhere(['<>', 'id', $excludeId]);
        }

        return $query
            ->orderBy(['id' => SORT_ASC])
            ->limit(20)
            ->asArray()
            ->all();
    }

    /**
     * Update an existing patient
     */
    public function actionUpdate($id)
    {
        $patient = Patient::findOne($id);
        if ($patient === null) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'Patient not found.',
            ]);
        }

        $data = Yii::$app->request->post();
        $patient->load($data, '');

        if (!$patient->save()) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'Failed to update patient.',
                'errors' => $patient->errors,
            ]);
        }

        // Keep the denormalized snapshot on this patient's linked donations in
        // sync with the corrected patient record. Donations carry their own copy
        // of patient_name / patient_address, so without this a correction to the
        // patient (e.g. a wrong ward) would never reach the donation rows that
        // display it. Only identity + location are propagated; patient_age and
        // patient_disease are point-in-time per donation and left untouched.
        $synced = Donation::updateAll(
            [
                'patient_name' => $patient->name,
                'patient_address' => $patient->address,
            ],
            ['patient_id' => $patient->id]
        );

        return $this->asJson([
            'status' => 'ok',
            'data' => $patient,
            'donations_synced' => $synced,
        ]);
    }

    /**
     * Delete a patient
     */
    public function actionDelete($id)
    {
        $patient = Patient::findOne($id);
        if ($patient === null) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'Patient not found.',
            ]);
        }

        // Check if patient has donations
        $donationCount = Donation::find()->where(['patient_id' => $id])->count();
        if ($donationCount > 0) {
            // Unlink patient from donations instead of preventing delete
            Donation::updateAll(['patient_id' => null], ['patient_id' => $id]);
        }

        if (!$patient->delete()) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'Failed to delete patient.',
            ]);
        }

        return $this->asJson([
            'status' => 'ok',
            'message' => 'Patient deleted successfully.',
        ]);
    }

    /**
     * Search patients for dropdown. Matches name, phone, blood type and any
     * part of the address (combined address, township, ward, village) so the
     * picker can distinguish same-named patients.
     */
    public function actionSearch($q = '', $limit = 20)
    {
        $query = Patient::find()
            ->select(['id', 'name', 'phone', 'address', 'township', 'ward', 'village', 'age', 'gender', 'blood_type']);

        if ($q) {
            $query->andWhere(['or',
                ['ilike', 'name', $q],
                ['ilike', 'phone', $q],
                ['ilike', 'address', $q],
                ['ilike', 'blood_type', $q],
                ['ilike', 'township', $q],
                ['ilike', 'ward', $q],
                ['ilike', 'village', $q],
            ]);
        }

        $patients = $query
            ->limit($limit)
            ->orderBy(['name' => SORT_ASC])
            ->asArray()
            ->all();

        return $this->asJson([
            'status' => 'ok',
            'data' => $patients,
        ]);
    }

    /**
     * Check for potential duplicate patients (match scoring)
     */
    public function actionCheckMatch($name = '', $address = '')
    {
        if (empty($name)) {
            return $this->asJson([
                'status' => 'ok',
                'data' => [],
            ]);
        }

        $query = Patient::find()
            ->select(['id', 'name', 'phone', 'address', 'age', 'gender', 'blood_type']);

        // Search by similar name
        $query->andWhere(['ilike', 'name', $name]);

        // If address provided, also check for matching address
        if ($address) {
            $query->orWhere([
                'and',
                ['ilike', 'name', $name],
                ['ilike', 'address', $address],
            ]);
        }

        $matches = $query
            ->limit(10)
            ->orderBy(['name' => SORT_ASC])
            ->asArray()
            ->all();

        // Add match score
        foreach ($matches as &$match) {
            $score = 0;
            if (strtolower($match['name']) === strtolower($name)) {
                $score += 50;
            } elseif (stripos($match['name'], $name) !== false) {
                $score += 30;
            }
            if ($address && stripos($match['address'], $address) !== false) {
                $score += 30;
            }
            $match['match_score'] = $score;
        }

        // Sort by match score
        usort($matches, function($a, $b) {
            return $b['match_score'] - $a['match_score'];
        });

        return $this->asJson([
            'status' => 'ok',
            'data' => $matches,
        ]);
    }
}
