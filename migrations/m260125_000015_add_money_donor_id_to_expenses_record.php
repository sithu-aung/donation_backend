<?php

use yii\db\Migration;

/**
 * Handles adding columns to table `{{%expenses_record}}`.
 */
class m260125_000015_add_money_donor_id_to_expenses_record extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('{{%expenses_record}}', 'money_donor_id', $this->integer()->null());

        // Add foreign key constraint
        $this->addForeignKey(
            'fk-expenses_record-money_donor_id',
            '{{%expenses_record}}',
            'money_donor_id',
            '{{%money_donor}}',
            'id',
            'SET NULL',
            'CASCADE'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        // Drop foreign key
        $this->dropForeignKey('fk-expenses_record-money_donor_id', '{{%expenses_record}}');

        // Drop column
        $this->dropColumn('{{%expenses_record}}', 'money_donor_id');
    }
}
