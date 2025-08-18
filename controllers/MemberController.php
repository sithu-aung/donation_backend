<?php

namespace app\controllers;

use app\models\Member;
use DateTime;
use Yii;
use yii\web\Controller;

class MemberController extends BaseApiController
{
    public function actionIndex($page, $limit, $q = '', $blood_type = null, $status = null, $birth_year = null, $donation_year = null)
    {
        // If donation_year is provided, use optimized query with SQL JOIN
        if ($donation_year) {
            $sql = "
                SELECT 
                    m.*,
                    MAX(d.donation_date) as last_donation_in_year,
                    COUNT(d.id) as year_donation_count
                FROM member m
                INNER JOIN donation d ON m.id = d.member
                WHERE EXTRACT(YEAR FROM d.donation_date) = :year
            ";
            
            $params = [':year' => $donation_year];
            
            // Add search conditions
            if ($q) {
                $sql .= " AND (m.name ILIKE :q OR m.father_name ILIKE :q OR m.phone ILIKE :q OR m.blood_bank_card ILIKE :q OR m.member_id ILIKE :q)";
                $params[':q'] = '%' . $q . '%';
            }
            
            // Add blood type filter
            if ($blood_type) {
                $sql .= " AND m.blood_type = :blood_type";
                $params[':blood_type'] = $blood_type;
            }
            
            // Add status filter
            if ($status) {
                $sql .= " AND m.status = :status";
                $params[':status'] = $status;
            }
            
            // Add birth year filter
            if ($birth_year) {
                $sql .= " AND m.birth_date LIKE :birth_year";
                $params[':birth_year'] = '%' . $birth_year . '%';
            }
            
            $sql .= " GROUP BY m.id ORDER BY last_donation_in_year ASC";
            
            // Apply pagination
            $sql .= " LIMIT :limit OFFSET :offset";
            $params[':limit'] = $limit;
            $params[':offset'] = $page * $limit;
            
            $members = Yii::$app->db->createCommand($sql, $params)->queryAll();
            
            // Get total count
            $countSql = "
                SELECT COUNT(DISTINCT m.id) as total
                FROM member m
                INNER JOIN donation d ON m.id = d.member
                WHERE EXTRACT(YEAR FROM d.donation_date) = :year
            ";
            
            $countParams = [':year' => $donation_year];
            
            if ($q) {
                $countSql .= " AND (m.name ILIKE :q OR m.father_name ILIKE :q OR m.phone ILIKE :q OR m.blood_bank_card ILIKE :q OR m.member_id ILIKE :q)";
                $countParams[':q'] = '%' . $q . '%';
            }
            
            if ($blood_type) {
                $countSql .= " AND m.blood_type = :blood_type";
                $countParams[':blood_type'] = $blood_type;
            }
            
            if ($status) {
                $countSql .= " AND m.status = :status";
                $countParams[':status'] = $status;
            }
            
            if ($birth_year) {
                $countSql .= " AND m.birth_date LIKE :birth_year";
                $countParams[':birth_year'] = '%' . $birth_year . '%';
            }
            
            $total = Yii::$app->db->createCommand($countSql, $countParams)->queryScalar();
            
            // Calculate total counts for each member
            foreach ($members as &$member) {
                // Get all donations count for this member
                $donationCount = Yii::$app->db->createCommand(
                    "SELECT COUNT(*) FROM donation WHERE member = :member_id",
                    [':member_id' => $member['id']]
                )->queryScalar();
                
                $beforeCount = intval($member['member_count'] ?? 0);
                $member['total_count'] = strval($beforeCount + $donationCount);
                $member['last_date'] = $member['last_donation_in_year'];
            }
            
            return $this->asJson([
                'status' => 'ok',
                'data' => $members,
                'total' => $total,
            ]);
        }
        
        // Original query for non-year filtered requests
        $query = Member::find();

        // Search by name, father_name, phone, blood_bank_card, or member_id
        if ($q) {
            $query->andWhere(['or',
                ['like', 'name', $q],
                ['like', 'father_name', $q],
                ['like', 'phone', $q],
                ['like', 'blood_bank_card', $q],
                ['like', 'member_id', $q],
            ]);
        }

        // Filter by blood type
        if ($blood_type) {
            $query->andWhere(['blood_type' => $blood_type]);
        }

        // Filter by status
        if ($status) {
            $query->andWhere(['status' => $status]);
        }

        // Filter by birth year
        if ($birth_year) {
            $query->andWhere(['like', 'birth_date', $birth_year]);
        }

        // Join with donations to get last donation date and sort by it
        $query->leftJoin('donation d', 'member.id = d.member')
              ->select(['member.*', 'MAX(d.donation_date) as last_donation_date'])
              ->groupBy('member.id')
              ->orderBy('MAX(d.donation_date) ASC NULLS FIRST'); // Farthest to nearest, NULL first

        // Apply pagination
        $queryClone = clone $query;
        $members = $query->offset($page * $limit)
                         ->limit($limit)
                         ->all();

        // Calculate total donation count for each member
        foreach ($members as $member) {
            // Load donations relation
            $donations = $member->getDonations()->all();
            $systemDonationCount = count($donations);
            $beforeCount = intval($member->member_count ?? 0);
            $totalCount = $beforeCount + $systemDonationCount;
            $member->total_count = strval($totalCount);
            
            // Set last_date to the last donation date from the query result
            // In Yii2, custom selected columns are available as attributes
            $attributes = $member->getAttributes();
            if (isset($attributes['last_donation_date']) && $attributes['last_donation_date']) {
                $member->last_date = $attributes['last_donation_date'];
            }
        }

        // Get the total count after applying filters
        $total = $queryClone->count();

        return $this->asJson([
            'status' => 'ok',
            'data' => $members,
            'total' => $total,
        ]);
    }

    public function actionView($id)
    {
        // Check if the search parameter is numeric (likely an ID) or a string (likely a member_id)
        $query = Member::find()->with('donations');
        // if (is_numeric($id)) {
        //     $query->where(['id' => $id]);
        // } else {
        //     $query->where(['member_id' => $id]);
        // }
        $query->where(['id' => $id]);

        $member = $query->one();

        if ($member === null) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'No Member Found.',
                'id' => $id
            ]);
        }

        // Calculate total donation count
        $systemDonationCount = count($member->donations);
        $beforeCount = intval($member->member_count ?? 0);
        $totalCount = $beforeCount + $systemDonationCount;
        
        // Update total_count in the member object
        $member->total_count = strval($totalCount);

        return $this->asJson([
            'status' => 'ok',
            'data' => [
                'member' => $member,
                'donations' => $member->donations,
                'donation_counts' => [
                    'before_joining' => $beforeCount,
                    'in_system' => $systemDonationCount,
                    'total' => $totalCount
                ]
            ],
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

        // Convert birth_date to 'd M Y' format
        if (isset($data['birth_date']) && !empty($data['birth_date'])) {
            $birthDateStr = $data['birth_date'];
            $date = null;
            
            // Try multiple date formats
            $formats = ['Y-m-d', 'd M Y', 'd MMM Y', 'j M Y', 'j MMM Y'];
            foreach ($formats as $format) {
                $date = DateTime::createFromFormat($format, $birthDateStr);
                if ($date !== false) {
                    break;
                }
            }
            
            // If still no valid date, try PHP's strtotime
            if ($date === false || $date === null) {
                $timestamp = strtotime($birthDateStr);
                if ($timestamp !== false) {
                    $date = new DateTime();
                    $date->setTimestamp($timestamp);
                }
            }
            
            $member->birth_date = $date ? $date->format('d M Y') : null;
        } else {
            $member->birth_date = null;
        }
        $member->blood_bank_card = $data['blood_bank_card'] ?? null;
        $member->blood_type = $data['blood_type'] ?? null;
        $member->father_name = $data['father_name'] ?? null;
        $member->member_count = $data['member_count'] ?? "0";
        $member->name = $data['name'] ?? null;
        $member->note = $data['note'] ?? null;
        $member->nrc = $data['nrc'] ?? null;
        $member->phone = $data['phone'] ?? null;
        $member->address = $data['address'] ?? null;
        $member->gender = $data['gender'] ?? null;
        $member->register_date = date('Y-m-d H:i:s');
        $member->total_count = "0";
        $member->status = 'available';
        $member->last_date = null;
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

        // Convert birth_date to 'd M Y' format
        if (isset($data['birth_date']) && !empty($data['birth_date'])) {
            $birthDateStr = $data['birth_date'];
            $date = null;
            
            // Try multiple date formats
            $formats = ['Y-m-d', 'd M Y', 'd MMM Y', 'j M Y', 'j MMM Y'];
            foreach ($formats as $format) {
                $date = DateTime::createFromFormat($format, $birthDateStr);
                if ($date !== false) {
                    break;
                }
            }
            
            // If still no valid date, try PHP's strtotime
            if ($date === false || $date === null) {
                $timestamp = strtotime($birthDateStr);
                if ($timestamp !== false) {
                    $date = new DateTime();
                    $date->setTimestamp($timestamp);
                }
            }
            
            $member->birth_date = $date ? $date->format('d M Y') : $member->birth_date;
        }
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

    public function actionCheckExists($name, $father_name = null, $blood_type = null)
    {
        $query = Member::find();

        // Add name condition (required)
        $query->andWhere(['like', 'name', $name]);

        // Add father_name condition if provided
        if ($father_name) {
            $query->andWhere(['like', 'father_name', $father_name]);
        }

        // Add blood_type condition if provided
        if ($blood_type) {
            $query->andWhere(['blood_type' => $blood_type]);
        }

        // Find matching members
        $members = $query->all();

        return $this->asJson([
            'status' => 'ok',
            'exists' => count($members) > 0,
            'members' => $members,
        ]);
    }
}
