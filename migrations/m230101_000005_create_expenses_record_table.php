<?php

use yii\db\Migration;

class m230101_000005_create_expenses_record_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%expenses_record}}', [
            'id' => $this->primaryKey(),
            'amount' => $this->integer(),
            'date' => $this->dateTime(),
            'name' => $this->string(),
        ]);
    }

    public function safeDown()
    {
        $this->dropTable('{{%expenses_record}}');
    }
}
