<?php

use yii\db\Migration;

class m230101_000003_create_special_event_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%special_event}}', [
            'id' => $this->primaryKey(),
            'date' => $this->string(),
            'haemoglobin' => $this->integer(),
            'hbs_ag' => $this->integer(),
            'hcv_ab' => $this->integer(),
            'mp_ict' => $this->integer(),
            'retro_test' => $this->integer(),
            'vdrl_test' => $this->integer(),
            'lab_name' => $this->string(),
            'total' => $this->integer(),
        ]);
    }

    public function safeDown()
    {
        $this->dropTable('{{%special_event}}');
    }
}