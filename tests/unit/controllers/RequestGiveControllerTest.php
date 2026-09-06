<?php

namespace tests\unit\controllers;

use app\controllers\RequestGiveController;
use Codeception\Test\Unit;
use DateTimeImmutable;
use DateTimeZone;
use Yii;
use yii\db\IntegrityException;
use yii\web\BadRequestHttpException;
use yii\web\ConflictHttpException;

class RequestGiveControllerTest extends Unit
{
    private const VALID_TOKEN = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    protected function _before(): void
    {
        parent::_before();

        $db = Yii::$app->db;
        $db->createCommand('DROP TABLE IF EXISTS request_give_month_state')->execute();
        $db->createCommand('DROP TABLE IF EXISTS request_give_daily')->execute();
        $db->createCommand('DROP TABLE IF EXISTS request_give')->execute();
        $db->createCommand('DROP TABLE IF EXISTS account_token')->execute();
        $db->createCommand('DROP TABLE IF EXISTS account')->execute();

        $db->createCommand(<<<'SQL'
CREATE TABLE account (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NULL,
    email TEXT NULL,
    phone TEXT NULL,
    password_hash TEXT NOT NULL,
    access_token TEXT NOT NULL
)
SQL)->execute();
        $db->createCommand(<<<'SQL'
CREATE TABLE account_token (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    account_id INTEGER NOT NULL,
    token TEXT NOT NULL,
    created_at TEXT NULL,
    last_used_at TEXT NULL
)
SQL)->execute();
        $db->createCommand(<<<'SQL'
CREATE TABLE request_give (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    request INTEGER NULL,
    give INTEGER NULL,
    date TEXT NULL
)
SQL)->execute();
        $db->createCommand(<<<'SQL'
CREATE TABLE request_give_daily (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    date TEXT NOT NULL UNIQUE,
    request INTEGER NULL CHECK (request >= 0),
    give INTEGER NULL CHECK (give >= 0)
)
SQL)->execute();
        $db->createCommand(<<<'SQL'
CREATE TABLE request_give_month_state (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    year INTEGER NOT NULL,
    month INTEGER NOT NULL,
    revision INTEGER NOT NULL DEFAULT 0 CHECK (revision >= 0),
    UNIQUE (year, month)
)
SQL)->execute();

        $db->createCommand()->insert('account', [
            'name' => 'Worksheet test staff',
            'password_hash' => 'unused-in-controller-test',
            'access_token' => self::VALID_TOKEN,
        ])->execute();

        $db->schema->refresh();
        $this->resetRequest();
    }

    public function testMonthEntryRequiresAuthentication(): void
    {
        Yii::$app->request->setQueryParams(['year' => '2026', 'month' => '9']);

        $this->controller()->runAction('month-entry');

        $this->assertSame(401, Yii::$app->response->statusCode);
        $this->assertSame('error', Yii::$app->response->data['status']);
    }

    public function testEmptyLeapMonthReturnsAnEditableWorksheet(): void
    {
        $data = $this->getMonth(2028, 2);

        $this->assertSame(2028, $data['year']);
        $this->assertSame(2, $data['month']);
        $this->assertSame(29, $data['daysInMonth']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $data['today']);
        $this->assertSame([], $data['rows']);
        $this->assertSame(['request' => 0, 'give' => 0], $data['totals']);
        $this->assertSame(0, $data['recordedDays']);
        $this->assertSame(0, $data['revision']);
        $this->assertNull($data['legacySummary']);
        $this->assertFalse($data['legacyOnly']);
        $this->assertTrue($data['editable']);
    }

    public function testLegacyOnlyMonthIsReturnedReadOnlyAndCannotBeOverwritten(): void
    {
        $legacyId = $this->insertSummary('2024-02-29 17:30:00', 404, 376);

        $data = $this->getMonth(2024, 2);

        $this->assertTrue($data['legacyOnly']);
        $this->assertFalse($data['editable']);
        $this->assertSame(0, $data['revision']);
        $this->assertSame([], $data['rows']);
        $this->assertSame([
            'id' => $legacyId,
            'date' => '2024-02-29',
            'request' => 404,
            'give' => 376,
            'recordCount' => 1,
        ], $data['legacySummary']);

        try {
            $this->saveMonth(2024, 2, [
                ['date' => '2024-02-01', 'request' => 1, 'give' => 1],
            ]);
            $this->fail('A legacy-only month must not accept daily rows.');
        } catch (ConflictHttpException $error) {
            $this->assertStringContainsString('legacy monthly summary', $error->getMessage());
        }

        $this->assertSame(0, $this->tableCount('request_give_daily'));
        $this->assertSame(1, $this->tableCount('request_give'));
        $this->assertSame(404, $this->summaryValue($legacyId, 'request'));
        $this->assertSame(376, $this->summaryValue($legacyId, 'give'));
    }

    public function testSaveMonthPreservesBlankAndZeroAndCreatesOneSummary(): void
    {
        $data = $this->saveMonth(2025, 9, [
            ['date' => '2025-09-03', 'request' => null, 'give' => null],
            ['date' => '2025-09-01', 'request' => 3, 'give' => 2],
            ['date' => '2025-09-02', 'request' => null, 'give' => 0],
        ]);

        $this->assertSame(30, $data['daysInMonth']);
        $this->assertSame(1, $data['revision']);
        $this->assertSame(['request' => 3, 'give' => 2], $data['totals']);
        $this->assertSame(2, $data['recordedDays']);
        $this->assertFalse($data['legacyOnly']);
        $this->assertTrue($data['editable']);
        $this->assertNull($data['legacySummary']);
        $this->assertSame([
            ['date' => '2025-09-01', 'request' => 3, 'give' => 2],
            ['date' => '2025-09-02', 'request' => null, 'give' => 0],
            ['date' => '2025-09-03', 'request' => null, 'give' => null],
        ], array_map(static function (array $row): array {
            unset($row['id']);
            return $row;
        }, $data['rows']));

        $this->assertSame(3, $this->tableCount('request_give_daily'));
        $this->assertSame(1, $this->tableCount('request_give'));
        $summary = Yii::$app->db->createCommand(
            'SELECT request, give, date FROM request_give'
        )->queryOne();
        $this->assertSame(3, (int)$summary['request']);
        $this->assertSame(2, (int)$summary['give']);
        $this->assertSame('2025-09-01', $summary['date']);

        foreach ($data['rows'] as $row) {
            $this->assertIsInt($row['id']);
            if ($row['request'] !== null) {
                $this->assertIsInt($row['request']);
            }
            if ($row['give'] !== null) {
                $this->assertIsInt($row['give']);
            }
        }
    }

    public function testResaveReplacesOnlyTheTargetMonthAndUpdatesTheSameSummary(): void
    {
        $this->saveMonth(2025, 8, [
            ['date' => '2025-08-31', 'request' => 9, 'give' => 8],
        ]);
        $this->saveMonth(2025, 9, [
            ['date' => '2025-09-01', 'request' => 3, 'give' => 2],
            ['date' => '2025-09-02', 'request' => 4, 'give' => 3],
        ]);
        $septemberSummaryId = (int)Yii::$app->db->createCommand(
            "SELECT id FROM request_give WHERE date = '2025-09-01'"
        )->queryScalar();

        $data = $this->saveMonth(2025, 9, [
            ['date' => '2025-09-05', 'request' => 7, 'give' => 6],
        ], 1);

        $this->assertCount(1, $data['rows']);
        $this->assertSame('2025-09-05', $data['rows'][0]['date']);
        $this->assertSame(['request' => 7, 'give' => 6], $data['totals']);
        $this->assertSame(1, $this->tableCountWhere(
            'request_give_daily',
            "date >= '2025-09-01' AND date < '2025-10-01'"
        ));
        $this->assertSame(1, $this->tableCountWhere(
            'request_give_daily',
            "date >= '2025-08-01' AND date < '2025-09-01'"
        ));
        $this->assertSame(2, $this->tableCount('request_give'));
        $this->assertSame(7, $this->summaryValue($septemberSummaryId, 'request'));
        $this->assertSame(6, $this->summaryValue($septemberSummaryId, 'give'));
        $this->assertSame(9, (int)Yii::$app->db->createCommand(
            "SELECT request FROM request_give WHERE date = '2025-08-01'"
        )->queryScalar());
    }

    public function testSaveConsolidatesDuplicateSummariesForAnEditableMonth(): void
    {
        $this->insertDaily('2025-09-01', 1, 1);
        $firstSummaryId = $this->insertSummary('2025-09-01', 1, 1);
        $this->insertSummary('2025-09-30 12:00:00', 2, 2);

        $this->saveMonth(2025, 9, [
            ['date' => '2025-09-10', 'request' => 5, 'give' => 4],
        ]);

        $this->assertSame(1, $this->tableCount('request_give_daily'));
        $this->assertSame(1, $this->tableCount('request_give'));
        $this->assertSame(5, $this->summaryValue($firstSummaryId, 'request'));
        $this->assertSame(4, $this->summaryValue($firstSummaryId, 'give'));
    }

    public function testStaleRevisionIsRejectedWithoutChangingTheWorksheet(): void
    {
        $first = $this->saveMonth(2025, 6, [
            ['date' => '2025-06-01', 'request' => 3, 'give' => 2],
        ]);
        $this->assertSame(1, $first['revision']);

        try {
            $this->saveMonth(2025, 6, [
                ['date' => '2025-06-02', 'request' => 99, 'give' => 88],
            ], 0);
            $this->fail('A stale worksheet revision must not overwrite newer data.');
        } catch (ConflictHttpException $error) {
            $this->assertStringContainsString(
                'changed after it was loaded',
                $error->getMessage()
            );
        }

        $data = $this->getMonth(2025, 6);
        $this->assertSame(1, $data['revision']);
        $this->assertSame('2025-06-01', $data['rows'][0]['date']);
        $this->assertSame(3, $data['rows'][0]['request']);
        $this->assertSame(2, $data['rows'][0]['give']);
    }

    public function testWorksheetCanBeClearedAndRemainsEditable(): void
    {
        $first = $this->saveMonth(2025, 7, [
            ['date' => '2025-07-01', 'request' => 8, 'give' => 7],
        ]);

        $cleared = $this->saveMonth(2025, 7, [], $first['revision']);

        $this->assertSame(2, $cleared['revision']);
        $this->assertSame([], $cleared['rows']);
        $this->assertSame(['request' => 0, 'give' => 0], $cleared['totals']);
        $this->assertSame(0, $cleared['recordedDays']);
        $this->assertFalse($cleared['legacyOnly']);
        $this->assertTrue($cleared['editable']);
        $this->assertNull($cleared['legacySummary']);
        $this->assertSame(0, $this->tableCount('request_give_daily'));
        $this->assertSame(0, $this->tableCount('request_give'));
        $this->assertSame(1, $this->tableCount('request_give_month_state'));

        $reloaded = $this->getMonth(2025, 7);
        $this->assertSame(2, $reloaded['revision']);
        $this->assertFalse($reloaded['legacyOnly']);
        $this->assertTrue($reloaded['editable']);

        try {
            $this->runLegacyAction('create', [
                'date' => '2025-07-01',
                'request' => 1,
                'give' => 1,
            ]);
            $this->fail('A cleared worksheet month must remain protected.');
        } catch (ConflictHttpException $error) {
            $this->assertStringContainsString(
                'managed by the daily worksheet',
                $error->getMessage()
            );
        }
    }

    public function testLegacyCrudRemainsAvailableForLegacyOnlyMonths(): void
    {
        $this->runLegacyAction('create', [
            'date' => '2024-05-31 17:30:00',
            'request' => 20,
            'give' => 18,
        ]);
        $id = (int)Yii::$app->db->getLastInsertID();
        $this->assertGreaterThan(0, $id);

        $this->runLegacyAction('update', [
            'date' => '2024-05-31 17:30:00',
            'request' => 21,
            'give' => 19,
        ], ['id' => $id]);
        $this->assertSame(21, $this->summaryValue($id, 'request'));
        $this->assertSame(19, $this->summaryValue($id, 'give'));

        $this->runLegacyAction('delete', [], ['id' => $id]);
        $this->assertSame(0, $this->tableCount('request_give'));
    }

    public function testLegacyCrudCannotMutateAWorksheetBackedMonth(): void
    {
        $data = $this->saveMonth(2025, 5, [
            ['date' => '2025-05-01', 'request' => 6, 'give' => 5],
        ]);
        $this->assertSame(1, $data['revision']);
        $summaryId = (int)Yii::$app->db->createCommand(
            "SELECT id FROM request_give WHERE date = '2025-05-01'"
        )->queryScalar();

        foreach ([
            ['create', [
                'date' => '2025-05-02',
                'request' => 1,
                'give' => 1,
            ], []],
            ['update', [
                'date' => '2025-05-01',
                'request' => 60,
                'give' => 50,
            ], ['id' => $summaryId]],
            ['delete', [], ['id' => $summaryId]],
        ] as [$action, $body, $params]) {
            try {
                $this->runLegacyAction($action, $body, $params);
                $this->fail("Legacy $action must not change a worksheet-backed month.");
            } catch (ConflictHttpException $error) {
                $this->assertStringContainsString(
                    'managed by the daily worksheet',
                    $error->getMessage()
                );
            }
        }

        $this->assertSame(1, $this->tableCount('request_give'));
        $this->assertSame(1, $this->tableCount('request_give_daily'));
        $this->assertSame(6, $this->summaryValue($summaryId, 'request'));
        $this->assertSame(5, $this->summaryValue($summaryId, 'give'));
    }

    public function testLegacyUpdateCannotMoveARecordIntoAWorksheetMonth(): void
    {
        $this->saveMonth(2025, 5, [
            ['date' => '2025-05-01', 'request' => 6, 'give' => 5],
        ]);
        $legacyId = $this->insertSummary('2025-04-30 17:30:00', 12, 11);

        try {
            $this->runLegacyAction('update', [
                'date' => '2025-05-02',
                'request' => 20,
                'give' => 19,
            ], ['id' => $legacyId]);
            $this->fail('A legacy row must not be moved into a worksheet month.');
        } catch (ConflictHttpException $error) {
            $this->assertStringContainsString(
                'managed by the daily worksheet',
                $error->getMessage()
            );
        }

        $legacy = Yii::$app->db->createCommand(
            'SELECT date, request, give FROM request_give WHERE id = :id',
            [':id' => $legacyId]
        )->queryOne();
        $this->assertSame('2025-04-30 17:30:00', $legacy['date']);
        $this->assertSame(12, (int)$legacy['request']);
        $this->assertSame(11, (int)$legacy['give']);
    }

    public function testFutureDailyRecordIsRejected(): void
    {
        $timeZone = new DateTimeZone('Asia/Yangon');
        $tomorrow = new DateTimeImmutable('tomorrow', $timeZone);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('cannot be in the future');

        $this->saveMonth(
            (int)$tomorrow->format('Y'),
            (int)$tomorrow->format('n'),
            [[
                'date' => $tomorrow->format('Y-m-d'),
                'request' => 1,
                'give' => 1,
            ]]
        );
    }

    public function testSaveMonthRequiresExpectedRevision(): void
    {
        $this->resetRequest();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->authenticate();
        Yii::$app->request->setBodyParams([
            'year' => 2025,
            'month' => 4,
            'records' => [],
        ]);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('expectedRevision must be an integer');

        $this->controller()->runAction('save-month');
    }

    /**
     * @dataProvider invalidRecordProvider
     */
    public function testInvalidRowsAreRejected(array $records, string $message): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage($message);

        $this->saveMonth(2026, 9, $records);
    }

    public function invalidRecordProvider(): array
    {
        return [
            'duplicate date' => [[
                ['date' => '2026-09-01', 'request' => 1, 'give' => 1],
                ['date' => '2026-09-01', 'request' => 2, 'give' => 2],
            ], 'more than once'],
            'outside month' => [[
                ['date' => '2026-10-01', 'request' => 1, 'give' => 1],
            ], 'does not belong'],
            'impossible date' => [[
                ['date' => '2026-09-31', 'request' => 1, 'give' => 1],
            ], 'not a valid calendar date'],
            'negative request' => [[
                ['date' => '2026-09-01', 'request' => -1, 'give' => 1],
            ], 'request must be null or a non-negative integer'],
            'string count' => [[
                ['date' => '2026-09-01', 'request' => '1', 'give' => 1],
            ], 'request must be null or a non-negative integer'],
            'decimal count' => [[
                ['date' => '2026-09-01', 'request' => 1.5, 'give' => 1],
            ], 'request must be null or a non-negative integer'],
        ];
    }

    public function testDatabaseFailureRollsBackDailyRowsAndMonthlySummary(): void
    {
        $this->saveMonth(2025, 9, [
            ['date' => '2025-09-01', 'request' => 3, 'give' => 2],
        ]);
        $summaryId = (int)Yii::$app->db->createCommand(
            "SELECT id FROM request_give WHERE date = '2025-09-01'"
        )->queryScalar();
        Yii::$app->db->getMasterPdo()->exec(<<<'SQL'
CREATE TRIGGER reject_test_daily_row
BEFORE INSERT ON request_give_daily
WHEN NEW.date = '2025-09-15'
BEGIN
    SELECT RAISE(ABORT, 'intentional worksheet test failure');
END
SQL);

        try {
            $this->saveMonth(2025, 9, [
                ['date' => '2025-09-10', 'request' => 10, 'give' => 9],
                ['date' => '2025-09-15', 'request' => 20, 'give' => 19],
            ], 1);
            $this->fail('The database trigger should abort the replacement.');
        } catch (IntegrityException $error) {
            $this->assertStringContainsString(
                'intentional worksheet test failure',
                $error->getMessage()
            );
        }

        $rows = Yii::$app->db->createCommand(
            'SELECT date, request, give FROM request_give_daily ORDER BY date'
        )->queryAll();
        $this->assertSame([[
            'date' => '2025-09-01',
            'request' => '3',
            'give' => '2',
        ]], array_map(static function (array $row): array {
            return [
                'date' => $row['date'],
                'request' => (string)$row['request'],
                'give' => (string)$row['give'],
            ];
        }, $rows));
        $this->assertSame(3, $this->summaryValue($summaryId, 'request'));
        $this->assertSame(2, $this->summaryValue($summaryId, 'give'));
        $this->assertSame(1, $this->getMonth(2025, 9)['revision']);
    }

    private function controller(): RequestGiveController
    {
        return new RequestGiveController('request-give', Yii::$app);
    }

    private function getMonth(int $year, int $month): array
    {
        $this->resetRequest();
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->authenticate();
        Yii::$app->request->setQueryParams([
            'year' => (string)$year,
            'month' => (string)$month,
        ]);
        $this->controller()->runAction('month-entry');

        $this->assertSame(200, Yii::$app->response->statusCode);
        $this->assertSame('ok', Yii::$app->response->data['status']);
        return Yii::$app->response->data['data'];
    }

    private function saveMonth(
        int $year,
        int $month,
        array $records,
        int $expectedRevision = 0
    ): array
    {
        $this->resetRequest();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->authenticate();
        Yii::$app->request->setBodyParams([
            'year' => $year,
            'month' => $month,
            'expectedRevision' => $expectedRevision,
            'records' => $records,
        ]);
        $this->controller()->runAction('save-month');

        $this->assertSame(200, Yii::$app->response->statusCode);
        $this->assertSame('ok', Yii::$app->response->data['status']);
        return Yii::$app->response->data['data'];
    }

    private function runLegacyAction(
        string $action,
        array $body,
        array $params = []
    ): void
    {
        $this->resetRequest();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->authenticate();
        Yii::$app->request->setBodyParams($body);
        $this->controller()->runAction($action, $params);
    }

    private function authenticate(): void
    {
        Yii::$app->request->headers->set(
            'Authorization',
            'Bearer ' . self::VALID_TOKEN
        );
    }

    private function resetRequest(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        Yii::$app->request->setQueryParams([]);
        Yii::$app->request->setBodyParams([]);
        Yii::$app->request->headers->removeAll();
        Yii::$app->response->clear();
        Yii::$app->response->statusCode = 200;
    }

    private function insertSummary(string $date, ?int $request, ?int $give): int
    {
        Yii::$app->db->createCommand()->insert('request_give', [
            'date' => $date,
            'request' => $request,
            'give' => $give,
        ])->execute();
        return (int)Yii::$app->db->getLastInsertID();
    }

    private function insertDaily(string $date, ?int $request, ?int $give): int
    {
        Yii::$app->db->createCommand()->insert('request_give_daily', [
            'date' => $date,
            'request' => $request,
            'give' => $give,
        ])->execute();
        return (int)Yii::$app->db->getLastInsertID();
    }

    private function tableCount(string $table): int
    {
        return (int)Yii::$app->db->createCommand(
            "SELECT COUNT(*) FROM $table"
        )->queryScalar();
    }

    private function tableCountWhere(string $table, string $where): int
    {
        return (int)Yii::$app->db->createCommand(
            "SELECT COUNT(*) FROM $table WHERE $where"
        )->queryScalar();
    }

    private function summaryValue(int $id, string $column): int
    {
        return (int)Yii::$app->db->createCommand(
            "SELECT $column FROM request_give WHERE id = :id",
            [':id' => $id]
        )->queryScalar();
    }
}
