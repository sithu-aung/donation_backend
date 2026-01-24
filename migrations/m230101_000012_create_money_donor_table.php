<?php

use yii\db\Migration;

class m230101_000012_create_money_donor_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%money_donor}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'phone' => $this->string(),
            'address' => $this->string(),
            'note' => $this->text(),
            'is_organization' => $this->boolean()->defaultValue(false),
            'created_at' => $this->dateTime()->defaultExpression('CURRENT_TIMESTAMP'),
            'owner_id' => $this->string()->notNull(),
        ]);

        // Add index for faster searching
        $this->createIndex(
            'idx-money_donor-name',
            '{{%money_donor}}',
            'name'
        );
    }

    public function safeDown()
    {
        $this->dropIndex('idx-money_donor-name', '{{%money_donor}}');
        $this->dropTable('{{%money_donor}}');
    }
}
