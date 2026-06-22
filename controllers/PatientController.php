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
            ->orderBy(['donation_date' => SORT_DESC])
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

        // Duplicate guard: refuse a patient whose name + township + ward/village
        // already exist, so accidentally submitting the same person twice does
        // not create two identical rows. The client may resend with force=1 when
        // it really is a different person who happens to share name and area.
        $force = filter_var($data['force'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if (!$force) {
            $existing = $this->findDuplicate(
                $patient->name,
                $patient->township,
                $patient->ward,
                $patient->village
            );
            if ($existing !== null) {
                return $this->asJson([
                    'status' => 'duplicate',
                    'message' => 'ဤအမည်၊ မြို့နယ်နှင့် ရပ်ကွက်/ကျေးရွာ တူညီသော လူနာ ရှိပြီးဖြစ်ပါသည်။',
                    'data' => $existing,
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
     * Find an existing patient that matches on name + township + ward + village.
     *
     * Matching is whitespace-trimmed and case-insensitive. Comparing both ward
     * and village keeps a ward-based address distinct from a village-based one
     * even when they share a name and township. Returns the row as an array, or
     * null when no duplicate exists.
     */
    private function findDuplicate($name, $township, $ward, $village, $excludeId = null)
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        $query = Patient::find()
            ->andWhere('LOWER(TRIM(name)) = LOWER(:name)', [':name' => $name])
            ->andWhere("LOWER(TRIM(COALESCE(township, ''))) = LOWER(:township)", [':township' => trim((string) $township)])
            ->andWhere("LOWER(TRIM(COALESCE(ward, ''))) = LOWER(:ward)", [':ward' => trim((string) $ward)])
            ->andWhere("LOWER(TRIM(COALESCE(village, ''))) = LOWER(:village)", [':village' => trim((string) $village)]);

        if ($excludeId !== null) {
            $query->andWhere(['<>', 'id', $excludeId]);
        }

        return $query->asArray()->one();
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
