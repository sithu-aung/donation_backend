<?php

use yii\db\Migration;

class m230101_000010_create_comment_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%comment}}', [
            'id' => $this->primaryKey(),
            'text' => $this->text(),
            'member_id' => $this->integer(),
            'created_at' => $this->dateTime(),
        ]);

        // Add foreign key for table `member`
        $this->addForeignKey(
            'fk-comment-member',
            '{{%comment}}',
            'member_id',
            '{{%member}}',
            'id',
            'CASCADE'
        );
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk-comment-member', '{{%comment}}');
        $this->dropTable('{{%comment}}');
    }
}
