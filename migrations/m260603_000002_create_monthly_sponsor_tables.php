<?php

use yii\db\Migration;

/**
 * Monthly Sponsors (လစဥ်ထောက်ပြံ့သူများ): a standalone record of recurring
 * sponsors and their per-month donation amounts. Intentionally separate from
 * donar_record / expenses_record so it never appears in income/expense reports.
 */
class m260603_000002_create_monthly_sponsor_tables extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%monthly_sponsor}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'phone' => $this->string(),
            'note' => $this->text(),
            'created_at' => $this->dateTime()->defaultExpression('CURRENT_TIMESTAMP'),
            'owner_id' => $this->string()->notNull(),
        ]);
        $this->createIndex('idx-monthly_sponsor-name', '{{%monthly_sponsor}}', 'name');

        $this->createTable('{{%monthly_sponsor_donation}}', [
            'id' => $this->primaryKey(),
            'sponsor_id' => $this->integer()->notNull(),
            'amount' => $this->integer(),
            'date' => $this->dateTime(),
            'note' => $this->string(),
            'created_at' => $this->dateTime()->defaultExpression('CURRENT_TIMESTAMP'),
        ]);
        $this->createIndex(
            'idx-monthly_sponsor_donation-sponsor_id',
            '{{%monthly_sponsor_donation}}',
            'sponsor_id'
        );
        $this->addForeignKey(
            'fk-monthly_sponsor_donation-sponsor',
            '{{%monthly_sponsor_donation}}',
            'sponsor_id',
            '{{%monthly_sponsor}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk-monthly_sponsor_donation-sponsor', '{{%monthly_sponsor_donation}}');
        $this->dropTable('{{%monthly_sponsor_donation}}');
        $this->dropIndex('idx-monthly_sponsor-name', '{{%monthly_sponsor}}');
        $this->dropTable('{{%monthly_sponsor}}');
    }
}
