<?php

namespace app\controllers;

use app\models\Donation;
use Yii;
use yii\web\Controller;

class DonationController extends BaseAuthController
{
    public function actionIndex($page, $limit, $q = '')
    {
        $query = Donation::find();
        if ($q) {
            $query = $query->where(['like', 'patient_name', $q]);
        }
        $query = $query->offset($page * $limit)->limit($limit)->orderBy("id");

        return $this->asJson([
            'status' => 'ok',
            'data' => $query->all(),
        ]);
    }

    public function actionView($id)
    {
        $donation = Donation::findOne($id);
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