<?php

namespace app\controllers;

use app\models\MonthlySponsor;
use app\models\MonthlySponsorDonation;
use Yii;

class MonthlySponsorController extends BaseApiController
{
    /**
     * List monthly sponsors with their donation count + total amount.
     */
    public function actionIndex($page = 0, $limit = 50, $q = '')
    {
        $query = MonthlySponsor::find()
            ->select([
                'monthly_sponsor.*',
                'COUNT(monthly_sponsor_donation.id) as donation_count',
                'COALESCE(SUM(monthly_sponsor_donation.amount), 0) as total_amount',
            ])
            ->leftJoin('monthly_sponsor_donation', 'monthly_sponsor_donation.sponsor_id = monthly_sponsor.id')
            ->groupBy('monthly_sponsor.id');

        if ($q) {
            $query->andWhere(['or',
                ['ilike', 'monthly_sponsor.name', $q],
                ['ilike', 'monthly_sponsor.phone', $q],
            ]);
        }

        $countQuery = clone $query;
        $count = $countQuery->count();

        $sponsors = $query
            ->offset($page * $limit)
            ->limit($limit)
            ->orderBy([
                'monthly_sponsor.created_at' => SORT_ASC,
                'monthly_sponsor.id' => SORT_ASC,
            ])
            ->asArray()
            ->all();

        foreach ($sponsors as &$s) {
            $s['donation_count'] = (int)($s['donation_count'] ?? 0);
            $s['total_amount'] = (int)($s['total_amount'] ?? 0);
        }

        return $this->asJson([
            'status' => 'ok',
            'data' => $sponsors,
            'total' => $count,
            'page' => (int)$page,
            'limit' => (int)$limit,
            'hasMore' => ($page * $limit + $limit) < $count,
        ]);
    }

    /**
     * One sponsor with their full monthly donation history + total.
     */
    public function actionView($id)
    {
        $sponsor = MonthlySponsor::find()->where(['id' => $id])->asArray()->one();
        if ($sponsor === null) {
            return $this->asJson(['status' => 'error', 'message' => 'Monthly sponsor not found.']);
        }

        $donations = MonthlySponsorDonation::find()
            ->where(['sponsor_id' => $id])
            ->orderBy(['date' => SORT_DESC, 'id' => SORT_DESC])
            ->asArray()
            ->all();

        $totalAmount = 0;
        foreach ($donations as $d) {
            $totalAmount += (int)($d['amount'] ?? 0);
        }

        $sponsor['donations'] = $donations;
        $sponsor['donation_count'] = count($donations);
        $sponsor['total_amount'] = $totalAmount;

        return $this->asJson(['status' => 'ok', 'data' => $sponsor]);
    }

    public function actionCreate()
    {
        $sponsor = new MonthlySponsor();
        $sponsor->load(Yii::$app->request->post(), '');
        if (empty($sponsor->owner_id)) {
            $sponsor->owner_id = 'system';
        }
        if (!$sponsor->save()) {
            return $this->asJson(['status' => 'error', 'message' => 'Failed to create sponsor.', 'errors' => $sponsor->errors]);
        }
        return $this->asJson(['status' => 'ok', 'data' => $sponsor]);
    }

    public function actionUpdate($id)
    {
        $sponsor = MonthlySponsor::findOne($id);
        if ($sponsor === null) {
            return $this->asJson(['status' => 'error', 'message' => 'Monthly sponsor not found.']);
        }
        $sponsor->load(Yii::$app->request->post(), '');
        if (!$sponsor->save()) {
            return $this->asJson(['status' => 'error', 'message' => 'Failed to update sponsor.', 'errors' => $sponsor->errors]);
        }
        return $this->asJson(['status' => 'ok', 'data' => $sponsor]);
    }

    public function actionDelete($id)
    {
        $sponsor = MonthlySponsor::findOne($id);
        if ($sponsor === null) {
            return $this->asJson(['status' => 'error', 'message' => 'Monthly sponsor not found.']);
        }
        // Linked donations are removed by the FK ON DELETE CASCADE.
        if (!$sponsor->delete()) {
            return $this->asJson(['status' => 'error', 'message' => 'Failed to delete sponsor.']);
        }
        return $this->asJson(['status' => 'ok', 'message' => 'Monthly sponsor deleted successfully.']);
    }
}
