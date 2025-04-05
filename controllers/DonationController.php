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
}
