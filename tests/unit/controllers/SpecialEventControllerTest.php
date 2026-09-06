<?php

namespace tests\unit\controllers;

use app\controllers\ReportController;
use app\controllers\SpecialEventController;
use Codeception\Test\Unit;
use Yii;

class SpecialEventControllerTest extends Unit
{
    protected function _before(): void
    {
        parent::_before();

        $db = Yii::$app->db;
        $db->createCommand('DROP TABLE IF EXISTS special_event')->execute();
        $db->createCommand(<<<'SQL'
CREATE TABLE special_event (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    date TEXT NULL,
    haemoglobin INTEGER NULL,
    hbs_ag INTEGER NULL,
    hcv_ab INTEGER NULL,
    mp_ict INTEGER NULL,
    retro_test INTEGER NULL,
    vdrl_test INTEGER NULL,
    lab_name TEXT NULL,
    total INTEGER NULL
)
SQL)->execute();
        $db->schema->refreshTableSchema('special_event');

        Yii::$app->request->setBodyParams([]);
        Yii::$app->request->headers->removeAll();
        Yii::$app->response->clear();
    }

    public function testIndexIsNewestFirstAndReturnsPaginationMetadata(): void
    {
        $this->insertEvent('2026-08-01', 'Lab A', 1);
        $this->insertEvent('2026-08-02', 'Lab B', 2);
        $this->insertEvent('2026-08-03', 'Lab C', 3);

        $response = $this->controller()->actionIndex(0, 2);

        $this->assertSame('ok', $response->data['status']);
        $this->assertSame(3, $response->data['total']);
        $this->assertSame(0, $response->data['page']);
        $this->assertSame(2, $response->data['limit']);
        $this->assertTrue($response->data['hasMore']);
        $this->assertSame(
            [3, 2],
            array_map('intval', array_column($response->data['data'], 'id'))
        );

        $secondPage = $this->controller()->actionIndex(1, 2);
        $this->assertFalse($secondPage->data['hasMore']);
        $this->assertSame(
            [1],
            array_map('intval', array_column($secondPage->data['data'], 'id'))
        );
    }

    public function testIndexNormalizesUnsafePaginationAndTrimsSearch(): void
    {
        $this->insertEvent('2026-08-01', 'Alpha Lab', 1);
        $this->insertEvent('2026-08-02', 'Beta Lab', 1);

        $response = $this->controller()->actionIndex(-5, 500, ' Alpha ');

        $this->assertSame(0, $response->data['page']);
        $this->assertSame(100, $response->data['limit']);
        $this->assertSame(1, $response->data['total']);
        $this->assertSame('Alpha Lab', $response->data['data'][0]['lab_name']);
    }

    public function testCreateTrimsLabAndComputesTheTotalOnTheServer(): void
    {
        Yii::$app->request->setBodyParams([
            'date' => '2026-08-29',
            'lab_name' => '  General Hospital Lab  ',
            'haemoglobin' => 1,
            'hbs_ag' => 2,
            'hcv_ab' => 3,
            'mp_ict' => 4,
            'retro_test' => 5,
            'vdrl_test' => 6,
            'total' => 999,
        ]);

        $response = $this->controller()->actionCreate();

        $this->assertSame(201, Yii::$app->response->statusCode);
        $this->assertSame('ok', $response->data['status']);
        $this->assertSame('General Hospital Lab', $response->data['data']->lab_name);
        $this->assertSame(21, $response->data['data']->total);
    }

    public function testUpdateAlsoRecomputesTheTotal(): void
    {
        $this->insertEvent('2026-08-01', 'Old Lab', 1);
        Yii::$app->request->setBodyParams([
            'date' => '2026-09-01',
            'lab_name' => ' Updated Lab ',
            'haemoglobin' => 0,
            'hbs_ag' => 1,
            'hcv_ab' => 2,
            'mp_ict' => 0,
            'retro_test' => 0,
            'vdrl_test' => 3,
            'total' => 999,
        ]);

        $response = $this->controller()->actionUpdate(1);

        $this->assertSame('ok', $response->data['status']);
        $this->assertSame('Updated Lab', $response->data['data']->lab_name);
        $this->assertSame(6, $response->data['data']->total);
        $this->assertSame(
            6,
            (int) Yii::$app->db->createCommand(
                'SELECT total FROM special_event WHERE id = 1'
            )->queryScalar()
        );
    }

    /**
     * @dataProvider invalidEventProvider
     */
    public function testCreateRejectsInvalidValues(array $body): void
    {
        Yii::$app->request->setBodyParams($body);

        $response = $this->controller()->actionCreate();

        $this->assertSame(422, Yii::$app->response->statusCode);
        $this->assertSame('error', $response->data['status']);
        $this->assertSame(0, (int) Yii::$app->db->createCommand(
            'SELECT COUNT(*) FROM special_event'
        )->queryScalar());
    }

    public function invalidEventProvider(): array
    {
        return [
            'blank lab' => [[
                'date' => '2026-08-29',
                'lab_name' => '   ',
            ]],
            'invalid date' => [[
                'date' => '29 Aug 2026',
                'lab_name' => 'General Hospital Lab',
            ]],
            'negative result' => [[
                'date' => '2026-08-29',
                'lab_name' => 'General Hospital Lab',
                'hcv_ab' => -1,
            ]],
            'non-numeric result' => [[
                'date' => '2026-08-29',
                'lab_name' => 'General Hospital Lab',
                'hcv_ab' => 'one',
            ]],
        ];
    }

    public function testDashboardSpecialEventCountUsesStoredRows(): void
    {
        $this->insertEvent('2026-08-01', 'Lab A', 1);
        $this->insertEvent('2026-08-02', 'Lab B', 1);

        $controller = new class('report', Yii::$app) extends ReportController {
            public function specialEventCount()
            {
                return $this->getTotalSpecialEvents();
            }
        };

        $this->assertSame(2, (int) $controller->specialEventCount());
    }

    private function controller(): SpecialEventController
    {
        return new SpecialEventController('special-event', Yii::$app);
    }

    private function insertEvent(string $date, string $labName, int $total): void
    {
        Yii::$app->db->createCommand()->insert('special_event', [
            'date' => $date,
            'haemoglobin' => $total,
            'hbs_ag' => 0,
            'hcv_ab' => 0,
            'mp_ict' => 0,
            'retro_test' => 0,
            'vdrl_test' => 0,
            'lab_name' => $labName,
            'total' => $total,
        ])->execute();
    }
}
