<?php

use yii\db\Migration;

class m230101_000007_create_noti_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%noti}}', [
            'id' => $this->primaryKey(),
            'title' => $this->string(),
            'body' => $this->string(),
            'payload' => $this->string(),
            'created_at' => $this->dateTime(),
        ]);
    }

    public function safeDown()
    {
        $this->dropTable('{{%noti}}');
    }
}