<?php

namespace app\commands;

use app\models\Member;
use app\models\Donation;
use app\models\SpecialEvent;
use app\models\DonarRecord;
use app\models\ExpensesRecord;
use app\models\RequestGive;
use Yii;
use yii\console\Controller;
use yii\helpers\Json;

class ImportController extends Controller
{
    public function actionAll()
    {
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $this->seedMembers();
            $this->seedDonations();
            $this->seedSpecialEvents();
            $this->seedDonarRecords();
            $this->seedExpensesRecords();
            $this->seedRequestGives();
        } catch (\Exception $e) {
            $transaction->rollBack();
            throw $e;
        }
        $transaction->commit();
    }

    protected function seedMembers()
    {
        $data = Json::decode(file_get_contents('data/members.json'));
        foreach ($data as $item) {
            $member = new Member();
            $member->birth_date = $item['birth_date'];
            $member->blood_bank_card = $item['blood_bank_card'];
            $member->blood_type = $item['blood_type'];
            $member->father_name = $item['father_name'];
            $member->last_date = $item['last_date'];
            $member->member_count = $item['member_count'];
            $member->member_id = $item['member_id'];
            $member->name = $item['name'];
            $member->note = $item['note'];
            $member->nrc = $item['nrc'];
            $member->phone = $item['phone'];
            $member->address = $item['address'];
            $member->gender = $item['gender'];
            $member->profile_url = $item['profile_url'];
            $member->register_date = $item['register_date'];
            $member->total_count = $item['total_count'];
            $member->status = $item['status'];
            $member->owner_id = $item['owner_id'];
            $member->save();
        }
    }

    protected function seedDonations()
    {
        $data = Json::decode(file_get_contents('data/donations.json'));
        foreach ($data as $item) {
            $donation = new Donation();
            $donation->date = $item['date'];
            $donation->donation_date = $item['donation_date'];
            $donation->hospital = $item['hospital'];
            $donation->member_id = $item['member_id'];
            $donation->member = $item['member'];
            $donation->patient_address = $item['patient_address'];
            $donation->patient_age = $item['patient_age'];
            $donation->patient_disease = $item['patient_disease'];
            $donation->patient_name = $item['patient_name'];
            $donation->owner_id = $item['owner_id'];
            $donation->save();
        }
    }

    protected function seedSpecialEvents()
    {
        $data = Json::decode(file_get_contents('data/special_events.json'));
        foreach ($data as $item) {
            $event = new SpecialEvent();
            $event->date = $item['date'];
            $event->haemoglobin = $item['haemoglobin'];
            $event->hbs_ag = $item['hbs_ag'];
            $event->hcv_ab = $item['hcv_ab'];
            $event->mp_ict = $item['mp_ict'];
            $event->retro_test = $item['retro_test'];
            $event->vdrl_test = $item['vdrl_test'];
            $event->lab_name = $item['lab_name'];
            $event->total = $item['total'];
            $event->save();
        }
    }

    protected function seedDonarRecords()
    {
        $data = Json::decode(file_get_contents('data/donar_records.json'));
        foreach ($data as $item) {
            $record = new DonarRecord();
            $record->amount = $item['amount'];
            $record->date = $item['date'];
            $record->name = $item['name'];
            $record->save();
        }
    }

    protected function seedExpensesRecords()
    {
        $data = Json::decode(file_get_contents('data/expenses_records.json'));
        foreach ($data as $item) {
            $expense = new ExpensesRecord();
            $expense->amount = $item['amount'];
            $expense->date = $item['date'];
            $expense->name = $item['name'];
            $expense->save();
        }
    }

    protected function seedRequestGives()
    {
        $data = Json::decode(file_get_contents('data/request_gives.json'));
        foreach ($data as $item) {
            $requestGive = new RequestGive();
            $requestGive->request = $item['request'];
            $requestGive->give = $item['give'];
            $requestGive->date = $item['date'];
            $requestGive->save();
        }
    }
}