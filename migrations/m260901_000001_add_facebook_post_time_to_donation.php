<?php

use yii\db\Migration;

class m260901_000001_add_facebook_post_time_to_donation extends Migration
{
    public function safeUp()
    {
        $this->addColumn(
            '{{%donation}}',
            'facebook_post_time',
            $this->string(64)
        );
    }

    public function safeDown()
    {
        $this->dropColumn('{{%donation}}', 'facebook_post_time');
    }
}
