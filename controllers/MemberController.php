<?php

namespace app\controllers;

use app\models\Member;
use Yii;
use yii\web\Controller;

class MemberController extends BaseApiController
{
    public function actionIndex($page, $limit, $q = '', $blood_type = null, $status = null)
    {
        $query = Member::find();
        
        // Search by name
        if ($q) {
            $query = $query->where(['like', 'name', $q]);
        }
        
        // Filter by blood type
        if ($blood_type) {
            $query = $query->andWhere(['blood_type' => $blood_type]);
        }
        
        // Filter by status
        if ($status) {
            $query = $query->andWhere(['status' => $status]);
        }
        
        $query = $query->offset($page * $limit)->limit($limit)->orderBy("id");
        $total = $query->count();

        return $this->asJson([
            'status' => 'ok',
            'data' => $query->all(),
            'total' => $total,
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
        $request = Yii::$app->request;
        $rawBody = $request->getRawBody();
        $data = json_decode($rawBody, true);
        
        // Generate member_id
        $totalMembers = Member::find()->count();
        $group = chr(65 + intval($totalMembers / 1000)); // Convert to letter A, B, C, etc.
        $number = str_pad(($totalMembers % 1000) + 1, 4, '0', STR_PAD_LEFT);
        $member->member_id = $group . '-' . $number;

        $member->birth_date = $data['birth_date'] ?? null;
        $member->blood_bank_card = $data['blood_bank_card'] ?? null;
        $member->blood_type = $data['blood_type'] ?? null;
        $member->father_name = $data['father_name'] ?? null;
        $member->member_count = "0";
        $member->name = $data['name'] ?? null;
        $member->note = $data['note'] ?? null;
        $member->nrc = $data['nrc'] ?? null;
        $member->phone = $data['phone'] ?? null;
        $member->address = $data['address'] ?? null;
        $member->gender = $data['gender'] ?? null;
        $member->register_date = date('Y-m-d H:i:s');
        $member->total_count = "0";
        $member->status = 'available';
        $member->last_date = '-';
        $member->owner_id = "1";

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

        $request = Yii::$app->request;
        $rawBody = $request->getRawBody();
        $data = json_decode($rawBody, true);
        
        $member->birth_date = $data['birth_date'] ?? $member->birth_date;
        $member->blood_bank_card = $data['blood_bank_card'] ?? $member->blood_bank_card;
        $member->blood_type = $data['blood_type'] ?? $member->blood_type;
        $member->father_name = $data['father_name'] ?? $member->father_name;
        $member->last_date = $data['last_date'] ?? $member->last_date;
        $member->member_count = $data['member_count'] ?? $member->member_count;
        $member->name = $data['name'] ?? $member->name;
        $member->note = $data['note'] ?? $member->note;
        $member->nrc = $data['nrc'] ?? $member->nrc;
        $member->phone = $data['phone'] ?? $member->phone;
        $member->address = $data['address'] ?? $member->address;
        $member->gender = $data['gender'] ?? $member->gender;
        $member->profile_url = $data['profile_url'] ?? $member->profile_url;
        $member->total_count = $data['total_count'] ?? $member->total_count;
        $member->status = $data['status'] ?? $member->status;

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
