<?php

use yii\db\Migration;

class m230101_000004_create_donar_record_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%donar_record}}', [
            'id' => $this->primaryKey(),
            'amount' => $this->integer(),
            'date' => $this->dateTime(),
            'name' => $this->string(),
        ]);
    }

    public function safeDown()
    {
        $this->dropTable('{{%donar_record}}');
    }
}