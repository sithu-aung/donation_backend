<?php

namespace tests\unit\controllers;

use app\controllers\DonationController;
use Codeception\Test\Unit;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\UnauthorizedHttpException;

class DonationControllerTest extends Unit
{
    private const VALID_TOKEN = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    protected function _before(): void
    {
        parent::_before();

        $db = Yii::$app->db;
        $db->createCommand('DROP TABLE IF EXISTS account_token')->execute();
        $db->createCommand('DROP TABLE IF EXISTS donation')->execute();
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
CREATE TABLE donation (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    donation_date TEXT NULL,
    hospital TEXT NULL,
    patient_id INTEGER NULL,
    patient_name TEXT NULL,
    facebook_post_time TEXT NULL,
    owner_id TEXT NOT NULL
)
SQL)->execute();

        $db->createCommand()->insert('account', [
            'name' => 'Test staff',
            'password_hash' => 'unused-in-controller-test',
            'access_token' => self::VALID_TOKEN,
        ])->execute();

        Yii::$app->request->setBodyParams([]);
        Yii::$app->request->headers->removeAll();
        Yii::$app->response->clear();
    }

    public function testAuthenticationIsRequired(): void
    {
        $this->post([
            'donation_ids' => [1],
            'time_of_day' => 'မနက်ပိုင်း',
        ]);

        $this->expectException(UnauthorizedHttpException::class);
        $this->controller()->actionSavePostTime();
    }

    public function testInvalidBearerTokenIsRejected(): void
    {
        $this->post([
            'donation_ids' => [1],
            'time_of_day' => 'မနက်ပိုင်း',
        ], str_repeat('z', 64));

        $this->expectException(UnauthorizedHttpException::class);
        $this->controller()->actionSavePostTime();
    }

    public function testSavesTheTimeForEveryDonationInTheCurrentGroup(): void
    {
        $firstId = $this->insertDonation('2026-09-01 08:15:00', 42, 'Patient A', 'General Hospital');
        $secondId = $this->insertDonation('2026-09-01 17:45:00', 42, 'Renamed Patient', ' General Hospital ');
        $otherDateId = $this->insertDonation('2026-09-02 08:15:00', 42, 'Patient A', 'General Hospital');
        $otherPatientId = $this->insertDonation('2026-09-01 08:15:00', 43, 'Patient B', 'General Hospital');
        $otherHospitalId = $this->insertDonation('2026-09-01 08:15:00', 42, 'Patient A', 'Other Hospital');

        $this->post([
            // Duplicate string IDs also prove that client input is normalized.
            'donation_ids' => [(string) $firstId, (string) $firstId],
            'time_of_day' => 'ညနေပိုင်း',
        ], self::VALID_TOKEN);

        $response = $this->controller()->actionSavePostTime();

        $this->assertSame('ok', $response->data['status']);
        $this->assertSame([$firstId, $secondId], $response->data['data']['donation_ids']);
        $this->assertSame('ညနေပိုင်း', $response->data['data']['time_of_day']);
        $this->assertSame(2, $response->data['data']['updated_count']);
        $this->assertSame('ညနေပိုင်း', $this->savedTime($firstId));
        $this->assertSame('ညနေပိုင်း', $this->savedTime($secondId));
        $this->assertNull($this->savedTime($otherDateId));
        $this->assertNull($this->savedTime($otherPatientId));
        $this->assertNull($this->savedTime($otherHospitalId));
    }

    public function testRejectsMalformedDonationIds(): void
    {
        $this->post([
            'donation_ids' => ['1.5'],
            'time_of_day' => 'မနက်ပိုင်း',
        ], self::VALID_TOKEN);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Every donation_id must be a positive integer.');
        $this->controller()->actionSavePostTime();
    }

    public function testRejectsAnEmptyDonationIdList(): void
    {
        $this->post([
            'donation_ids' => [],
            'time_of_day' => 'မနက်ပိုင်း',
        ], self::VALID_TOKEN);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('donation_ids must be a non-empty array.');
        $this->controller()->actionSavePostTime();
    }

    public function testRejectsDonationIdsFromMixedGroupsWithoutSaving(): void
    {
        $firstId = $this->insertDonation('2026-09-01 08:15:00', 42, 'Patient A', 'General Hospital');
        $secondId = $this->insertDonation('2026-09-01 08:15:00', 43, 'Patient B', 'General Hospital');

        $this->post([
            'donation_ids' => [$firstId, $secondId],
            'time_of_day' => 'မနက်ပိုင်း',
        ], self::VALID_TOKEN);

        try {
            $this->controller()->actionSavePostTime();
            $this->fail('Mixed donation groups should be rejected.');
        } catch (BadRequestHttpException $error) {
            $this->assertSame(
                'donation_ids must belong to one date, patient, and hospital.',
                $error->getMessage()
            );
        }

        $this->assertNull($this->savedTime($firstId));
        $this->assertNull($this->savedTime($secondId));
    }

    public function testRejectsADeletedDonationIdWithoutSaving(): void
    {
        $existingId = $this->insertDonation('2026-09-01 08:15:00', 42, 'Patient A', 'General Hospital');

        $this->post([
            'donation_ids' => [$existingId, 99999],
            'time_of_day' => 'မနက်ပိုင်း',
        ], self::VALID_TOKEN);

        try {
            $this->controller()->actionSavePostTime();
            $this->fail('A missing donation ID should be rejected.');
        } catch (BadRequestHttpException $error) {
            $this->assertSame('One or more donations no longer exist.', $error->getMessage());
        }

        $this->assertNull($this->savedTime($existingId));
    }

    public function testAcceptsAndPersistsAValidCustomNightTime(): void
    {
        $donationId = $this->insertDonation('2026-09-01 20:00:00', null, 'Patient Without Record', 'General Hospital');

        $this->post([
            'donation_ids' => [$donationId],
            'time_of_day' => 'ည(၁၀:၃၀)',
        ], self::VALID_TOKEN);

        $response = $this->controller()->actionSavePostTime();

        $this->assertSame('ည(၁၀:၃၀)', $response->data['data']['time_of_day']);
        $this->assertSame('ည(၁၀:၃၀)', $this->savedTime($donationId));
    }

    public function testRejectsAnInvalidCustomNightTimeWithoutSaving(): void
    {
        $donationId = $this->insertDonation('2026-09-01 20:00:00', null, 'Patient Without Record', 'General Hospital');

        $this->post([
            'donation_ids' => [$donationId],
            'time_of_day' => 'ည(၁၃:၆၀)',
        ], self::VALID_TOKEN);

        try {
            $this->controller()->actionSavePostTime();
            $this->fail('An invalid custom night time should be rejected.');
        } catch (BadRequestHttpException $error) {
            $this->assertSame(
                'time_of_day is not a supported Facebook post time.',
                $error->getMessage()
            );
        }

        $this->assertNull($this->savedTime($donationId));
    }

    private function controller(): DonationController
    {
        return new DonationController('donation', Yii::$app);
    }

    private function post(array $bodyParams, ?string $token = null): void
    {
        Yii::$app->request->setBodyParams($bodyParams);
        Yii::$app->request->headers->removeAll();
        if ($token !== null) {
            Yii::$app->request->headers->set('Authorization', 'Bearer ' . $token);
        }
    }

    private function insertDonation(
        string $donationDate,
        ?int $patientId,
        string $patientName,
        string $hospital
    ): int {
        Yii::$app->db->createCommand()->insert('donation', [
            'donation_date' => $donationDate,
            'patient_id' => $patientId,
            'patient_name' => $patientName,
            'hospital' => $hospital,
            'facebook_post_time' => null,
            'owner_id' => 'test-owner',
        ])->execute();

        return (int) Yii::$app->db->getLastInsertID();
    }

    private function savedTime(int $donationId): ?string
    {
        $value = Yii::$app->db->createCommand(
            'SELECT facebook_post_time FROM donation WHERE id = :id',
            [':id' => $donationId]
        )->queryScalar();

        return $value === false || $value === null ? null : (string) $value;
    }
}
