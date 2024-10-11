<?php

use yii\db\Migration;

class m230101_000002_create_donation_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%donation}}', [
            'id' => $this->primaryKey(),
            'date' => $this->string(),
            'donation_date' => $this->dateTime(),
            'hospital' => $this->string(),
            'member_id' => $this->string(),
            'member' => $this->integer(),
            'patient_address' => $this->string(),
            'patient_age' => $this->string(),
            'patient_disease' => $this->string(),
            'patient_name' => $this->string(),
            'owner_id' => $this->string()->notNull(),
        ]);

        // Add foreign key for table `member`
        $this->addForeignKey(
            'fk-donation-member',
            '{{%donation}}',
            'member',
            '{{%member}}',
            'id',
            'CASCADE'
        );
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk-donation-member', '{{%donation}}');
        $this->dropTable('{{%donation}}');
    }
}