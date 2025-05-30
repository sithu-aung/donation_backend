<?php

namespace app\controllers;

use app\models\DonarRecord;
use app\models\Donation;
use app\models\ExpensesRecord;
use app\models\Member;
use app\models\RequestGive;
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

    public function actionByDisease($limit = 8, $year = null, $month = null)
    {
        $query = Donation::find()
            ->select(['patient_disease', 'COUNT(*) as quantity']);

        // Apply year and month filters if provided
        if ($year !== null) {
            $query->andWhere("EXTRACT(YEAR FROM donation_date) = :year", [':year' => $year]);
        }
        if ($month !== null) {
            $query->andWhere("EXTRACT(MONTH FROM donation_date) = :month", [':month' => $month]);
        }

        $diseaseData = $query
            ->groupBy('patient_disease')
            ->orderBy(['quantity' => SORT_DESC])
            ->limit($limit)
            ->asArray()
            ->all();

        // Get total donations with same filters
        $totalQuery = Donation::find();
        if ($year !== null) {
            $totalQuery->andWhere("EXTRACT(YEAR FROM donation_date) = :year", [':year' => $year]);
        }
        if ($month !== null) {
            $totalQuery->andWhere("EXTRACT(MONTH FROM donation_date) = :month", [':month' => $month]);
        }
        $totalDonations = $totalQuery->count();

        // Map to Flutter expected format and calculate percentage
        foreach ($diseaseData as &$disease) {
            $disease['name'] = $disease['patient_disease'] ?? '';
            $disease['count'] = (int)$disease['quantity'];
            $disease['percentage'] = $totalDonations > 0 ? round(($disease['quantity'] / $totalDonations) * 100, 1) : 0;
            unset($disease['patient_disease']);
            unset($disease['quantity']);
        }

        return $this->asJson([
            'status' => 'ok',
            'data' => $diseaseData,
            'totalDonations' => $totalDonations,
        ]);
    }

    public function actionByGender($year = null, $month = null)
    {
        $query = Donation::find()
            ->select(['member.gender as patient_gender', 'COUNT(*) as quantity'])
            ->joinWith('member0');

        // Apply year and month filters if provided
        if ($year !== null) {
            $query->andWhere("EXTRACT(YEAR FROM donation.donation_date) = :year", [':year' => $year]);
        }
        if ($month !== null) {
            $query->andWhere("EXTRACT(MONTH FROM donation.donation_date) = :month", [':month' => $month]);
        }

        $genderData = $query
            ->groupBy('member.gender')
            ->asArray()
            ->all();

        // Get total donations with same filters
        $totalQuery = Donation::find();
        if ($year !== null) {
            $totalQuery->andWhere("EXTRACT(YEAR FROM donation_date) = :year", [':year' => $year]);
        }
        if ($month !== null) {
            $totalQuery->andWhere("EXTRACT(MONTH FROM donation_date) = :month", [':month' => $month]);
        }
        $totalDonations = $totalQuery->count();

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
                'percentage' => $totalDonations > 0 ? round(($femaleCount / $totalDonations) * 100) : 0,
            ],
            [
                'patient_gender' => 'male',
                'quantity' => $maleCount,
                'percentage' => $totalDonations > 0 ? round(($maleCount / $totalDonations) * 100) : 0,
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

    public function actionByBloodType($year = null, $month = null)
    {
        $query = Donation::find()
            ->select(['member.blood_type', 'COUNT(*) as quantity'])
            ->joinWith('member0');

        // Apply year and month filters if provided
        if ($year !== null) {
            $query->andWhere("EXTRACT(YEAR FROM donation.donation_date) = :year", [':year' => $year]);
        }
        if ($month !== null) {
            $query->andWhere("EXTRACT(MONTH FROM donation.donation_date) = :month", [':month' => $month]);
        }

        $bloodTypeData = $query
            ->groupBy('member.blood_type')
            ->orderBy(['member.blood_type' => SORT_ASC])
            ->asArray()
            ->all();

        // Get total donations with same filters
        $totalQuery = Donation::find();
        if ($year !== null) {
            $totalQuery->andWhere("EXTRACT(YEAR FROM donation_date) = :year", [':year' => $year]);
        }
        if ($month !== null) {
            $totalQuery->andWhere("EXTRACT(MONTH FROM donation_date) = :month", [':month' => $month]);
        }
        $totalDonations = $totalQuery->count();

        foreach ($bloodTypeData as &$bloodType) {
            $bloodType['percentage'] = $totalDonations > 0 ? round(($bloodType['quantity'] / $totalDonations) * 100, 1) : 0;
            unset($bloodType['member0']);
        }

        return $this->asJson([
            'status' => 'ok',
            'data' => $bloodTypeData,
            'totalDonations' => $totalDonations,
        ]);
    }

    public function actionByHospital($limit = 20, $year = null, $month = null)
    {
        $query = Donation::find()
            ->select(['hospital', 'COUNT(*) as quantity']);

        // Apply year and month filters if provided
        if ($year !== null) {
            $query->andWhere("EXTRACT(YEAR FROM donation_date) = :year", [':year' => $year]);
        }
        if ($month !== null) {
            $query->andWhere("EXTRACT(MONTH FROM donation_date) = :month", [':month' => $month]);
        }

        $hospitalData = $query
            ->groupBy('hospital')
            ->orderBy(['quantity' => SORT_DESC])
            ->limit($limit)
            ->asArray()
            ->all();

        // Get total donations with same filters
        $totalQuery = Donation::find();
        if ($year !== null) {
            $totalQuery->andWhere("EXTRACT(YEAR FROM donation_date) = :year", [':year' => $year]);
        }
        if ($month !== null) {
            $totalQuery->andWhere("EXTRACT(MONTH FROM donation_date) = :month", [':month' => $month]);
        }
        $totalDonations = $totalQuery->count();

        foreach ($hospitalData as &$hospital) {
            $hospital['percentage'] = $totalDonations > 0 ? round(($hospital['quantity'] / $totalDonations) * 100, 1) : 0;
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
            ->orderBy(['quantity' => SORT_DESC])
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

    public function actionByRequestGive()
    {
        // Group request give data by month and year using PostgreSQL-compatible functions
        $requestGiveData = RequestGive::find()
            ->select([
                'EXTRACT(YEAR FROM date) as year',
                'EXTRACT(MONTH FROM date) as month',
                'SUM(request) as request',
                'SUM(give) as give'
            ])
            ->groupBy(['EXTRACT(YEAR FROM date)', 'EXTRACT(MONTH FROM date)'])
            ->orderBy(['year' => SORT_ASC, 'month' => SORT_ASC])
            ->asArray()
            ->all();

        // Format the data for the chart
        $chartData = [];
        foreach ($requestGiveData as $data) {
            $chartData[] = [
                'year' => (int)$data['year'],
                'month' => (int)$data['month'],
                'request' => (int)$data['request'],
                'give' => (int)$data['give'],
            ];
        }

        return $this->asJson([
            'status' => 'ok',
            'data' => $chartData,
        ]);
    }

    public function actionDonationSummary($year = null, $month = null)
    {
        // Build base query with date filters
        $baseQuery = Donation::find();
        if ($year !== null) {
            $baseQuery->andWhere("EXTRACT(YEAR FROM donation_date) = :year", [':year' => $year]);
        }
        if ($month !== null) {
            $baseQuery->andWhere("EXTRACT(MONTH FROM donation_date) = :month", [':month' => $month]);
        }

        // Get total donations count
        $totalDonations = $baseQuery->count();

        // Get disease data
        $diseaseQuery = clone $baseQuery;
        $diseaseData = $diseaseQuery
            ->select(['patient_disease as name', 'COUNT(*) as count'])
            ->groupBy('patient_disease')
            ->orderBy(['count' => SORT_DESC])
            ->limit(8)
            ->asArray()
            ->all();

        // Clean disease data
        foreach ($diseaseData as &$disease) {
            $disease['name'] = $disease['name'] ?? '';
            $disease['count'] = (int)$disease['count'];
            $disease['percentage'] = $totalDonations > 0 ? round(($disease['count'] / $totalDonations) * 100, 1) : 0;
        }

        // Get blood type data
        $bloodTypeQuery = clone $baseQuery;
        $bloodTypeData = $bloodTypeQuery
            ->select(['member.blood_type', 'COUNT(*) as quantity'])
            ->joinWith('member0')
            ->groupBy('member.blood_type')
            ->orderBy(['member.blood_type' => SORT_ASC])
            ->asArray()
            ->all();

        // Format blood type data
        $bloodTypeMap = [];
        foreach ($bloodTypeData as $bloodType) {
            $bloodTypeMap[$bloodType['blood_type']] = [
                'quantity' => (int)$bloodType['quantity'],
                'percentage' => $totalDonations > 0 ? round(($bloodType['quantity'] / $totalDonations) * 100, 1) : 0
            ];
        }

        // Get hospital data
        $hospitalQuery = clone $baseQuery;
        $hospitalData = $hospitalQuery
            ->select(['hospital', 'COUNT(*) as quantity'])
            ->groupBy('hospital')
            ->orderBy(['quantity' => SORT_DESC])
            ->limit(10)
            ->asArray()
            ->all();

        // Format hospital data
        $hospitalList = [];
        foreach ($hospitalData as $hospital) {
            $hospitalList[] = [
                'hospital' => $hospital['hospital'] ?? '',
                'quantity' => (int)$hospital['quantity'],
                'percentage' => $totalDonations > 0 ? round(($hospital['quantity'] / $totalDonations) * 100, 1) : 0
            ];
        }

        // Get gender data
        $genderQuery = clone $baseQuery;
        $genderData = $genderQuery
            ->select(['member.gender as patient_gender', 'COUNT(*) as quantity'])
            ->joinWith('member0')
            ->groupBy('member.gender')
            ->asArray()
            ->all();

        $femaleCount = 0;
        $maleCount = 0;
        foreach ($genderData as $gender) {
            if ($gender['patient_gender'] === 'female') {
                $femaleCount += (int)$gender['quantity'];
            } elseif ($gender['patient_gender'] === 'male') {
                $maleCount += (int)$gender['quantity'];
            } elseif ($gender['patient_gender'] === null) {
                $femaleCount += (int)$gender['quantity'];
            }
        }

        $genderStats = [
            [
                'patient_gender' => 'female',
                'quantity' => $femaleCount,
                'percentage' => $totalDonations > 0 ? round(($femaleCount / $totalDonations) * 100) : 0,
            ],
            [
                'patient_gender' => 'male',
                'quantity' => $maleCount,
                'percentage' => $totalDonations > 0 ? round(($maleCount / $totalDonations) * 100) : 0,
            ],
        ];

        return $this->asJson([
            'status' => 'ok',
            'data' => [
                'totalDonations' => $totalDonations,
                'diseases' => $diseaseData,
                'bloodTypes' => $bloodTypeMap,
                'hospitals' => $hospitalList,
                'genderStats' => $genderStats,
                'period' => [
                    'year' => $year,
                    'month' => $month,
                ]
            ],
        ]);
    }

    public function actionBloodDonationReport($year = null, $month = null)
    {
        // Build base query with date filters
        $baseQuery = Donation::find();
        if ($year !== null) {
            $baseQuery->andWhere("EXTRACT(YEAR FROM donation_date) = :year", [':year' => $year]);
        }
        if ($month !== null) {
            $baseQuery->andWhere("EXTRACT(MONTH FROM donation_date) = :month", [':month' => $month]);
        }

        // Get total donations count
        $totalDonations = $baseQuery->count();

        // Get blood type statistics with member join
        $bloodTypeQuery = clone $baseQuery;
        $bloodTypeData = $bloodTypeQuery
            ->select(['member.blood_type', 'COUNT(*) as count'])
            ->joinWith('member0')
            ->groupBy('member.blood_type')
            ->orderBy(['member.blood_type' => SORT_ASC])
            ->asArray()
            ->all();

        // Process blood type data
        $bloodTypeStats = [];
        foreach ($bloodTypeData as $item) {
            $bloodType = $item['blood_type'] ?? 'Unknown';
            $count = (int)$item['count'];
            $percentage = $totalDonations > 0 ? round(($count / $totalDonations) * 100, 1) : 0;
            
            $bloodTypeStats[] = [
                'blood_type' => $bloodType,
                'count' => $count,
                'percentage' => $percentage
            ];
        }

        // Get disease statistics
        $diseaseQuery = clone $baseQuery;
        $diseaseData = $diseaseQuery
            ->select(['patient_disease', 'COUNT(*) as count'])
            ->groupBy('patient_disease')
            ->orderBy(['count' => SORT_DESC])
            ->limit(10)
            ->asArray()
            ->all();

        // Process disease data
        $diseaseStats = [];
        foreach ($diseaseData as $item) {
            $disease = $item['patient_disease'] ?? 'Unknown';
            $count = (int)$item['count'];
            $percentage = $totalDonations > 0 ? round(($count / $totalDonations) * 100, 1) : 0;
            
            $diseaseStats[] = [
                'disease' => $disease,
                'count' => $count,
                'percentage' => $percentage
            ];
        }

        // Get hospital statistics
        $hospitalQuery = clone $baseQuery;
        $hospitalData = $hospitalQuery
            ->select(['hospital', 'COUNT(*) as count'])
            ->groupBy('hospital')
            ->orderBy(['count' => SORT_DESC])
            ->limit(10)
            ->asArray()
            ->all();

        // Process hospital data
        $hospitalStats = [];
        foreach ($hospitalData as $item) {
            $hospital = $item['hospital'] ?? 'Unknown';
            $count = (int)$item['count'];
            $percentage = $totalDonations > 0 ? round(($count / $totalDonations) * 100, 1) : 0;
            
            $hospitalStats[] = [
                'hospital' => $hospital,
                'count' => $count,
                'percentage' => $percentage
            ];
        }

        // Get monthly breakdown if showing yearly data
        $monthlyBreakdown = [];
        if ($year !== null && $month === null) {
            $monthlyQuery = Donation::find()
                ->select([
                    'EXTRACT(MONTH FROM donation_date) as month',
                    'COUNT(*) as count'
                ])
                ->where("EXTRACT(YEAR FROM donation_date) = :year", [':year' => $year])
                ->groupBy('EXTRACT(MONTH FROM donation_date)')
                ->orderBy(['month' => SORT_ASC])
                ->asArray()
                ->all();

            foreach ($monthlyQuery as $item) {
                $monthlyBreakdown[] = [
                    'month' => (int)$item['month'],
                    'count' => (int)$item['count']
                ];
            }
        }

        return $this->asJson([
            'status' => 'ok',
            'data' => [
                'totalDonations' => $totalDonations,
                'bloodTypes' => $bloodTypeStats,
                'diseases' => $diseaseStats,
                'hospitals' => $hospitalStats,
                'monthlyBreakdown' => $monthlyBreakdown,
                'period' => [
                    'year' => $year ? (int)$year : null,
                    'month' => $month ? (int)$month : null,
                    'isYearly' => $month === null
                ]
            ],
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
