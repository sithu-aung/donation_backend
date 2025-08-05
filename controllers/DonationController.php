<?php

namespace app\controllers;

use app\models\Donation;
use Yii;

class DonationController extends BaseApiController
{
    public function actionIndex($page, $limit, $q = '', $order = 'desc', $disease = '', $hospital = '', $year = '')
    {
        $query = Donation::find()
            ->with(['member0' => function($query) {
                $query->select(['id', 'name', 'blood_type', 'phone', 'address', 'nrc', 'father_name','blood_bank_card','birth_date','member_id']);
            }]);

        // Apply filters
        if ($q) {
            $query->andWhere(['like', 'patient_name', $q]);
        }
        if ($disease) {
            $query->andWhere(['patient_disease' => $disease]);
        }
        if ($hospital) {
            $query->andWhere(['hospital' => $hospital]);
        }
        if ($year) {
            $query->andWhere("date_part('year', donation_date) = :year", [':year' => $year]);
        }

        // Get total count after applying filters
        $count = $query->count();

        $hospitals = Donation::find()
            ->select('hospital')
            ->distinct()
            ->where(['not', ['hospital' => null]])
            ->column();

        $diseases = Donation::find()
            ->select('patient_disease')
            ->distinct()
            ->where(['not', ['patient_disease' => null]])
            ->column();

        // Convert order parameter to SORT_ASC or SORT_DESC
        $direction = strtolower($order) === 'desc' ? SORT_DESC : SORT_ASC;
        $query = $query->offset($page * $limit)
            ->limit($limit)
            ->orderBy(['id' => $direction]);

        // Get donation data with related member information
        $donations = $query->asArray()->all();

        // Map member data to memberObj for frontend compatibility
        foreach ($donations as &$donation) {
            if (isset($donation['member0'])) {
                $donation['memberObj'] = $donation['member0'];
            }
        }

        return $this->asJson([
            'status' => 'ok',
            'data' => $donations,
            'total' => $count,
            'hospitals' => $hospitals,
            'diseases' => $diseases,
            'page' => $page,
            'limit' => $limit,
            'hasMore' => ($page * $limit + $limit) < $count,
        ]);
    }

    public function actionByMonthYear($month, $year, $page = 0, $limit = 500)
    {
        $query = Donation::find()
            ->with(['member0' => function($query) {
                $query->select(['id', 'name', 'blood_type', 'phone', 'address', 'nrc', 'father_name','blood_bank_card','birth_date','member_id']);
            }])
            ->where("date_part('month', donation_date) = :month", [':month' => $month])
            ->andWhere("date_part('year', donation_date) = :year", [':year' => $year]);

        // Get total count
        $count = $query->count();

        // Apply pagination
        $query = $query->offset($page * $limit)
            ->limit($limit)
            ->orderBy(['donation_date' => SORT_ASC]);

        // Get donation data with related member information
        $donations = $query->asArray()->all();

        // Map member data to memberObj for frontend compatibility
        foreach ($donations as &$donation) {
            if (isset($donation['member0'])) {
                $donation['memberObj'] = $donation['member0'];
            }
        }

        return $this->asJson([
            'status' => 'ok',
            'data' => $donations,
            'total' => $count,
            'page' => $page,
            'limit' => $limit,
            'hasMore' => ($page * $limit + $limit) < $count,
        ]);
    }

    public function actionByYear($year, $page = 0, $limit = 500)
    {
        $query = Donation::find()
            ->with(['member0' => function($query) {
                $query->select(['id', 'name', 'blood_type', 'phone', 'address', 'nrc', 'father_name','blood_bank_card','birth_date','member_id']);
            }])
            ->where("date_part('year', donation_date) = :year", [':year' => $year]);

        // Get total count
        $count = $query->count();

        // Apply pagination
        $query = $query->offset($page * $limit)
            ->limit($limit)
            ->orderBy(['donation_date' => SORT_DESC]);

        // Get donation data with related member information
        $donations = $query->asArray()->all();

        // Map member data to memberObj for frontend compatibility
        foreach ($donations as &$donation) {
            if (isset($donation['member0'])) {
                $donation['memberObj'] = $donation['member0'];
            }
        }

        return $this->asJson([
            'status' => 'ok',
            'data' => $donations,
            'total' => $count,
            'page' => $page,
            'limit' => $limit,
            'hasMore' => ($page * $limit + $limit) < $count,
        ]);
    }

    public function actionView($id)
    {
        $donation = Donation::find()
            ->with('member0')
            ->where(['id' => $id])
            ->asArray()
            ->one();
        if ($donation === null) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'No Donation Found.',
            ]);
        }

        return $this->asJson([
            'status' => 'ok',
            'data' => $donation,
        ]);
    }

    public function actionCreate()
    {
        $donation = new Donation();
        $donation->date = Yii::$app->request->post('date');

        // Handle donation_date without timezone conversion
        $donationDate = Yii::$app->request->post('donation_date');
        if ($donationDate) {
            // Store the datetime exactly as provided from the client
            $dateObj = new \DateTime($donationDate);
            $donation->donation_date = $dateObj->format('Y-m-d H:i:s');

            // Debug logging in development
            Yii::debug("Original date preserved: {$donationDate}, Stored as: {$donation->donation_date}");
        } else {
            $donation->donation_date = null;
        }

        $donation->hospital = Yii::$app->request->post('hospital');
        $donation->member_id = Yii::$app->request->post('member_id');
        $donation->member = Yii::$app->request->post('member');
        $donation->patient_address = Yii::$app->request->post('patient_address');
        $donation->patient_age = Yii::$app->request->post('patient_age');
        $donation->patient_disease = Yii::$app->request->post('patient_disease');
        $donation->patient_name = Yii::$app->request->post('patient_name');
        $donation->owner_id = Yii::$app->request->post('owner_id');

        if (!$donation->save()) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'Failed to create Donation.',
                'errors' => $donation->errors,
            ]);
        }

        return $this->asJson([
            'status' => 'ok',
            'data' => $donation
        ]);
    }

    public function actionUpdate($id)
    {
        $donation = Donation::findOne($id);
        if ($donation === null) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'No Donation Found.',
            ]);
        }

        $donation->date = Yii::$app->request->post('date');

        // Handle donation_date without timezone conversion
        $donationDate = Yii::$app->request->post('donation_date');
        if ($donationDate) {
            // Store the datetime exactly as provided from the client
            $dateObj = new \DateTime($donationDate);
            $donation->donation_date = $dateObj->format('Y-m-d H:i:s');

            // Debug logging in development
            Yii::debug("Original date preserved: {$donationDate}, Stored as: {$donation->donation_date}");
        } else {
            $donation->donation_date = null;
        }

        $donation->hospital = Yii::$app->request->post('hospital');
        $donation->member_id = Yii::$app->request->post('member_id');
        $donation->member = Yii::$app->request->post('member');
        $donation->patient_address = Yii::$app->request->post('patient_address');
        $donation->patient_age = Yii::$app->request->post('patient_age');
        $donation->patient_disease = Yii::$app->request->post('patient_disease');
        $donation->patient_name = Yii::$app->request->post('patient_name');
        $donation->owner_id = Yii::$app->request->post('owner_id');

        if (!$donation->save()) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'Failed to update Donation.',
                'errors' => $donation->errors,
            ]);
        }

        return $this->asJson([
            'status' => 'ok',
            'data' => $donation
        ]);
    }

    public function actionDelete($id)
    {
        $donation = Donation::findOne($id);
        if ($donation === null) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'No Donation Found.',
            ]);
        }
        if (!$donation->delete()) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'Failed to delete Donation.',
            ]);
        }

        return $this->asJson([
            'status' => 'ok',
            'message' => 'Donation is deleted.'
        ]);
    }

    public function actionPatientList($page = 0, $limit = 20, $q = '', $order = 'desc')
    {
        $offset = $page * $limit;
        $orderDirection = strtolower($order) === 'desc' ? 'DESC' : 'ASC';
        
        // Build search condition
        $searchCondition = '';
        $params = [];
        if (!empty($q)) {
            $searchCondition = "WHERE d.patient_name ILIKE :q 
                              OR d.patient_disease ILIKE :q 
                              OR d.hospital ILIKE :q 
                              OR d.patient_address ILIKE :q";
            $params[':q'] = '%' . $q . '%';
        }
        
        // Get unique patients with their latest donation info and count
        $sql = "
            WITH latest_donations AS (
                SELECT 
                    patient_name,
                    patient_age,
                    patient_address,
                    patient_disease,
                    hospital,
                    member_id,
                    id as latest_id,
                    donation_date as latest_donation_date,
                    ROW_NUMBER() OVER (PARTITION BY patient_name ORDER BY donation_date DESC) as rn
                FROM donation
                WHERE patient_name IS NOT NULL AND patient_name != ''
            ),
            patient_stats AS (
                SELECT 
                    patient_name,
                    COUNT(*) as donation_count
                FROM donation
                WHERE patient_name IS NOT NULL AND patient_name != ''
                GROUP BY patient_name
            )
            SELECT 
                ld.patient_name,
                ld.patient_age,
                ld.patient_address,
                ld.patient_disease,
                ld.hospital,
                m.blood_type as blood_group,
                ld.latest_id,
                ld.latest_donation_date,
                ps.donation_count
            FROM latest_donations ld
            LEFT JOIN member m ON ld.member_id = m.id
            LEFT JOIN patient_stats ps ON ld.patient_name = ps.patient_name
            WHERE ld.rn = 1
        ";
        
        // Count total unique patients for pagination
        $countSql = "
            SELECT COUNT(DISTINCT patient_name) as count
            FROM donation d
            WHERE patient_name IS NOT NULL AND patient_name != ''
            $searchCondition
        ";
        
        $count = Yii::$app->db->createCommand($countSql, $params)->queryScalar();
        
        // Add search condition to main query
        if (!empty($searchCondition)) {
            $sql = "
                WITH latest_donations AS (
                    SELECT 
                        d.patient_name,
                        d.patient_age,
                        d.patient_address,
                        d.patient_disease,
                        d.hospital,
                        d.member_id,
                        d.id as latest_id,
                        d.donation_date as latest_donation_date,
                        ROW_NUMBER() OVER (PARTITION BY d.patient_name ORDER BY d.donation_date DESC) as rn
                    FROM donation d
                    $searchCondition
                ),
                patient_stats AS (
                    SELECT 
                        patient_name,
                        COUNT(*) as donation_count
                    FROM donation
                    WHERE patient_name IS NOT NULL AND patient_name != ''
                    GROUP BY patient_name
                )
                SELECT 
                    ld.patient_name,
                    ld.patient_age,
                    ld.patient_address,
                    ld.patient_disease,
                    ld.hospital,
                    m.blood_type as blood_group,
                    ld.latest_id,
                    ld.latest_donation_date,
                    ps.donation_count
                FROM latest_donations ld
                LEFT JOIN member m ON ld.member_id = m.id
                LEFT JOIN patient_stats ps ON ld.patient_name = ps.patient_name
                WHERE ld.rn = 1
            ";
        }
        
        // Add ordering and pagination
        $sql .= " ORDER BY ld.latest_donation_date $orderDirection
                  LIMIT :limit OFFSET :offset";
        
        $params[':limit'] = $limit;
        $params[':offset'] = $offset;
        
        $patients = Yii::$app->db->createCommand($sql, $params)->queryAll();
        
        return $this->asJson([
            'status' => 'ok',
            'data' => $patients,
            'total' => (int)$count,
            'page' => $page,
            'limit' => $limit,
            'hasMore' => ($offset + $limit) < $count,
        ]);
    }
}
