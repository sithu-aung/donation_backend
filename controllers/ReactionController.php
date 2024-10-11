<?php

namespace app\controllers;

use app\models\Reaction;
use Yii;
use yii\web\Controller;

class ReactionController extends Controller
{
    public function actionIndex($page, $limit, $q = '')
    {
        $query = Reaction::find();
        if ($q) {
            $query = $query->where(['like', 'type', $q]);
        }
        $query = $query->offset($page * $limit)->limit($limit)->orderBy("id");

        return $this->asJson([
            'status' => 'ok',
            'data' => $query->all(),
        ]);
    }

    public function actionView($id)
    {
        $reaction = Reaction::findOne($id);
        if ($reaction === null) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'No Reaction Found.',
            ]);
        }

        return $this->asJson([
            'status' => 'ok',
            'data' => $reaction,
        ]);
    }

    public function actionCreate()
    {
        $reaction = new Reaction();
        $reaction->emoji = Yii::$app->request->post('emoji');
        $reaction->type = Yii::$app->request->post('type');
        $reaction->member_id = Yii::$app->request->post('member_id');
        $reaction->created_at = Yii::$app->request->post('created_at');

        if (!$reaction->save()) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'Failed to create Reaction.',
                'errors' => $reaction->errors,
            ]);
        }

        return $this->asJson([
            'status' => 'ok',
            'data' => $reaction
        ]);
    }

    public function actionUpdate($id)
    {
        $reaction = Reaction::findOne($id);
        if ($reaction === null) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'No Reaction Found.',
            ]);
        }

        $reaction->emoji = Yii::$app->request->post('emoji');
        $reaction->type = Yii::$app->request->post('type');
        $reaction->member_id = Yii::$app->request->post('member_id');
        $reaction->created_at = Yii::$app->request->post('created_at');

        if (!$reaction->save()) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'Failed to update Reaction.',
                'errors' => $reaction->errors,
            ]);
        }

        return $this->asJson([
            'status' => 'ok',
            'data' => $reaction
        ]);
    }

    public function actionDelete($id)
    {
        $reaction = Reaction::findOne($id);
        if ($reaction === null) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'No Reaction Found.',
            ]);
        }
        if (!$reaction->delete()) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'Failed to delete Reaction.',
            ]);
        }

        return $this->asJson([
            'status' => 'ok',
            'message' => 'Reaction is deleted.'
        ]);
    }
}