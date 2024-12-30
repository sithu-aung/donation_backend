<?php

namespace app\controllers;

use app\models\DonarRecord;
use app\models\Donation;
use app\models\ExpensesRecord;
use app\models\Member;
use app\models\SpecialEvent;

class ReportController extends BaseAuthController
{
    public function actionDashboard()
    {
        // Fetch data from providers
        $totalMember = $this->getTotalMembers();
        $totalDonations = $this->getTotalDonations();
        $totalExpenses = $this->getTotalExpenses();
        $donations = $this->getBloodDonations();
        $totalPatient = $this->getTotalPatients();

        // Return the data as JSON
        return $this->asJson([
            'status' => 'ok',
            'data' => [
                'totalMember' => $totalMember,
                'totalDonations' => $totalDonations,
                'totalExpenses' => $totalExpenses,
                'donations' => $donations,
                'totalPatient' => $totalPatient,
            ],
        ]);
    }

    public function actionByDisease($limit = 8)
    {
        $totalDonations = $this->getBloodDonations();

        $diseaseData = Donation::find()
            ->select(['patient_disease', 'COUNT(*) as quantity'])
            ->groupBy('patient_disease')
            ->orderBy(['quantity' => SORT_DESC])
            ->limit($limit)
            ->asArray()
            ->all();

        foreach ($diseaseData as &$disease) {
            $disease['percentage'] = round(($disease['quantity'] / $totalDonations) * 100);
        }

        return $this->asJson([
            'status' => 'ok',
            'data' => $diseaseData,
            'totalDonations' => $totalDonations,
        ]);
    }

    public function actionByGender()
    {
        $totalDonations = $this->getBloodDonations();

        $genderData = Donation::find()
            ->select(['member.gender as patient_gender', 'COUNT(*) as quantity'])
            ->joinWith('member0')
            ->groupBy('member.gender')
            ->asArray()
            ->all();

        // Initialize variables to hold the counts
        $femaleCount = 0;
        $maleCount = 0;

        // Process the gender data
        foreach ($genderData as &$gender) {
            if ($gender['patient_gender'] === 'female') {
                $femaleCount += $gender['quantity'];
            } elseif ($gender['patient_gender'] === 'male') {
                $maleCount += $gender['quantity'];
            } elseif ($gender['patient_gender'] === null) {
                // Merge null gender count into female count
                $femaleCount += $gender['quantity'];
            }
        }

        // Rebuild the genderData array with updated counts
        $genderData = [
            [
                'patient_gender' => 'female',
                'quantity' => $femaleCount,
                'percentage' => round(($femaleCount / $totalDonations) * 100),
            ],
            [
                'patient_gender' => 'male',
                'quantity' => $maleCount,
                'percentage' => round(($maleCount / $totalDonations) * 100),
            ],
        ];

        $members = Member::find()->all();
        $ages = array_map(function ($member) {
            $birthDate = \DateTime::createFromFormat('d M Y', $member->birth_date);
            if ($birthDate === false) {
                return null;
            }
            $currentDate = new \DateTime();
            $age = $currentDate->diff($birthDate)->y;
            return $age;
        }, $members);

        $ages = array_filter($ages);
        $averageAge = round(count($ages) > 0 ? array_sum($ages) / count($ages) : 0);

        $ageRanges = [
            '18-25' => 0,
            '26-35' => 0,
            '36-45' => 0,
            '46+' => 0,
        ];

        // Calculate donation quantities for each age range
        foreach ($members as $member) {
            $birthDate = \DateTime::createFromFormat('d M Y', $member->birth_date);
            if ($birthDate !== false) {
                $currentDate = new \DateTime();
                $age = $currentDate->diff($birthDate)->y;
                if ($age >= 18 && $age <= 25) {
                    $ageRanges['18-25']++;
                } elseif ($age >= 26 && $age <= 35) {
                    $ageRanges['26-35']++;
                } elseif ($age >= 36 && $age <= 45) {
                    $ageRanges['36-45']++;
                } elseif ($age >= 46) {
                    $ageRanges['46+']++;
                }
            }
        }

        return $this->asJson([
            'status' => 'ok',
            'data' => $genderData,
            'averageAge' => $averageAge,
            'ageRanges' => $ageRanges,
            'totalDonations' => $totalDonations,
            'totalMembers' => $this->getTotalMembers(),
        ]);
    }

    public function actionByBloodType()
    {
        $totalDonations = $this->getBloodDonations();

        $bloodTypeData = Donation::find()
            ->select(['member.blood_type', 'COUNT(*) as quantity'])
            ->joinWith('member0')
            ->groupBy('member.blood_type')
            ->orderBy(['member.blood_type' => SORT_ASC])
            ->asArray()
            ->all();

        foreach ($bloodTypeData as &$bloodType) {
            $bloodType['percentage'] = round(($bloodType['quantity'] / $totalDonations) * 100, 1);
            unset($bloodType['member0']);
        }

        return $this->asJson([
            'status' => 'ok',
            'data' => $bloodTypeData,
            'totalDonations' => $totalDonations,
        ]);
    }

    public function actionByHospital($limit = 20)
    {
        $totalDonations = $this->getBloodDonations();

        $hospitalData = Donation::find()
            ->select(['hospital', 'COUNT(*) as quantity'])
            ->groupBy('hospital')
            ->orderBy(['quantity' => SORT_DESC])
            ->limit($limit)
            ->asArray()
            ->all();

        foreach ($hospitalData as &$hospital) {
            $hospital['percentage'] = round(($hospital['quantity'] / $totalDonations) * 100, 1);
        }

        return $this->asJson([
            'status' => 'ok',
            'data' => $hospitalData,
            'totalDonations' => $totalDonations,

        ]);
    }

    public function actionByLabName()
    {
        // Get total count of special events
        $totalEvents = SpecialEvent::find()->count();

        $labData = SpecialEvent::find()
            ->select(['lab_name', 'COUNT(*) as quantity'])
            ->groupBy('lab_name')
            ->orderBy(['lab_name' => SORT_ASC])
            ->asArray()
            ->all();

        // Calculate percentage for each lab
        foreach ($labData as &$lab) {
            $lab['percentage'] = round(($lab['quantity'] / $totalEvents) * 100, 1);
        }

        return $this->asJson([
            'status' => 'ok',
            'data' => $labData,
            'totalEvents' => $totalEvents,
        ]);
    }

    protected function getTotalMembers()
    {
        $member = Member::find()->count();
        return $member;
    }

    protected function getTotalDonations()
    {
        $donations = DonarRecord::find()->sum('amount');
        return $donations;
    }

    protected function getTotalExpenses()
    {
        $expenses = ExpensesRecord::find()->sum('amount');
        return $expenses;
    }

    protected function getBloodDonations()
    {
        $donations = Donation::find()->count();
        return $donations;
    }

    protected function getTotalPatients()
    {
        $patients = Donation::find()
            ->select('patient_name')
            ->distinct()
            ->count();
        return $patients;
    }
}
