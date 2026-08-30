<?php

use yii\db\Migration;

/**
 * Per-session access tokens.
 *
 * Historically each account held a single access_token column that every login
 * overwrote, so a login on one device silently logged out every other device
 * using the same account. Sessions now live in this table: one row per login,
 * so any number of devices can stay signed in to the same account at once.
 * The legacy column stays readable so sessions issued before this deploy keep
 * working until those devices next log in.
 */
class m260830_000001_create_account_token_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%account_token}}', [
            'id' => $this->primaryKey(),
            'account_id' => $this->integer()->notNull(),
            'token' => $this->string(64)->notNull()->unique(),
            'created_at' => $this->timestamp()
                ->notNull()
                ->defaultExpression('CURRENT_TIMESTAMP'),
            'last_used_at' => $this->timestamp()
                ->notNull()
                ->defaultExpression('CURRENT_TIMESTAMP'),
        ]);

        $this->createIndex(
            'idx-account_token-account_id',
            '{{%account_token}}',
            'account_id'
        );

        $this->addForeignKey(
            'fk-account_token-account_id',
            '{{%account_token}}',
            'account_id',
            '{{%account}}',
            'id',
            'CASCADE'
        );
    }

    public function safeDown()
    {
        $this->dropTable('{{%account_token}}');
    }
}
