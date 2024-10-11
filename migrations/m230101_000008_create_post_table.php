<?php

use yii\db\Migration;

class m230101_000008_create_post_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%post}}', [
            'id' => $this->primaryKey(),
            'text' => $this->text(),
            'created_at' => $this->dateTime(),
            'posted_by' => $this->string(),
            'poster_profile_url' => $this->string(),
        ]);

        // Assuming you have a separate table for images, reactions, and comments
    }

    public function safeDown()
    {
        $this->dropTable('{{%post}}');
    }
}