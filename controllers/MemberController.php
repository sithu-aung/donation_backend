<?php

namespace app\controllers;

use app\models\Member;
use Yii;
use yii\web\Controller;

class MemberController extends BaseAuthController
{
    public function actionIndex($page, $limit, $q = '')
    {
        $query = Member::find();
        if ($q) {
            $query = $query->where(['like', 'name', $q]);
        }
        $query = $query->offset($page * $limit)->limit($limit)->orderBy("id");

        return $this->asJson([
            'status' => 'ok',
            'data' => $query->all(),
        ]);
    }

    public function actionView($id)
    {
        $member = Member::findOne($id);
        if ($member === null) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'No Member Found.',
            ]);
        }

        return $this->asJson([
            'status' => 'ok',
            'data' => $member,
        ]);
    }

    public function actionCreate()
    {
        $member = new Member();
        $member->birth_date = Yii::$app->request->post('birth_date');
        $member->blood_bank_card = Yii::$app->request->post('blood_bank_card');
        $member->blood_type = Yii::$app->request->post('blood_type');
        $member->father_name = Yii::$app->request->post('father_name');
        $member->last_date = Yii::$app->request->post('last_date');
        $member->member_count = Yii::$app->request->post('member_count');
        $member->member_id = Yii::$app->request->post('member_id');
        $member->name = Yii::$app->request->post('name');
        $member->note = Yii::$app->request->post('note');
        $member->nrc = Yii::$app->request->post('nrc');
        $member->phone = Yii::$app->request->post('phone');
        $member->address = Yii::$app->request->post('address');
        $member->gender = Yii::$app->request->post('gender');
        $member->profile_url = Yii::$app->request->post('profile_url');
        $member->register_date = Yii::$app->request->post('register_date');
        $member->total_count = Yii::$app->request->post('total_count');
        $member->status = Yii::$app->request->post('status');
        $member->owner_id = Yii::$app->request->post('owner_id');

        if (!$member->save()) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'Failed to create Member.',
                'errors' => $member->errors,
            ]);
        }

        return $this->asJson([
            'status' => 'ok',
            'data' => $member
        ]);
    }

    public function actionUpdate($id)
    {
        $member = Member::findOne($id);
        if ($member === null) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'No Member Found.',
            ]);
        }

        $member->birth_date = Yii::$app->request->post('birth_date');
        $member->blood_bank_card = Yii::$app->request->post('blood_bank_card');
        $member->blood_type = Yii::$app->request->post('blood_type');
        $member->father_name = Yii::$app->request->post('father_name');
        $member->last_date = Yii::$app->request->post('last_date');
        $member->member_count = Yii::$app->request->post('member_count');
        $member->member_id = Yii::$app->request->post('member_id');
        $member->name = Yii::$app->request->post('name');
        $member->note = Yii::$app->request->post('note');
        $member->nrc = Yii::$app->request->post('nrc');
        $member->phone = Yii::$app->request->post('phone');
        $member->address = Yii::$app->request->post('address');
        $member->gender = Yii::$app->request->post('gender');
        $member->profile_url = Yii::$app->request->post('profile_url');
        $member->register_date = Yii::$app->request->post('register_date');
        $member->total_count = Yii::$app->request->post('total_count');
        $member->status = Yii::$app->request->post('status');
        $member->owner_id = Yii::$app->request->post('owner_id');

        if (!$member->save()) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'Failed to update Member.',
                'errors' => $member->errors,
            ]);
        }

        return $this->asJson([
            'status' => 'ok',
            'data' => $member
        ]);
    }

    public function actionDelete($id)
    {
        $member = Member::findOne($id);
        if ($member === null) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'No Member Found.',
            ]);
        }
        if (!$member->delete()) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'Failed to delete Member.',
            ]);
        }

        return $this->asJson([
            'status' => 'ok',
            'message' => 'Member is deleted.'
        ]);
    }
}