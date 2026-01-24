<?php

use yii\db\Migration;

class m230101_000014_add_money_donor_id_to_donar_record extends Migration
{
    public function safeUp()
    {
        // Add money_donor_id column (nullable for backward compatibility)
        $this->addColumn('{{%donar_record}}', 'money_donor_id', $this->integer());

        // Add foreign key constraint
        $this->addForeignKey(
            'fk-donar_record-money_donor',
            '{{%donar_record}}',
            'money_donor_id',
            '{{%money_donor}}',
            'id',
            'SET NULL',
            'CASCADE'
        );

        // Add index for faster lookups
        $this->createIndex(
            'idx-donar_record-money_donor_id',
            '{{%donar_record}}',
            'money_donor_id'
        );
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk-donar_record-money_donor', '{{%donar_record}}');
        $this->dropIndex('idx-donar_record-money_donor_id', '{{%donar_record}}');
        $this->dropColumn('{{%donar_record}}', 'money_donor_id');
    }
}
