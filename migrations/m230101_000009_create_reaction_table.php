<?php

use yii\db\Migration;

class m230101_000009_create_reaction_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%reaction}}', [
            'id' => $this->primaryKey(),
            'emoji' => $this->string(),
            'type' => $this->string(),
            'member_id' => $this->integer(),
            'created_at' => $this->dateTime(),
        ]);

        // Add foreign key for table `member`
        $this->addForeignKey(
            'fk-reaction-member',
            '{{%reaction}}',
            'member_id',
            '{{%member}}',
            'id',
            'CASCADE'
        );
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk-reaction-member', '{{%reaction}}');
        $this->dropTable('{{%reaction}}');
    }
}