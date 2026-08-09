<?php

namespace app\controllers;

use app\models\Member;
use Yii;

class SearchMemberController extends BaseApiController
{
    public function actionIndex($page, $limit, $q = '', $blood_type = null, $donation_year = null)
    {
        // If donation_year is provided, filter by the year of the member's LAST donation
        if ($donation_year) {
            // First, get members whose LAST donation (most recent) is in the selected year
            $sql = "
                SELECT
                    m.*,
                    last_d.max_date as last_donation_date,
                    last_d.donation_count as donation_count
                FROM member m
                INNER JOIN (
                    SELECT
                        member,
                        MAX(donation_date) as max_date,
                        COUNT(*) as donation_count
                    FROM donation
                    GROUP BY member
                ) last_d ON m.id = last_d.member
                WHERE EXTRACT(YEAR FROM last_d.max_date) = :year
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

            $sql .= " ORDER BY last_d.max_date ASC";
            
            // Apply pagination
            $sql .= " LIMIT :limit OFFSET :offset";
            $params[':limit'] = $limit;
            $params[':offset'] = $page * $limit;
            
            $members = Yii::$app->db->createCommand($sql, $params)->queryAll();
            
            // Get total count - filter by year of LAST donation
            $countSql = "
                SELECT COUNT(*) as total
                FROM member m
                INNER JOIN (
                    SELECT member, MAX(donation_date) as max_date
                    FROM donation
                    GROUP BY member
                ) last_d ON m.id = last_d.member
                WHERE EXTRACT(YEAR FROM last_d.max_date) = :year
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
            
            // Calculate total counts and status for each member
            $fourMonthsAgo = date('Y-m-d', strtotime('-4 months'));

            foreach ($members as &$member) {
                // COUNT and MAX were already calculated by the aggregate above.
                // Reusing them avoids two database queries for every result row.
                $donationCount = intval($member['donation_count'] ?? 0);
                $beforeCount = intval($member['member_count'] ?? 0);
                $member['total_count'] = strval($beforeCount + $donationCount);

                $actualLastDate = $member['last_donation_date'] ?? null;
                $member['last_date'] = $actualLastDate ?? $member['last_donation_date'];
                unset($member['donation_count']);

                // Calculate status based on actual last donation date
                // If last donation was within 4 months, status = 'unavailable'
                // If last donation was more than 4 months ago or no donations, status = 'available'
                if ($actualLastDate && $actualLastDate > $fourMonthsAgo) {
                    $member['status'] = 'unavailable';
                } else {
                    $member['status'] = 'available';
                }
            }
            unset($member);
            
            return $this->asJson([
                'status' => 'ok',
                'data' => $members,
                'total' => $total,
            ]);
        }
        
        // For non-year filtered requests, still sort by last donation date
        $query = Member::find();

        // Search conditions - use table prefix to avoid ambiguity with JOIN
        if ($q) {
            $query->andWhere(['or',
                ['like', 'member.name', $q],
                ['like', 'member.father_name', $q],
                ['like', 'member.phone', $q],
                ['like', 'member.blood_bank_card', $q],
                ['like', 'member.member_id', $q],
            ]);
        }

        // Filter by blood type
        if ($blood_type) {
            $query->andWhere(['member.blood_type' => $blood_type]);
        }

        // Join with donations to get last donation date and sort by it
        $query->leftJoin('donation d', 'member.id = d.member')
              ->select([
                  'member.*',
                  'MAX(d.donation_date) as last_donation_date',
                  'COUNT(d.id) as donation_count',
              ])
              ->groupBy('member.id')
              ->orderBy('MAX(d.donation_date) ASC NULLS FIRST'); // Farthest to nearest, NULL first

        // Apply pagination
        $queryClone = clone $query;
        $members = $query->offset($page * $limit)
                         ->limit($limit)
                         ->asArray()
                         ->all();

        // Calculate total donation count and status for each member
        $fourMonthsAgo = date('Y-m-d', strtotime('-4 months'));

        foreach ($members as &$member) {
            $systemDonationCount = intval($member['donation_count'] ?? 0);
            $beforeCount = intval($member['member_count'] ?? 0);
            $member['total_count'] = strval($beforeCount + $systemDonationCount);

            $actualLastDate = $member['last_donation_date'] ?? null;

            // Set last_date to the actual last donation date
            if ($actualLastDate) {
                $member['last_date'] = $actualLastDate;
            }

            // Calculate status based on actual last donation date
            // If last donation was within 4 months, status = 'unavailable'
            // If last donation was more than 4 months ago or no donations, status = 'available'
            if ($actualLastDate && $actualLastDate > $fourMonthsAgo) {
                $member['status'] = 'unavailable';
            } else {
                $member['status'] = 'available';
            }

            // Aggregate aliases are internal implementation details; removing
            // them keeps the API response identical to serialized Member rows.
            unset($member['last_donation_date'], $member['donation_count']);
        }
        unset($member);

        // Get the total count after applying filters
        $total = $queryClone->count();

        return $this->asJson([
            'status' => 'ok',
            'data' => $members,
            'total' => $total,
        ]);
    }
}
