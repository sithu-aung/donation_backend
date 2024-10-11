<?php

namespace app\controllers;

use app\models\Comment;
use Yii;
use yii\web\Controller;

class CommentController extends Controller
{
    public function actionIndex($page, $limit, $q = '')
    {
        $query = Comment::find();
        if ($q) {
            $query = $query->where(['like', 'text', $q]);
        }
        $query = $query->offset($page * $limit)->limit($limit)->orderBy("id");

        return $this->asJson([
            'status' => 'ok',
            'data' => $query->all(),
        ]);
    }

    public function actionView($id)
    {
        $comment = Comment::findOne($id);
        if ($comment === null) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'No Comment Found.',
            ]);
        }

        return $this->asJson([
            'status' => 'ok',
            'data' => $comment,
        ]);
    }

    public function actionCreate()
    {
        $comment = new Comment();
        $comment->text = Yii::$app->request->post('text');
        $comment->member_id = Yii::$app->request->post('member_id');
        $comment->created_at = Yii::$app->request->post('created_at');

        if (!$comment->save()) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'Failed to create Comment.',
                'errors' => $comment->errors,
            ]);
        }

        return $this->asJson([
            'status' => 'ok',
            'data' => $comment
        ]);
    }

    public function actionUpdate($id)
    {
        $comment = Comment::findOne($id);
        if ($comment === null) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'No Comment Found.',
            ]);
        }

        $comment->text = Yii::$app->request->post('text');
        $comment->member_id = Yii::$app->request->post('member_id');
        $comment->created_at = Yii::$app->request->post('created_at');

        if (!$comment->save()) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'Failed to update Comment.',
                'errors' => $comment->errors,
            ]);
        }

        return $this->asJson([
            'status' => 'ok',
            'data' => $comment
        ]);
    }

    public function actionDelete($id)
    {
        $comment = Comment::findOne($id);
        if ($comment === null) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'No Comment Found.',
            ]);
        }
        if (!$comment->delete()) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'Failed to delete Comment.',
            ]);
        }

        return $this->asJson([
            'status' => 'ok',
            'message' => 'Comment is deleted.'
        ]);
    }
}