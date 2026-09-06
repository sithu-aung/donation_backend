<?php

namespace app\controllers;

use app\models\SpecialEvent;
use Yii;

class SpecialEventController extends BaseApiController
{
    private const MAX_PAGE_SIZE = 100;

    public function actionIndex($page = 0, $limit = 50, $q = '')
    {
        $page = max(0, (int) $page);
        $limit = min(self::MAX_PAGE_SIZE, max(1, (int) $limit));
        $q = trim((string) $q);

        $query = SpecialEvent::find();
        if ($q !== '') {
            $query->andWhere(['like', 'lab_name', $q]);
        }

        // Get total count before applying pagination
        $totalCount = (int) $query->count();

        $events = $query
            ->orderBy(['id' => SORT_DESC])
            ->offset($page * $limit)
            ->limit($limit)
            ->asArray()
            ->all();

        return $this->asJson([
            'status' => 'ok',
            'data' => $events,
            'total' => $totalCount,
            'page' => $page,
            'limit' => $limit,
            'hasMore' => (($page + 1) * $limit) < $totalCount,
        ]);
    }

    public function actionView($id)
    {
        $specialEvent = SpecialEvent::findOne($id);
        if ($specialEvent === null) {
            Yii::$app->response->statusCode = 404;
            return $this->asJson([
                'status' => 'error',
                'message' => 'No SpecialEvent Found.',
            ]);
        }

        return $this->asJson([
            'status' => 'ok',
            'data' => $specialEvent,
        ]);
    }

    public function actionCreate()
    {
        $specialEvent = new SpecialEvent();
        $this->populateFromRequest($specialEvent);

        if (!$specialEvent->validate()) {
            Yii::$app->response->statusCode = 422;
            return $this->asJson([
                'status' => 'error',
                'message' => 'Failed to create SpecialEvent.',
                'errors' => $specialEvent->errors,
            ]);
        }

        $specialEvent->total = $this->calculateTotal($specialEvent);
        $specialEvent->save(false);
        Yii::$app->response->statusCode = 201;

        return $this->asJson([
            'status' => 'ok',
            'data' => $specialEvent
        ]);
    }

    public function actionUpdate($id)
    {
        $specialEvent = SpecialEvent::findOne($id);
        if ($specialEvent === null) {
            Yii::$app->response->statusCode = 404;
            return $this->asJson([
                'status' => 'error',
                'message' => 'No SpecialEvent Found.',
            ]);
        }

        $this->populateFromRequest($specialEvent);

        if (!$specialEvent->validate()) {
            Yii::$app->response->statusCode = 422;
            return $this->asJson([
                'status' => 'error',
                'message' => 'Failed to update SpecialEvent.',
                'errors' => $specialEvent->errors,
            ]);
        }

        $specialEvent->total = $this->calculateTotal($specialEvent);
        $specialEvent->save(false);

        return $this->asJson([
            'status' => 'ok',
            'data' => $specialEvent
        ]);
    }

    public function actionDelete($id)
    {
        $specialEvent = SpecialEvent::findOne($id);
        if ($specialEvent === null) {
            Yii::$app->response->statusCode = 404;
            return $this->asJson([
                'status' => 'error',
                'message' => 'No SpecialEvent Found.',
            ]);
        }
        if (!$specialEvent->delete()) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'Failed to delete SpecialEvent.',
            ]);
        }

        return $this->asJson([
            'status' => 'ok',
            'message' => 'SpecialEvent is deleted.'
        ]);
    }

    private function populateFromRequest(SpecialEvent $specialEvent): void
    {
        $request = Yii::$app->request;
        $specialEvent->date = $request->post('date');
        $specialEvent->haemoglobin = $request->post('haemoglobin', 0);
        $specialEvent->hbs_ag = $request->post('hbs_ag', 0);
        $specialEvent->hcv_ab = $request->post('hcv_ab', 0);
        $specialEvent->mp_ict = $request->post('mp_ict', 0);
        $specialEvent->retro_test = $request->post('retro_test', 0);
        $specialEvent->vdrl_test = $request->post('vdrl_test', 0);
        $specialEvent->lab_name = $request->post('lab_name');
        // The server is authoritative for the derived total. Ignore any total
        // sent by older clients so a malformed request cannot persist an
        // inconsistent summary.
        $specialEvent->total = 0;
    }

    private function calculateTotal(SpecialEvent $specialEvent): int
    {
        return (int) $specialEvent->haemoglobin
            + (int) $specialEvent->hbs_ag
            + (int) $specialEvent->hcv_ab
            + (int) $specialEvent->mp_ict
            + (int) $specialEvent->retro_test
            + (int) $specialEvent->vdrl_test;
    }
}
