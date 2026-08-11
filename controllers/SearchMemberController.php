<?php

namespace app\controllers;

use app\models\Member;
use Yii;
use yii\db\Expression;
use yii\db\Query;
use yii\db\Transaction;
use yii\web\BadRequestHttpException;

class SearchMemberController extends BaseApiController
{
    public function actionIndex(
        $page,
        $limit,
        $q = '',
        $blood_type = null,
        $availability = null
    )
    {
        $page = max(0, (int)$page);
        // Keep responses lightweight. Directory-wide counts are returned in
        // `analysis`; clients page through matching rows instead of requesting
        // thousands of member records in one response.
        $limit = max(1, min(250, (int)$limit));
        $q = trim((string)$q);
        $blood_type = trim((string)$blood_type);
        $availability = strtolower(trim((string)$availability));
        if ($availability === 'all') {
            $availability = '';
        }
        if ($availability !== '' && !in_array(
            $availability,
            ['green', 'yellow', 'red'],
            true
        )) {
            throw new BadRequestHttpException('Invalid availability filter.');
        }
        if ($availability === '') {
            $availability = null;
        }

        // Keep analysis, the selected total, and visible rows on one database
        // snapshot so an edit during the request cannot make them disagree.
        $transaction = Yii::$app->db->beginTransaction(
            Transaction::REPEATABLE_READ
        );

        try {
        // Availability is derived from source-of-truth fields on every request.
        // It is deliberately not persisted: the four-month boundary changes with
        // the calendar even when nobody edits the member record.
        $asOfDateSql = "(CURRENT_TIMESTAMP AT TIME ZONE 'Asia/Bangkok')::date";
        $effectiveLastDateSql = 'GREATEST(MAX(d.donation_date), m.last_date)';
        $manualOffSql = "LOWER(TRIM(COALESCE(m.status, 'available'))) IN "
            . "('not_available', 'unavailable', 'disabled', 'false', '0')";
        $meaningfulNoteSql = "NULLIF(TRIM(COALESCE(m.note, '')), '') IS NOT NULL "
            . "AND TRIM(COALESCE(m.note, '')) NOT IN ('-', '—', '–')";
        $insideWaitingPeriodSql = "{$effectiveLastDateSql} IS NOT NULL AND "
            . "(({$effectiveLastDateSql})::date + INTERVAL '4 months')::date > {$asOfDateSql}";
        $availabilityStateSql = "CASE "
            . "WHEN {$manualOffSql} THEN 'red' "
            . "WHEN ({$meaningfulNoteSql}) OR ({$insideWaitingPeriodSql}) THEN 'yellow' "
            . "ELSE 'green' END";

        // Build one canonical all-member directory query. Both the result page
        // and the global analysis counters come from this same filtered dataset.
        $directoryQuery = Member::find()->alias('m');

        // Search conditions - use table prefix to avoid ambiguity with JOIN
        if ($q !== '') {
            $directoryQuery->andWhere(['or',
                ['ilike', 'm.name', $q],
                ['ilike', 'm.father_name', $q],
                ['ilike', 'm.phone', $q],
                ['ilike', 'm.blood_bank_card', $q],
                ['ilike', 'm.member_id', $q],
                // Member addresses are stored as one composite value containing
                // the street/address, quarter (ward), and township. Searching
                // this column therefore covers every location segment without
                // relying on non-existent member.quarter/township columns.
                ['ilike', 'm.address', $q],
            ]);
        }

        // Filter by blood type
        if ($blood_type !== '') {
            $directoryQuery->andWhere(['m.blood_type' => $blood_type]);
        }

        $directoryQuery->leftJoin('donation d', 'm.id = d.member')
              ->select([
                  'm.id',
                  'm.member_id',
                  'm.name',
                  'm.blood_type',
                  'm.phone',
                  'm.address',
                  'm.note',
                  'm.status',
                  'm.member_count',
                  'm.last_date',
                  'last_donation_date' => new Expression('MAX(d.donation_date)'),
                  'donation_count' => new Expression('COUNT(d.id)'),
                  'effective_last_date' => new Expression($effectiveLastDateSql),
                  'availability_state' => new Expression($availabilityStateSql),
                  'eligible_again_at' => new Expression(
                      "CASE WHEN {$effectiveLastDateSql} IS NULL THEN NULL "
                      . "ELSE ((({$effectiveLastDateSql})::date + INTERVAL '4 months')::date)::text END"
                  ),
              ])
              ->groupBy('m.id');

        // These counters always describe every member matching name/blood type,
        // even when one availability chip is selected for the visible page.
        $analysisRow = (new Query())
            ->from(['directory' => clone $directoryQuery])
            ->select([
                'total' => new Expression('COUNT(*)'),
                'green' => new Expression(
                    "COUNT(*) FILTER (WHERE availability_state = 'green')"
                ),
                'yellow' => new Expression(
                    "COUNT(*) FILTER (WHERE availability_state = 'yellow')"
                ),
                'red' => new Expression(
                    "COUNT(*) FILTER (WHERE availability_state = 'red')"
                ),
                'calculated_on' => new Expression("({$asOfDateSql})::text"),
            ])
            ->one();

        $pageQuery = (new Query())
            ->from(['directory' => clone $directoryQuery]);
        if ($availability !== null) {
            $pageQuery->andWhere(['availability_state' => $availability]);
        }

        $totalKey = $availability ?? 'total';
        $total = (int)($analysisRow[$totalKey] ?? 0);
        $members = $pageQuery
            // Member ID is immutable, so offset pages cannot reshuffle while a
            // separate donation is being entered.
            ->orderBy(['id' => SORT_ASC])
            ->offset($page * $limit)
            ->limit($limit)
            ->all();

        foreach ($members as &$member) {
            $systemDonationCount = intval($member['donation_count'] ?? 0);
            $beforeCount = intval($member['member_count'] ?? 0);
            $member['total_count'] = strval($beforeCount + $systemDonationCount);

            $this->decorateSearchMember($member);
        }
        unset($member);

        $response = $this->asJson([
            'status' => 'ok',
            'data' => $members,
            'total' => $total,
            'analysis' => [
                'total' => (int)($analysisRow['total'] ?? 0),
                'green' => (int)($analysisRow['green'] ?? 0),
                'yellow' => (int)($analysisRow['yellow'] ?? 0),
                'red' => (int)($analysisRow['red'] ?? 0),
                'calculated_on' => $analysisRow['calculated_on'] ?? null,
            ],
            'page' => $page,
            'limit' => $limit,
            'loaded' => count($members),
            'has_more' => (($page + 1) * $limit) < $total,
            'classification' => [
                'as_of_date' => $analysisRow['calculated_on'] ?? null,
                'waiting_period_months' => 4,
            ],
        ]);
        $transaction->commit();
        return $response;
        } catch (\Throwable $error) {
            if ($transaction->getIsActive()) {
                $transaction->rollBack();
            }
            throw $error;
        }
    }

    /**
     * Keep the staff-controlled status separate from the automatic four-month
     * waiting period. The client uses can_donate for the red state and computes
     * date/remark cautions independently.
     */
    private function decorateSearchMember(array &$member): void
    {
        $effectiveLastDate = $member['effective_last_date']
            ?? $member['last_donation_date']
            ?? $member['last_date']
            ?? null;

        $member['last_date'] = $effectiveLastDate ?: null;

        $storedStatus = strtolower(trim((string)($member['status'] ?? 'available')));
        $member['can_donate'] = !in_array(
            $storedStatus,
            ['not_available', 'unavailable', 'disabled', 'false', '0'],
            true
        );

        // Aggregate aliases are implementation details. `last_date` is the
        // single effective value consumed by existing app versions.
        unset(
            $member['last_donation_date'],
            $member['effective_last_date'],
            $member['donation_count']
        );
    }
}
