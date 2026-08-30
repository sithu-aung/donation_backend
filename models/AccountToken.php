<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;
use yii\db\Expression;

/**
 * One row per signed-in session.
 *
 * Logging in issues a new row instead of rotating a shared column, so several
 * devices can hold the same account at once and none of them can log the
 * others out. Rows have no fixed expiry — staff keep long-lived sessions — but
 * rows untouched for over a year are pruned on that account's next login.
 *
 * @property int $id
 * @property int $account_id
 * @property string $token
 * @property string $created_at
 * @property string $last_used_at
 */
class AccountToken extends ActiveRecord
{
    /** Sessions idle longer than this are pruned at the next login. */
    private const PRUNE_IDLE_INTERVAL = '1 year';

    public static function tableName(): string
    {
        return 'account_token';
    }

    /**
     * Mints and stores a fresh session token for [$account] without touching
     * any other session of that account.
     */
    public static function issueFor(Account $account): string
    {
        $token = Yii::$app->security->generateRandomString(64);
        Yii::$app->db->createCommand()->insert(static::tableName(), [
            'account_id' => $account->id,
            'token' => $token,
        ])->execute();

        // Self-pruning keeps the table small without needing a cron job.
        Yii::$app->db->createCommand()->delete(
            static::tableName(),
            'account_id = :account AND last_used_at < ' .
                "(CURRENT_TIMESTAMP - INTERVAL '" . self::PRUNE_IDLE_INTERVAL . "')",
            [':account' => $account->id]
        )->execute();

        return $token;
    }

    /**
     * Resolves a bearer token to its account, or null.
     *
     * Checks per-session rows first, then the legacy account.access_token
     * column so sessions issued before the account_token deploy keep working.
     * The table lookup is shielded so the moments between "code pulled" and
     * "migration applied" degrade to legacy-only auth instead of a 500.
     */
    public static function findAccountByToken(string $token): ?Account
    {
        if (strlen($token) !== 64) {
            return null;
        }

        try {
            $row = static::findOne(['token' => $token]);
            if ($row !== null) {
                // Touch at most once an hour so pruning can see real usage
                // without adding a write to every request.
                Yii::$app->db->createCommand()->update(
                    static::tableName(),
                    ['last_used_at' => new Expression('CURRENT_TIMESTAMP')],
                    "id = :id AND last_used_at < (CURRENT_TIMESTAMP - INTERVAL '1 hour')",
                    [':id' => $row->id]
                )->execute();
                return Account::findOne(['id' => $row->account_id]);
            }
        } catch (\Throwable $error) {
            Yii::error(
                'account_token lookup failed, falling back to legacy token: '
                    . $error->getMessage(),
                __METHOD__
            );
        }

        return Account::findOne(['access_token' => $token]);
    }

    /**
     * Deletes the session behind [$token]. Unknown (already-revoked or legacy)
     * tokens are a no-op: logout must always succeed on the client.
     */
    public static function revoke(string $token): void
    {
        if (strlen($token) !== 64) {
            return;
        }
        try {
            static::deleteAll(['token' => $token]);
        } catch (\Throwable $error) {
            Yii::error('token revoke failed: ' . $error->getMessage(), __METHOD__);
        }
    }
}
