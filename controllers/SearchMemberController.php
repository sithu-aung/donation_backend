<?php

namespace app\controllers;

use app\models\Member;
use Yii;

class SearchMemberController extends BaseApiController
{
    public function actionIndex($page, $limit, $q = '', $blood_type = null, $donation_year = null)
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
        
        // For non-year filtered requests, still sort by last donation date
        $query = Member::find();

        // Search conditions
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
}