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

        return $this->asJson([
            'status' => 'ok',
            'data' => $patient,
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
     * Search patients for dropdown
     */
    public function actionSearch($q = '', $limit = 20)
    {
        $query = Patient::find()
            ->select(['id', 'name', 'phone', 'address', 'age', 'gender', 'blood_type']);

        if ($q) {
            $query->andWhere(['or',
                ['ilike', 'name', $q],
                ['ilike', 'phone', $q],
                ['ilike', 'address', $q],
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
