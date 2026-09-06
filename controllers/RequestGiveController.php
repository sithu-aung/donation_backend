<?php

namespace app\controllers;

use app\models\RequestGive;
use app\models\RequestGiveDaily;
use app\models\RequestGiveMonthState;
use DateTimeImmutable;
use DateTimeZone;
use Yii;
use yii\db\Expression;
use yii\filters\VerbFilter;
use yii\web\BadRequestHttpException;
use yii\web\ConflictHttpException;
use yii\web\ServerErrorHttpException;

class RequestGiveController extends BaseAuthController
{
    private const WORKSHEET_TIME_ZONE = 'Asia/Yangon';

    public function behaviors(): array
    {
        $behaviors = parent::behaviors();
        $behaviors['verbs'] = [
            'class' => VerbFilter::class,
            'actions' => [
                'month-entry' => ['GET'],
                'save-month' => ['POST'],
            ],
        ];
        return $behaviors;
    }

    public function actionIndex($page, $limit, $q = '')
    {
        $query = RequestGive::find();
        if ($q) {
            $query = $query->where(['like', 'request', $q]);
        }
        $query = $query->offset($page * $limit)->limit($limit)->orderBy("id");

        return $this->asJson([
            'status' => 'ok',
            'data' => $query->all(),
        ]);
    }

    public function actionView($id)
    {
        $requestGive = RequestGive::findOne($id);
        if ($requestGive === null) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'No RequestGive Found.',
            ]);
        }

        return $this->asJson([
            'status' => 'ok',
            'data' => $requestGive,
        ]);
    }

    public function actionCreate()
    {
        $date = Yii::$app->request->post('date');
        $this->assertLegacyMutationAllowedForDate($date);

        $requestGive = new RequestGive();
        $requestGive->request = Yii::$app->request->post('request');
        $requestGive->give = Yii::$app->request->post('give');
        $requestGive->date = $date;

        if (!$requestGive->save()) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'Failed to create RequestGive.',
                'errors' => $requestGive->errors,
            ]);
        }

        return $this->asJson([
            'status' => 'ok',
            'data' => $requestGive
        ]);
    }

    public function actionUpdate($id)
    {
        $requestGive = RequestGive::findOne($id);
        if ($requestGive === null) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'No RequestGive Found.',
            ]);
        }

        $date = Yii::$app->request->post('date');
        $this->assertLegacyMutationAllowedForDate($requestGive->date);
        $this->assertLegacyMutationAllowedForDate($date);

        $requestGive->request = Yii::$app->request->post('request');
        $requestGive->give = Yii::$app->request->post('give');
        $requestGive->date = $date;

        if (!$requestGive->save()) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'Failed to update RequestGive.',
                'errors' => $requestGive->errors,
            ]);
        }

        return $this->asJson([
            'status' => 'ok',
            'data' => $requestGive
        ]);
    }

    public function actionDelete($id)
    {
        $requestGive = RequestGive::findOne($id);
        if ($requestGive === null) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'No RequestGive Found.',
            ]);
        }
        $this->assertLegacyMutationAllowedForDate($requestGive->date);
        if (!$requestGive->delete()) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'Failed to delete RequestGive.',
            ]);
        }

        return $this->asJson([
            'status' => 'ok',
            'message' => 'RequestGive is deleted.'
        ]);
    }

    /**
     * Get detailed report by year or by year and month
     */
    public function actionDetailedReport()
    {
        $year = Yii::$app->request->get('year');
        $month = Yii::$app->request->get('month');
        
        $query = RequestGive::find();
        
        if ($year) {
            $query->andWhere(['EXTRACT(YEAR FROM date)' => $year]);
            
            if ($month) {
                // Get data for specific month
                $query->andWhere(['EXTRACT(MONTH FROM date)' => $month]);
                
                $data = $query->orderBy('date ASC')->all();
                
                // Calculate totals for the month
                $totalRequest = 0;
                $totalGive = 0;
                foreach ($data as $item) {
                    $totalRequest += $item->request;
                    $totalGive += $item->give;
                }
                
                return $this->asJson([
                    'status' => 'ok',
                    'data' => [
                        'records' => $data,
                        'summary' => [
                            'year' => $year,
                            'month' => $month,
                            'totalRequest' => $totalRequest,
                            'totalGive' => $totalGive,
                            'count' => count($data)
                        ]
                    ]
                ]);
            } else {
                // Get monthly summary for the year
                $sql = "SELECT 
                    EXTRACT(MONTH FROM date) as month,
                    SUM(request) as totalRequest,
                    SUM(give) as totalGive,
                    COUNT(*) as count
                FROM request_give
                WHERE EXTRACT(YEAR FROM date) = :year
                GROUP BY EXTRACT(MONTH FROM date)
                ORDER BY month";
                
                $monthlyData = Yii::$app->db->createCommand($sql)
                    ->bindValue(':year', $year)
                    ->queryAll();
                
                // Get yearly totals
                $yearlyTotal = Yii::$app->db->createCommand(
                    "SELECT 
                        COALESCE(SUM(request), 0) as totalRequest,
                        COALESCE(SUM(give), 0) as totalGive,
                        COUNT(*) as count
                    FROM request_give
                    WHERE EXTRACT(YEAR FROM date) = :year"
                )
                ->bindValue(':year', $year)
                ->queryOne();
                
                // Ensure we have valid data even if empty
                if (!$yearlyTotal) {
                    $yearlyTotal = [
                        'totalRequest' => 0,
                        'totalGive' => 0,
                        'count' => 0
                    ];
                }
                
                return $this->asJson([
                    'status' => 'ok',
                    'data' => [
                        'monthlyData' => $monthlyData ?: [],
                        'yearlyTotal' => $yearlyTotal,
                        'year' => $year
                    ]
                ]);
            }
        } else {
            // Get all years summary
            $sql = "SELECT 
                EXTRACT(YEAR FROM date) as year,
                SUM(request) as totalRequest,
                SUM(give) as totalGive,
                COUNT(*) as count
            FROM request_give
            GROUP BY EXTRACT(YEAR FROM date)
            ORDER BY year DESC";
            
            $yearlyData = Yii::$app->db->createCommand($sql)->queryAll();
            
            return $this->asJson([
                'status' => 'ok',
                'data' => [
                    'yearlyData' => $yearlyData
                ]
            ]);
        }
    }

    /**
     * Get or create request/give record for a specific month
     */
    public function actionGetOrCreateMonthly()
    {
        $year = Yii::$app->request->get('year');
        $month = Yii::$app->request->get('month');
        
        if (!$year || !$month) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'Year and month are required.',
            ]);
        }
        
        // Create date for the first day of the month
        $date = sprintf('%04d-%02d-01', $year, $month);
        
        // Check if record exists for this month
        $existingRecord = RequestGive::find()
            ->where(['EXTRACT(YEAR FROM date)' => $year])
            ->andWhere(['EXTRACT(MONTH FROM date)' => $month])
            ->one();
        
        if ($existingRecord) {
            return $this->asJson([
                'status' => 'ok',
                'data' => $existingRecord,
                'isNew' => false
            ]);
        }
        
        // Create new record with default values
        $newRecord = new RequestGive();
        $newRecord->date = $date;
        $newRecord->request = 0;
        $newRecord->give = 0;
        
        return $this->asJson([
            'status' => 'ok',
            'data' => $newRecord,
            'isNew' => true
        ]);
    }

    /**
     * Return the saved rows for one month worksheet.
     *
     * Months that only contain an imported request_give aggregate are exposed
     * as read-only. This prevents a daily worksheet from silently replacing a
     * historical monthly total whose day-by-day breakdown is unknown.
     */
    public function actionMonthEntry()
    {
        $month = $this->monthBounds(
            Yii::$app->request->get('year'),
            Yii::$app->request->get('month')
        );

        return $this->asJson([
            'status' => 'ok',
            'data' => $this->monthEntryData($month),
        ]);
    }

    /**
     * Replace one complete/partial month worksheet and refresh its aggregate.
     *
     * Clients must submit the revision returned by actionMonthEntry as
     * expectedRevision. A successful save advances and returns that revision.
     *
     * The delete, daily inserts, duplicate-summary cleanup, and summary upsert
     * share one transaction. A validation or persistence error therefore
     * leaves both the worksheet and its monthly report value unchanged.
     */
    public function actionSaveMonth()
    {
        $month = $this->monthBounds(
            Yii::$app->request->post('year'),
            Yii::$app->request->post('month')
        );
        $records = $this->normalizeMonthRecords(
            Yii::$app->request->post('records'),
            $month
        );
        $expectedRevision = $this->boundedInteger(
            Yii::$app->request->post('expectedRevision'),
            'expectedRevision',
            0,
            PHP_INT_MAX - 1
        );

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $existingDailyCount = (int)$this->dailyQuery($month)->count();
            $summaryRecords = $this->summaryQuery($month)
                ->orderBy(['id' => SORT_ASC])
                ->all();
            $hasMonthState = $this->monthStateQuery($month)->exists();

            if (!$hasMonthState
                && $existingDailyCount === 0
                && $summaryRecords !== []) {
                throw new ConflictHttpException(
                    'This month contains a legacy monthly summary and cannot be overwritten with daily rows.'
                );
            }

            $this->advanceMonthRevision($month, $expectedRevision);
            RequestGiveDaily::deleteAll($this->monthRangeCondition($month));

            $totalRequest = 0;
            $totalGive = 0;
            foreach ($records as $record) {
                $daily = new RequestGiveDaily();
                $daily->date = $record['date'];
                $daily->request = $record['request'];
                $daily->give = $record['give'];
                if (!$daily->save()) {
                    throw new ServerErrorHttpException(
                        'Failed to save the daily request/give worksheet.'
                    );
                }

                $totalRequest += $record['request'] ?? 0;
                $totalGive += $record['give'] ?? 0;
            }

            if ($records === []) {
                // Removing the worksheet's final row means "not recorded", not
                // an explicit zero. Keep only the month-state ownership marker.
                foreach ($summaryRecords as $summaryRecord) {
                    if ($summaryRecord->delete() === false) {
                        throw new ServerErrorHttpException(
                            'Failed to clear the monthly request/give summary.'
                        );
                    }
                }
            } else {
                /** @var RequestGive $summary */
                $summary = $summaryRecords !== []
                    ? array_shift($summaryRecords)
                    : new RequestGive();

                // Old clients could create duplicate month summaries. Once a
                // month has a daily worksheet, consolidate only that month so
                // all report readers see exactly one aggregate row.
                foreach ($summaryRecords as $duplicateSummary) {
                    if ($duplicateSummary->delete() === false) {
                        throw new ServerErrorHttpException(
                            'Failed to consolidate the monthly request/give summary.'
                        );
                    }
                }

                $summary->date = $month['start'];
                $summary->request = $totalRequest;
                $summary->give = $totalGive;
                if (!$summary->save()) {
                    throw new ServerErrorHttpException(
                        'Failed to save the monthly request/give summary.'
                    );
                }
            }

            $data = $this->monthEntryData($month);
            $transaction->commit();
        } catch (\Throwable $error) {
            if ($transaction->isActive) {
                $transaction->rollBack();
            }
            throw $error;
        }

        return $this->asJson([
            'status' => 'ok',
            'data' => $data,
        ]);
    }

    /**
     * @return array{year:int,month:int,start:string,next:string,daysInMonth:int}
     */
    private function monthBounds($rawYear, $rawMonth): array
    {
        $year = $this->boundedInteger($rawYear, 'year', 1900, 9999);
        $month = $this->boundedInteger($rawMonth, 'month', 1, 12);
        $startDate = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            sprintf('%04d-%02d-01', $year, $month)
        );

        if ($startDate === false) {
            throw new BadRequestHttpException('year and month must form a valid calendar month.');
        }

        return [
            'year' => $year,
            'month' => $month,
            'start' => $startDate->format('Y-m-d'),
            'next' => $startDate->modify('+1 month')->format('Y-m-d'),
            'daysInMonth' => (int)$startDate->format('t'),
        ];
    }

    private function boundedInteger($value, string $name, int $minimum, int $maximum): int
    {
        if (is_int($value)) {
            $normalized = $value;
        } elseif (is_string($value) && preg_match('/^[0-9]+$/D', $value)) {
            $normalized = (int)$value;
        } else {
            throw new BadRequestHttpException("$name must be an integer.");
        }

        if ($normalized < $minimum || $normalized > $maximum) {
            throw new BadRequestHttpException(
                "$name must be between $minimum and $maximum."
            );
        }

        return $normalized;
    }

    /**
     * @param mixed $rawRecords
     * @param array{start:string,next:string} $month
     * @return array<int,array{date:string,request:int|null,give:int|null}>
     */
    private function normalizeMonthRecords($rawRecords, array $month): array
    {
        if (!is_array($rawRecords)) {
            throw new BadRequestHttpException('records must be an array.');
        }

        $records = [];
        $seenDates = [];
        foreach ($rawRecords as $index => $rawRecord) {
            if (!is_array($rawRecord)) {
                throw new BadRequestHttpException(
                    "records[$index] must be an object."
                );
            }

            $date = $this->normalizeWorksheetDate(
                $rawRecord['date'] ?? null,
                $index,
                $month
            );
            if (isset($seenDates[$date])) {
                throw new BadRequestHttpException(
                    "records contains the date $date more than once."
                );
            }
            $seenDates[$date] = true;

            $records[] = [
                'date' => $date,
                'request' => $this->normalizeNullableCount(
                    $rawRecord['request'] ?? null,
                    'request',
                    $index
                ),
                'give' => $this->normalizeNullableCount(
                    $rawRecord['give'] ?? null,
                    'give',
                    $index
                ),
            ];
        }

        usort($records, static function (array $left, array $right): int {
            return strcmp($left['date'], $right['date']);
        });

        return $records;
    }

    /** @param array{start:string,next:string} $month */
    private function normalizeWorksheetDate($value, int $index, array $month): string
    {
        if (!is_string($value)
            || !preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $value)) {
            throw new BadRequestHttpException(
                "records[$index].date must use yyyy-MM-dd."
            );
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false
            || ($errors !== false
                && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d') !== $value) {
            throw new BadRequestHttpException(
                "records[$index].date is not a valid calendar date."
            );
        }

        if ($value < $month['start'] || $value >= $month['next']) {
            throw new BadRequestHttpException(
                "records[$index].date does not belong to the selected month."
            );
        }
        if ($value > $this->worksheetToday()) {
            throw new BadRequestHttpException(
                "records[$index].date cannot be in the future."
            );
        }

        return $value;
    }

    private function normalizeNullableCount($value, string $name, int $index): ?int
    {
        if ($value === null) {
            return null;
        }
        if (!is_int($value) || $value < 0) {
            throw new BadRequestHttpException(
                "records[$index].$name must be null or a non-negative integer."
            );
        }

        return $value;
    }

    /** @param array{start:string,next:string} $month */
    private function dailyQuery(array $month)
    {
        return RequestGiveDaily::find()
            ->where(['>=', 'date', $month['start']])
            ->andWhere(['<', 'date', $month['next']]);
    }

    /** @param array{start:string,next:string} $month */
    private function summaryQuery(array $month)
    {
        return RequestGive::find()
            ->where(['>=', 'date', $month['start']])
            ->andWhere(['<', 'date', $month['next']]);
    }

    /** @param array{year:int,month:int} $month */
    private function monthStateQuery(array $month)
    {
        return RequestGiveMonthState::find()->where([
            'year' => $month['year'],
            'month' => $month['month'],
        ]);
    }

    /**
     * Atomically compare-and-swap the month revision inside the save
     * transaction. Only one writer holding a given revision can advance it.
     *
     * @param array{year:int,month:int} $month
     */
    private function advanceMonthRevision(array $month, int $expectedRevision): void
    {
        Yii::$app->db->createCommand()->upsert(
            RequestGiveMonthState::tableName(),
            [
                'year' => $month['year'],
                'month' => $month['month'],
                'revision' => 0,
            ],
            false
        )->execute();

        $advanced = Yii::$app->db->createCommand()->update(
            RequestGiveMonthState::tableName(),
            ['revision' => new Expression('revision + 1')],
            [
                'year' => $month['year'],
                'month' => $month['month'],
                'revision' => $expectedRevision,
            ]
        )->execute();

        if ($advanced !== 1) {
            throw new ConflictHttpException(
                'This month worksheet changed after it was loaded. Reload it before saving again.'
            );
        }
    }

    private function worksheetToday(): string
    {
        $timeZone = new DateTimeZone(self::WORKSHEET_TIME_ZONE);
        return (new DateTimeImmutable('today', $timeZone))->format('Y-m-d');
    }

    /**
     * Legacy aggregate clients may continue changing legacy-only months, but
     * must not mutate the aggregate that is maintained by a daily worksheet.
     */
    private function assertLegacyMutationAllowedForDate($value): void
    {
        $month = $this->monthFromStoredDate($value);
        if ($month === null) {
            return;
        }

        if ($this->monthStateQuery($month)->exists()
            || $this->dailyQuery($month)->exists()) {
            throw new ConflictHttpException(
                'This month is managed by the daily worksheet and cannot be changed through the legacy monthly endpoint.'
            );
        }
    }

    /**
     * Historical aggregates can contain a timestamp, so only the canonical
     * date prefix is needed to resolve their calendar month.
     *
     * @return array{year:int,month:int,start:string,next:string,daysInMonth:int}|null
     */
    private function monthFromStoredDate($value): ?array
    {
        if (!is_string($value)
            || !preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})/', $value, $parts)) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            sprintf('%s-%s-%s', $parts[1], $parts[2], $parts[3])
        );
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false
            || ($errors !== false
                && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return null;
        }

        return $this->monthBounds((int)$parts[1], (int)$parts[2]);
    }

    /**
     * @param array{start:string,next:string} $month
     * @return array<int,mixed>
     */
    private function monthRangeCondition(array $month): array
    {
        return [
            'and',
            ['>=', 'date', $month['start']],
            ['<', 'date', $month['next']],
        ];
    }

    /**
     * @param array{year:int,month:int,start:string,next:string,daysInMonth:int} $month
     */
    private function monthEntryData(array $month): array
    {
        $dailyRecords = $this->dailyQuery($month)
            ->orderBy(['date' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        $rows = [];
        $totalRequest = 0;
        $totalGive = 0;
        $recordedDays = 0;
        foreach ($dailyRecords as $record) {
            $request = $this->databaseNullableInteger($record->request);
            $give = $this->databaseNullableInteger($record->give);
            $rows[] = [
                'id' => (int)$record->id,
                'date' => substr((string)$record->date, 0, 10),
                'request' => $request,
                'give' => $give,
            ];
            $totalRequest += $request ?? 0;
            $totalGive += $give ?? 0;
            if ($request !== null || $give !== null) {
                $recordedDays++;
            }
        }

        $summaryRecords = $this->summaryQuery($month)
            ->orderBy(['id' => SORT_ASC])
            ->all();
        $monthState = $this->monthStateQuery($month)->one();
        $worksheetOwned = $monthState !== null || $dailyRecords !== [];
        $legacyOnly = !$worksheetOwned && $summaryRecords !== [];

        return [
            'year' => $month['year'],
            'month' => $month['month'],
            'daysInMonth' => $month['daysInMonth'],
            'today' => $this->worksheetToday(),
            'rows' => $rows,
            'totals' => [
                'request' => $totalRequest,
                'give' => $totalGive,
            ],
            'recordedDays' => $recordedDays,
            'revision' => $monthState === null ? 0 : (int)$monthState->revision,
            'legacySummary' => $legacyOnly
                ? $this->legacySummaryData($summaryRecords)
                : null,
            'legacyOnly' => $legacyOnly,
            'editable' => !$legacyOnly,
        ];
    }

    private function databaseNullableInteger($value): ?int
    {
        return $value === null || $value === '' ? null : (int)$value;
    }

    /** @param RequestGive[] $summaryRecords */
    private function legacySummaryData(array $summaryRecords): array
    {
        $first = $summaryRecords[0];
        $totalRequest = 0;
        $totalGive = 0;
        foreach ($summaryRecords as $record) {
            $totalRequest += (int)($record->request ?? 0);
            $totalGive += (int)($record->give ?? 0);
        }

        return [
            'id' => (int)$first->id,
            'date' => substr((string)$first->date, 0, 10),
            'request' => $totalRequest,
            'give' => $totalGive,
            'recordCount' => count($summaryRecords),
        ];
    }
}
