<?php

namespace app\controllers;

use app\models\Donation;
use Yii;

class DonationController extends BaseApiController
{
    public function actionIndex($page, $limit, $q = '', $order = 'desc', $disease = '', $hospital = '', $year = '')
    {
        $query = Donation::find();
        
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
            $query->andWhere([
                'or',
                ['date_part(\'year\', donation_date)' => $year],
                ['date_part(\'year\', date)' => $year]
            ]);
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

        return $this->asJson([
            'status' => 'ok',
            'data' => $query->all(),
            'total' => $count,
            'hospitals' => $hospitals,
            'diseases' => $diseases,
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
        $donation->donation_date = Yii::$app->request->post('donation_date');
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
        $donation->donation_date = Yii::$app->request->post('donation_date');
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
