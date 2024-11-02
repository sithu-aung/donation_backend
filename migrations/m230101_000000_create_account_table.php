<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%account}}`.
 */
class m230101_000000_create_account_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%account}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string(),
            'email' => $this->string()->unique(),
            'phone' => $this->string(),
            'password_hash' => $this->string()->notNull(),
            'access_token' => $this->string(64)->notNull(),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%account}}');
    }
}
