<?php

use yii\db\Migration;

class m230101_000013_add_patient_id_to_donation extends Migration
{
    public function safeUp()
    {
        // Add patient_id column (nullable for backward compatibility)
        $this->addColumn('{{%donation}}', 'patient_id', $this->integer());

        // Add foreign key constraint
        $this->addForeignKey(
            'fk-donation-patient',
            '{{%donation}}',
            'patient_id',
            '{{%patient}}',
            'id',
            'SET NULL',
            'CASCADE'
        );

        // Add index for faster lookups
        $this->createIndex(
            'idx-donation-patient_id',
            '{{%donation}}',
            'patient_id'
        );
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk-donation-patient', '{{%donation}}');
        $this->dropIndex('idx-donation-patient_id', '{{%donation}}');
        $this->dropColumn('{{%donation}}', 'patient_id');
    }
}
