<?php

namespace app\controllers;

use app\models\SpecialEvent;
use Yii;
use yii\web\Controller;

class SpecialEventController extends BaseAuthController
{
    public function actionIndex($page, $limit, $q = '')
    {
        $query = SpecialEvent::find();
        if ($q) {
            $query = $query->where(['like', 'lab_name', $q]);
        }
        $query = $query->offset($page * $limit)->limit($limit)->orderBy("id");

        return $this->asJson([
            'status' => 'ok',
            'data' => $query->all(),
        ]);
    }

    public function actionView($id)
    {
        $specialEvent = SpecialEvent::findOne($id);
        if ($specialEvent === null) {
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
        $specialEvent->date = Yii::$app->request->post('date');
        $specialEvent->haemoglobin = Yii::$app->request->post('haemoglobin');
        $specialEvent->hbs_ag = Yii::$app->request->post('hbs_ag');
        $specialEvent->hcv_ab = Yii::$app->request->post('hcv_ab');
        $specialEvent->mp_ict = Yii::$app->request->post('mp_ict');
        $specialEvent->retro_test = Yii::$app->request->post('retro_test');
        $specialEvent->vdrl_test = Yii::$app->request->post('vdrl_test');
        $specialEvent->lab_name = Yii::$app->request->post('lab_name');
        $specialEvent->total = Yii::$app->request->post('total');

        if (!$specialEvent->save()) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'Failed to create SpecialEvent.',
                'errors' => $specialEvent->errors,
            ]);
        }

        return $this->asJson([
            'status' => 'ok',
            'data' => $specialEvent
        ]);
    }

    public function actionUpdate($id)
    {
        $specialEvent = SpecialEvent::findOne($id);
        if ($specialEvent === null) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'No SpecialEvent Found.',
            ]);
        }

        $specialEvent->date = Yii::$app->request->post('date');
        $specialEvent->haemoglobin = Yii::$app->request->post('haemoglobin');
        $specialEvent->hbs_ag = Yii::$app->request->post('hbs_ag');
        $specialEvent->hcv_ab = Yii::$app->request->post('hcv_ab');
        $specialEvent->mp_ict = Yii::$app->request->post('mp_ict');
        $specialEvent->retro_test = Yii::$app->request->post('retro_test');
        $specialEvent->vdrl_test = Yii::$app->request->post('vdrl_test');
        $specialEvent->lab_name = Yii::$app->request->post('lab_name');
        $specialEvent->total = Yii::$app->request->post('total');

        if (!$specialEvent->save()) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'Failed to update SpecialEvent.',
                'errors' => $specialEvent->errors,
            ]);
        }

        return $this->asJson([
            'status' => 'ok',
            'data' => $specialEvent
        ]);
    }

    public function actionDelete($id)
    {
        $specialEvent = SpecialEvent::findOne($id);
        if ($specialEvent === null) {
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
}