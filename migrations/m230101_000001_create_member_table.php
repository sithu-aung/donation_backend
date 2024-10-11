<?php

use yii\db\Migration;

class m230101_000001_create_member_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%member}}', [
            'id' => $this->primaryKey(),
            'birth_date' => $this->string(),
            'blood_bank_card' => $this->string(),
            'blood_type' => $this->string(),
            'father_name' => $this->string(),
            'last_date' => $this->dateTime(),
            'member_count' => $this->string(),
            'member_id' => $this->string(),
            'name' => $this->string(),
            'note' => $this->string(),
            'nrc' => $this->string(),
            'phone' => $this->string(),
            'address' => $this->string(),
            'gender' => $this->string(),
            'profile_url' => $this->string(),
            'register_date' => $this->dateTime(),
            'total_count' => $this->string(),
            'status' => $this->string(),
            'owner_id' => $this->string()->notNull(),
        ]);
    }

    public function safeDown()
    {
        $this->dropTable('{{%member}}');
    }
}