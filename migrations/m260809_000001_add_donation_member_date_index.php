<?php

use yii\db\Migration;

class m260809_000001_add_donation_member_date_index extends Migration
{
    public function safeUp()
    {
        $this->createIndex(
            'idx-donation-member-donation_date',
            '{{%donation}}',
            ['member', 'donation_date']
        );
    }

    public function safeDown()
    {
        $this->dropIndex(
            'idx-donation-member-donation_date',
            '{{%donation}}'
        );
    }
}
