<?php

namespace app\commands;

use app\models\Account;
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
            $this->seedAccounts();
            $this->seedMembers();
            $this->seedDonations();
            $this->seedSpecialEvents();
            $this->seedDonarRecords();
            $this->seedExpensesRecords();
            $this->seedRequestGives();
            $transaction->commit();
        } catch (\Exception $e) {
            $transaction->rollBack();
            Yii::error("Error in ImportController: " . $e->getMessage(), __METHOD__);
            throw $e;
        }
    }

    protected function seedAccounts()
    {
        $data = Json::decode(file_get_contents('data/User.json'));
        foreach ($data as $item) {
            $account = new Account();
            $account->name = $item['name'];
            $account->email = $item['email'];
            $account->password_hash = Yii::$app->security->generatePasswordHash($item['password']);
            $account->access_token = Yii::$app->security->generateRandomString(64);
            $account->phone = $item['phone'];
            $account->save();
        }
    }

    protected function seedMembers()
    {
        $data = Json::decode(file_get_contents('data/Member.json'));
        // Sort the data by memberId
        usort($data, function ($a, $b) {
            return strcmp($a['memberId'], $b['memberId']);
        });
        foreach ($data as $item) {
            $member = new Member();
            $member->birth_date = $item['birthDate'] ?? null;
            $member->blood_bank_card = $item['bloodBankCard'] ?? null;
            $member->blood_type = $item['bloodType'] ?? null;
            $member->father_name = $item['fatherName'] ?? null;
            $member->last_date = $item['lastDate'] ?? null;
            $member->member_count = $item['memberCount'] ?? null;
            $member->member_id = $item['memberId'] ?? null;
            $member->name = $item['name'] ?? null;
            $member->note = $item['note'] ?? null;
            $member->nrc = $item['nrc'] ?? null;
            $member->phone = $item['phone'] ?? null;
            $member->address = $item['address'] ?? null;
            $member->gender = $item['gender'] ?? null;
            $member->profile_url = $item['profile_url'] ?? null;
            $member->register_date = $item['registerDate'] ?? null;
            $member->total_count = $item['totalCount'] ?? null;
            $member->status = $item['status'] ?? null;
            $member->owner_id = $item['owner_id'] ?? null;
            $member->save();
        }
    }

    protected function seedDonations()
    {
        $data = Json::decode(file_get_contents('data/Donation.json'));
        foreach ($data as $item) {
            $donation = new Donation();
            $donation->date = $item['date'] ?? null;
            $donation->donation_date = $item['donationDate'] ?? null;
            $donation->hospital = $item['hospital'] ?? null;
            $donation->member_id = $item['memberId'] ?? null;
            $member = Member::findOne(['member_id' => $item['memberId']]);
            $donation->member = $member->id;
            
            if ($donation->member === null) {
                echo "Member not found for memberId: " . $item['memberId'] . "\n";
                continue; // Skip this record
            }

            $donation->patient_address = $item['patientAddress'] ?? null;
            $donation->patient_age = $item['patientAge'] ?? null;
            $donation->patient_disease = $item['patientDisease'] ?? null;
            $donation->patient_name = $item['patientName'] ?? null;
            $donation->owner_id = $item['owner_id'] ?? null;

            if (!$donation->save()) {
                echo "Failed to save donation for memberId: " . $item['memberId'] . "\n";
                print_r($donation->getErrors());
            }
        }
    }

    protected function seedSpecialEvents()
    {
        $data = Json::decode(file_get_contents('data/SpecialEvent.json'));
        foreach ($data as $item) {
            $event = new SpecialEvent();
            $event->date = $item['date'] ?? null;
            $event->haemoglobin = $item['haemoglobin'] ?? null;
            $event->hbs_ag = $item['hbsAg'] ?? null; 
            $event->hcv_ab = $item['hcvAb'] ?? null; 
            $event->mp_ict = $item['mpIct'] ?? null; 
            $event->retro_test = $item['retroTest'] ?? null; 
            $event->vdrl_test = $item['vdrlTest'] ?? null; 
            $event->lab_name = $item['labName'] ?? null; 
            $event->total = $item['total'] ?? null;
            $event->save();
        }
    }

    protected function seedDonarRecords()
    {
        $data = Json::decode(file_get_contents('data/DonarRecord.json'));
        foreach ($data as $item) {
            $record = new DonarRecord();
            $record->amount = $item['amount'] ?? null;
            $record->date = $item['date'] ?? null;
            $record->name = $item['name'] ?? null;
            $record->save();
        }
    }

    protected function seedExpensesRecords()
    {
        $data = Json::decode(file_get_contents('data/ExpensesRecord.json'));
        foreach ($data as $item) {
            $expense = new ExpensesRecord();
            $expense->amount = $item['amount'] ?? null;
            $expense->date = $item['date'] ?? null;
            $expense->name = $item['name'] ?? null;
            $expense->save();
        }
    }

    protected function seedRequestGives()
    {
        $data = Json::decode(file_get_contents('data/RequestGive.json'));
        foreach ($data as $item) {
            $requestGive = new RequestGive();
            $requestGive->request = $item['request'] ?? null;
            $requestGive->give = $item['give'] ?? null;
            $requestGive->date = $item['date'] ?? null;
            $requestGive->save();
        }
    }
}
