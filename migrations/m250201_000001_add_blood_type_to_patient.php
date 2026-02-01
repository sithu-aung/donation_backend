<?php

use yii\db\Migration;

/**
 * Handles adding blood_type column to patient table.
 */
class m250201_000001_add_blood_type_to_patient extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('patient', 'blood_type', $this->string(20)->after('gender'));
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropColumn('patient', 'blood_type');
    }
}
