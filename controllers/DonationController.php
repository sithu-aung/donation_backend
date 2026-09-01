<?php

namespace app\controllers;

use app\models\AccountToken;
use app\models\Donation;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\ConflictHttpException;
use yii\web\UnauthorizedHttpException;

class DonationController extends BaseApiController
{
    private const FACEBOOK_POST_TIMES = [
        'မနက်စောစော',
        'မနက်ပိုင်း',
        'ဒီနေ့မနက်',
        'နေ့လယ်ပိုင်း',
        'ဒီနေ့နေ့လယ်',
        'ညနေပိုင်း',
        'ဒီနေ့ညနေ',
        'ညနေစောင်း',
        'ဒီနေ့ည',
    ];

    public function actionIndex($page, $limit, $q = '', $order = 'desc', $disease = '', $hospital = '', $year = '')
    {
        $query = Donation::find()
            ->with(['member0' => function($query) {
                $query->select(['id', 'name', 'blood_type', 'phone', 'address', 'nrc', 'father_name','blood_bank_card','birth_date','member_id']);
            }])
            ->with(['patient' => function($query) {
                $query->select(['id', 'name', 'phone', 'address', 'age', 'gender']);
            }]);

        // Apply filters
        if ($q) {
            $query->andWhere(['like', 'patient_name', $q]);
        }
        if ($disease) {
            $query->andWhere(['patient_disease' => $disease]);
        }
        if ($hospital) {
            $query->andWhere(['hospital' => $hospital]);
        }
        if ($year) {
            $query->andWhere("date_part('year', donation_date) = :year", [':year' => $year]);
        }

        // Get total count after applying filters
        $count = $query->count();

        $hospitals = Donation::find()
            ->select('hospital')
            ->distinct()
            ->where(['not', ['hospital' => null]])
            ->column();

        $diseases = Donation::find()
            ->select('patient_disease')
            ->distinct()
            ->where(['not', ['patient_disease' => null]])
            ->column();

        // Convert order parameter to SORT_ASC or SORT_DESC
        $direction = strtolower($order) === 'desc' ? SORT_DESC : SORT_ASC;
        $query = $query->offset($page * $limit)
            ->limit($limit)
            ->orderBy(['id' => $direction]);

        // Get donation data with related member information
        $donations = $query->asArray()->all();

        // Map member data to memberObj for frontend compatibility
        foreach ($donations as &$donation) {
            if (isset($donation['member0'])) {
                $donation['memberObj'] = $donation['member0'];
            }
        }

        return $this->asJson([
            'status' => 'ok',
            'data' => $donations,
            'total' => $count,
            'hospitals' => $hospitals,
            'diseases' => $diseases,
            'page' => $page,
            'limit' => $limit,
            'hasMore' => ($page * $limit + $limit) < $count,
        ]);
    }

    public function actionByMonthYear($month, $year, $page = 0, $limit = 500)
    {
        $query = Donation::find()
            ->with(['member0' => function($query) {
                $query->select(['id', 'name', 'blood_type', 'phone', 'address', 'nrc', 'father_name','blood_bank_card','birth_date','member_id']);
            }])
            ->where("date_part('month', donation_date) = :month", [':month' => $month])
            ->andWhere("date_part('year', donation_date) = :year", [':year' => $year]);

        // Get total count
        $count = $query->count();

        // Apply pagination
        $query = $query->offset($page * $limit)
            ->limit($limit)
            ->orderBy(['donation_date' => SORT_ASC, 'id' => SORT_ASC]);

        // Get donation data with related member information
        $donations = $query->asArray()->all();

        // Map member data to memberObj for frontend compatibility
        foreach ($donations as &$donation) {
            if (isset($donation['member0'])) {
                $donation['memberObj'] = $donation['member0'];
            }
        }

        return $this->asJson([
            'status' => 'ok',
            'data' => $donations,
            'total' => $count,
            'page' => $page,
            'limit' => $limit,
            'hasMore' => ($page * $limit + $limit) < $count,
        ]);
    }

    /**
     * Return every donation recorded on one exact local calendar date.
     *
     * A half-open timestamp range keeps records with any time on the selected
     * date and avoids wrapping donation_date in a SQL function.
     */
    public function actionByDate($date, $page = 0, $limit = 100)
    {
        $selectedDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $dateErrors = \DateTimeImmutable::getLastErrors();
        $hasDateErrors = is_array($dateErrors)
            && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0);

        if ($selectedDate === false || $hasDateErrors || $selectedDate->format('Y-m-d') !== $date) {
            throw new BadRequestHttpException('Date must use the YYYY-MM-DD format.');
        }

        $page = max(0, (int) $page);
        $limit = min(500, max(1, (int) $limit));
        $nextDate = $selectedDate->modify('+1 day');

        $query = Donation::find()
            ->with(['member0' => function($query) {
                $query->select(['id', 'name', 'blood_type', 'phone', 'address', 'nrc', 'father_name','blood_bank_card','birth_date','member_id']);
            }])
            ->andWhere(['>=', 'donation_date', $selectedDate->format('Y-m-d H:i:s')])
            ->andWhere(['<', 'donation_date', $nextDate->format('Y-m-d H:i:s')]);

        $count = $query->count();
        $donations = $query
            ->offset($page * $limit)
            ->limit($limit)
            ->orderBy(['donation_date' => SORT_ASC, 'id' => SORT_ASC])
            ->asArray()
            ->all();

        foreach ($donations as &$donation) {
            if (isset($donation['member0'])) {
                $donation['memberObj'] = $donation['member0'];
            }
        }

        return $this->asJson([
            'status' => 'ok',
            'data' => $donations,
            'total' => $count,
            'page' => $page,
            'limit' => $limit,
            'hasMore' => ($page * $limit + $limit) < $count,
        ]);
    }

    public function actionByYear($year, $page = 0, $limit = 500)
    {
        $query = Donation::find()
            ->with(['member0' => function($query) {
                $query->select(['id', 'name', 'blood_type', 'phone', 'address', 'nrc', 'father_name','blood_bank_card','birth_date','member_id']);
            }])
            ->where("date_part('year', donation_date) = :year", [':year' => $year]);

        // Get total count
        $count = $query->count();

        // Apply pagination
        $query = $query->offset($page * $limit)
            ->limit($limit)
            ->orderBy(['donation_date' => SORT_DESC]);

        // Get donation data with related member information
        $donations = $query->asArray()->all();

        // Map member data to memberObj for frontend compatibility
        foreach ($donations as &$donation) {
            if (isset($donation['member0'])) {
                $donation['memberObj'] = $donation['member0'];
            }
        }

        return $this->asJson([
            'status' => 'ok',
            'data' => $donations,
            'total' => $count,
            'page' => $page,
            'limit' => $limit,
            'hasMore' => ($page * $limit + $limit) < $count,
        ]);
    }

    /**
     * Persist one Facebook-post time phrase for every donation in a patient
     * group. One SQL update keeps grouped donor rows consistent.
     */
    public function actionSavePostTime()
    {
        $this->requireFacebookPostAuthentication();

        $rawDonationIds = Yii::$app->request->post('donation_ids');
        if (!is_array($rawDonationIds) || $rawDonationIds === []) {
            throw new BadRequestHttpException('donation_ids must be a non-empty array.');
        }

        $donationIds = [];
        foreach ($rawDonationIds as $rawDonationId) {
            if (is_int($rawDonationId)) {
                $donationId = $rawDonationId;
            } elseif (is_string($rawDonationId) && ctype_digit($rawDonationId)) {
                $donationId = (int) $rawDonationId;
            } else {
                throw new BadRequestHttpException('Every donation_id must be a positive integer.');
            }

            if ($donationId <= 0) {
                throw new BadRequestHttpException('Every donation_id must be a positive integer.');
            }
            $donationIds[] = $donationId;
        }
        $donationIds = array_values(array_unique($donationIds));

        $postTime = Yii::$app->request->post('time_of_day');
        if (!is_string($postTime) || $postTime === '') {
            throw new BadRequestHttpException('time_of_day is required.');
        }

        if (!in_array($postTime, self::FACEBOOK_POST_TIMES, true)
            && !$this->isValidCustomNightTime($postTime)) {
            throw new BadRequestHttpException('time_of_day is not a supported Facebook post time.');
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $groupDonationIds = $this->findFacebookPostGroupIds($donationIds);
            $updatedCount = Donation::updateAll(
                ['facebook_post_time' => $postTime],
                ['id' => $groupDonationIds]
            );
            if ($updatedCount !== count($groupDonationIds)) {
                throw new ConflictHttpException(
                    'The donation group changed while its post time was being saved.'
                );
            }
            $transaction->commit();
        } catch (\Throwable $error) {
            if ($transaction->isActive) {
                $transaction->rollBack();
            }
            throw $error;
        }

        return $this->asJson([
            'status' => 'ok',
            'data' => [
                'donation_ids' => $groupDonationIds,
                'time_of_day' => $postTime,
                'updated_count' => $updatedCount,
            ],
        ]);
    }

    private function requireFacebookPostAuthentication(): void
    {
        $authHeader = Yii::$app->request->headers->get('Authorization');
        if (!is_string($authHeader)
            || !preg_match('/^Bearer\s+(\S+)$/i', trim($authHeader), $matches)
            || AccountToken::findAccountByToken($matches[1]) === null) {
            throw new UnauthorizedHttpException('A valid staff session is required.');
        }
    }

    /**
     * Verify that the submitted rows still form one UI card, then expand the
     * stale client snapshot to every current row in that date/patient/hospital
     * group. This prevents a partial batch from leaving collaborators with
     * conflicting saved times.
     *
     * @param int[] $donationIds
     * @return int[]
     */
    private function findFacebookPostGroupIds(array $donationIds): array
    {
        $donations = Donation::find()
            ->select([
                'id',
                'donation_date',
                'patient_id',
                'patient_name',
                'hospital',
            ])
            ->where(['id' => $donationIds])
            ->orderBy(['id' => SORT_ASC])
            ->asArray()
            ->all();

        if (count($donations) !== count($donationIds)) {
            throw new BadRequestHttpException(
                'One or more donations no longer exist.'
            );
        }

        $first = $donations[0];
        $date = $this->facebookPostDate($first['donation_date']);
        $patientId = $this->nullableInt($first['patient_id']);
        $patientName = trim((string) ($first['patient_name'] ?? ''));
        $hospital = trim((string) ($first['hospital'] ?? ''));

        if ($patientId === null && $patientName === '') {
            throw new BadRequestHttpException(
                'The donation group has no patient identity.'
            );
        }

        foreach ($donations as $donation) {
            $samePatient = $patientId !== null
                ? $this->nullableInt($donation['patient_id']) === $patientId
                : $this->nullableInt($donation['patient_id']) === null
                    && trim((string) ($donation['patient_name'] ?? '')) === $patientName;

            if ($this->facebookPostDate($donation['donation_date']) !== $date
                || trim((string) ($donation['hospital'] ?? '')) !== $hospital
                || !$samePatient) {
                throw new BadRequestHttpException(
                    'donation_ids must belong to one date, patient, and hospital.'
                );
            }
        }

        $nextDate = (new \DateTimeImmutable($date))->modify('+1 day');
        $query = Donation::find()
            ->select('id')
            ->andWhere(['>=', 'donation_date', "$date 00:00:00"])
            ->andWhere(['<', 'donation_date', $nextDate->format('Y-m-d 00:00:00')])
            ->andWhere(
                "TRIM(COALESCE(hospital, '')) = :facebookPostHospital",
                [':facebookPostHospital' => $hospital]
            );

        if ($patientId !== null) {
            $query->andWhere(['patient_id' => $patientId]);
        } else {
            $query
                ->andWhere(['patient_id' => null])
                ->andWhere(
                    "TRIM(COALESCE(patient_name, '')) = :facebookPostPatient",
                    [':facebookPostPatient' => $patientName]
                );
        }

        return array_map('intval', $query->orderBy(['id' => SORT_ASC])->column());
    }

    private function facebookPostDate(mixed $rawDate): string
    {
        $date = substr((string) $rawDate, 0, 10);
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $dateErrors = \DateTimeImmutable::getLastErrors();
        $hasDateErrors = is_array($dateErrors)
            && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0);

        if ($parsed === false || $hasDateErrors || $parsed->format('Y-m-d') !== $date) {
            throw new BadRequestHttpException(
                'The donation group contains an invalid date.'
            );
        }

        return $date;
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private function isValidCustomNightTime(string $postTime): bool
    {
        if (!preg_match('/^ည\(([၀-၉]{1,2}):([၀-၉]{2})\)$/u', $postTime, $matches)) {
            return false;
        }

        $myanmarToAscii = [
            '၀' => '0', '၁' => '1', '၂' => '2', '၃' => '3', '၄' => '4',
            '၅' => '5', '၆' => '6', '၇' => '7', '၈' => '8', '၉' => '9',
        ];
        $hour = (int) strtr($matches[1], $myanmarToAscii);
        $minute = (int) strtr($matches[2], $myanmarToAscii);

        return $hour >= 1 && $hour <= 12 && $minute >= 0 && $minute <= 59;
    }

    public function actionView($id)
    {
        $donation = Donation::find()
            ->with('member0')
            ->with('patient')
            ->where(['id' => $id])
            ->asArray()
            ->one();
        if ($donation === null) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'No Donation Found.',
            ]);
        }

        return $this->asJson([
            'status' => 'ok',
            'data' => $donation,
        ]);
    }

    public function actionCreate()
    {
        $donation = new Donation();
        $donation->date = Yii::$app->request->post('date');

        // Handle donation_date without timezone conversion
        $donationDate = Yii::$app->request->post('donation_date');
        if ($donationDate) {
            // Store the datetime exactly as provided from the client
            $dateObj = new \DateTime($donationDate);
            $donation->donation_date = $dateObj->format('Y-m-d H:i:s');

            // Debug logging in development
            Yii::debug("Original date preserved: {$donationDate}, Stored as: {$donation->donation_date}");
        } else {
            $donation->donation_date = null;
        }

        $donation->hospital = Yii::$app->request->post('hospital');
        $donation->member_id = Yii::$app->request->post('member_id');
        $donation->member = Yii::$app->request->post('member');
        $donation->patient_address = Yii::$app->request->post('patient_address');
        $donation->patient_age = Yii::$app->request->post('patient_age');
        $donation->patient_disease = Yii::$app->request->post('patient_disease');
        $donation->patient_name = Yii::$app->request->post('patient_name');
        $donation->patient_id = Yii::$app->request->post('patient_id');
        $donation->owner_id = Yii::$app->request->post('owner_id');

        if (!$donation->save()) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'Failed to create Donation.',
                'errors' => $donation->errors,
            ]);
        }

        // Reload with relations
        $donation = Donation::find()
            ->with('member0')
            ->with('patient')
            ->where(['id' => $donation->id])
            ->asArray()
            ->one();

        return $this->asJson([
            'status' => 'ok',
            'data' => $donation
        ]);
    }

    public function actionUpdate($id)
    {
        $donation = Donation::findOne($id);
        if ($donation === null) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'No Donation Found.',
            ]);
        }

        $donation->date = Yii::$app->request->post('date');

        // Handle donation_date without timezone conversion
        $donationDate = Yii::$app->request->post('donation_date');
        if ($donationDate) {
            // Store the datetime exactly as provided from the client
            $dateObj = new \DateTime($donationDate);
            $donation->donation_date = $dateObj->format('Y-m-d H:i:s');

            // Debug logging in development
            Yii::debug("Original date preserved: {$donationDate}, Stored as: {$donation->donation_date}");
        } else {
            $donation->donation_date = null;
        }

        $donation->hospital = Yii::$app->request->post('hospital');
        $donation->member_id = Yii::$app->request->post('member_id');
        $donation->member = Yii::$app->request->post('member');
        $donation->patient_address = Yii::$app->request->post('patient_address');
        $donation->patient_age = Yii::$app->request->post('patient_age');
        $donation->patient_disease = Yii::$app->request->post('patient_disease');
        $donation->patient_name = Yii::$app->request->post('patient_name');
        // Only touch the patient link when the client actually sends it, so
        // older app builds that omit patient_id cannot wipe an existing link.
        $patientId = Yii::$app->request->post('patient_id', false);
        if ($patientId !== false) {
            $donation->patient_id = $patientId === '' ? null : $patientId;
        }
        $donation->owner_id = Yii::$app->request->post('owner_id');

        if (!$donation->save()) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'Failed to update Donation.',
                'errors' => $donation->errors,
            ]);
        }

        // Reload with relations
        $donation = Donation::find()
            ->with('member0')
            ->with('patient')
            ->where(['id' => $donation->id])
            ->asArray()
            ->one();

        return $this->asJson([
            'status' => 'ok',
            'data' => $donation
        ]);
    }

    public function actionDelete($id)
    {
        $donation = Donation::findOne($id);
        if ($donation === null) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'No Donation Found.',
            ]);
        }
        if (!$donation->delete()) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'Failed to delete Donation.',
            ]);
        }

        return $this->asJson([
            'status' => 'ok',
            'message' => 'Donation is deleted.'
        ]);
    }

    public function actionPatientList($page = 0, $limit = 20, $q = '', $order = 'desc')
    {
        $offset = $page * $limit;
        $orderDirection = strtolower($order) === 'desc' ? 'DESC' : 'ASC';
        
        // Build search condition
        $searchCondition = '';
        $params = [];
        if (!empty($q)) {
            $searchCondition = "AND (d.patient_name ILIKE :q 
                              OR d.patient_disease ILIKE :q 
                              OR d.hospital ILIKE :q 
                              OR d.patient_address ILIKE :q)";
            $params[':q'] = '%' . $q . '%';
        }
        
        // Get unique patients with their latest donation info and count
        $sql = "
            WITH latest_donations AS (
                SELECT 
                    patient_name,
                    patient_age,
                    patient_address,
                    patient_disease,
                    hospital,
                    member_id,
                    id as latest_id,
                    donation_date as latest_donation_date,
                    ROW_NUMBER() OVER (PARTITION BY patient_name ORDER BY donation_date DESC) as rn
                FROM donation
                WHERE patient_name IS NOT NULL AND patient_name != ''
            ),
            patient_stats AS (
                SELECT 
                    patient_name,
                    COUNT(*) as donation_count
                FROM donation
                WHERE patient_name IS NOT NULL AND patient_name != ''
                GROUP BY patient_name
            )
            SELECT 
                ld.patient_name,
                ld.patient_age,
                ld.patient_address,
                ld.patient_disease,
                ld.hospital,
                m.blood_type as blood_group,
                ld.latest_id,
                ld.latest_donation_date,
                ps.donation_count
            FROM latest_donations ld
            LEFT JOIN member m ON ld.member_id = m.member_id
            LEFT JOIN patient_stats ps ON ld.patient_name = ps.patient_name
            WHERE ld.rn = 1
        ";
        
        // Count total unique patients for pagination
        $countSql = "
            SELECT COUNT(DISTINCT patient_name) as count
            FROM donation d
            WHERE patient_name IS NOT NULL AND patient_name != ''
            $searchCondition
        ";
        
        $count = Yii::$app->db->createCommand($countSql, $params)->queryScalar();
        
        // Add search condition to main query
        if (!empty($searchCondition)) {
            $sql = "
                WITH latest_donations AS (
                    SELECT 
                        d.patient_name,
                        d.patient_age,
                        d.patient_address,
                        d.patient_disease,
                        d.hospital,
                        d.member_id,
                        d.id as latest_id,
                        d.donation_date as latest_donation_date,
                        ROW_NUMBER() OVER (PARTITION BY d.patient_name ORDER BY d.donation_date DESC) as rn
                    FROM donation d
                    WHERE d.patient_name IS NOT NULL AND d.patient_name != ''
                    $searchCondition
                ),
                patient_stats AS (
                    SELECT 
                        patient_name,
                        COUNT(*) as donation_count
                    FROM donation
                    WHERE patient_name IS NOT NULL AND patient_name != ''
                    GROUP BY patient_name
                )
                SELECT 
                    ld.patient_name,
                    ld.patient_age,
                    ld.patient_address,
                    ld.patient_disease,
                    ld.hospital,
                    m.blood_type as blood_group,
                    ld.latest_id,
                    ld.latest_donation_date,
                    ps.donation_count
                FROM latest_donations ld
                LEFT JOIN member m ON ld.member_id = m.member_id
                LEFT JOIN patient_stats ps ON ld.patient_name = ps.patient_name
                WHERE ld.rn = 1
            ";
        }
        
        // Add ordering and pagination
        $sql .= " ORDER BY ld.latest_donation_date $orderDirection
                  LIMIT :limit OFFSET :offset";
        
        $params[':limit'] = $limit;
        $params[':offset'] = $offset;
        
        $patients = Yii::$app->db->createCommand($sql, $params)->queryAll();
        
        return $this->asJson([
            'status' => 'ok',
            'data' => $patients,
            'total' => (int)$count,
            'page' => $page,
            'limit' => $limit,
            'hasMore' => ($offset + $limit) < $count,
        ]);
    }
}
