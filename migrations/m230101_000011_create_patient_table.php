<?php

use yii\db\Migration;

class m230101_000011_create_patient_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%patient}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'phone' => $this->string(),
            'address' => $this->string(),
            'age' => $this->string(),
            'gender' => $this->string(),
            'medical_notes' => $this->text(),
            'created_at' => $this->dateTime()->defaultExpression('CURRENT_TIMESTAMP'),
            'owner_id' => $this->string()->notNull(),
        ]);

        // Add index for faster searching
        $this->createIndex(
            'idx-patient-name',
            '{{%patient}}',
            'name'
        );
    }

    public function safeDown()
    {
        $this->dropIndex('idx-patient-name', '{{%patient}}');
        $this->dropTable('{{%patient}}');
    }
}
