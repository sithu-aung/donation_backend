<?php

use yii\db\Migration;

class m230101_000006_create_request_give_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%request_give}}', [
            'id' => $this->primaryKey(),
            'request' => $this->integer(),
            'give' => $this->integer(),
            'date' => $this->dateTime(),
        ]);
    }

    public function safeDown()
    {
        $this->dropTable('{{%request_give}}');
    }
}
