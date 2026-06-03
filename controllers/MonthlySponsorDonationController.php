<?php

namespace app\controllers;

use app\models\MonthlySponsorDonation;
use Yii;

/**
 * Per-sponsor monthly donation records (amount + date). Standalone — not part
 * of the income/expense (donar_record / expenses_record) reports.
 */
class MonthlySponsorDonationController extends BaseApiController
{
    public function actionCreate()
    {
        $record = new MonthlySponsorDonation();
        $record->load(Yii::$app->request->post(), '');
        if (!$record->save()) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'Failed to create record',
                'errors' => $record->errors,
            ]);
        }
        return $this->asJson(['status' => 'ok', 'data' => $record]);
    }

    public function actionUpdate($id)
    {
        $record = MonthlySponsorDonation::findOne($id);
        if (!$record) {
            return $this->asJson(['status' => 'error', 'message' => 'Record not found']);
        }
        $record->load(Yii::$app->request->post(), '');
        if (!$record->save()) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'Failed to update record',
                'errors' => $record->errors,
            ]);
        }
        return $this->asJson(['status' => 'ok', 'data' => $record]);
    }

    public function actionDelete($id)
    {
        $record = MonthlySponsorDonation::findOne($id);
        if (!$record) {
            return $this->asJson(['status' => 'error', 'message' => 'Record not found']);
        }
        if (!$record->delete()) {
            return $this->asJson(['status' => 'error', 'message' => 'Failed to delete record']);
        }
        return $this->asJson(['status' => 'ok', 'message' => 'Record deleted successfully']);
    }
}
