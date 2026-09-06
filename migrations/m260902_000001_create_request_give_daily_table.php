<?php

use yii\db\Migration;

/**
 * Stores the day rows and month-level revision state behind the request/give
 * worksheet.
 *
 * The existing request_give table remains the source of monthly summaries so
 * imported historical totals keep their original meaning.
 */
class m260902_000001_create_request_give_daily_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%request_give_daily}}', [
            'id' => $this->primaryKey(),
            'date' => $this->date()->notNull(),
            // NULL means staff have not entered the value; zero is an explicit
            // confirmation that there was no activity that day.
            'request' => $this->integer()->check('request >= 0'),
            'give' => $this->integer()->check('give >= 0'),
        ]);

        $this->createIndex(
            'ux_request_give_daily_date',
            '{{%request_give_daily}}',
            'date',
            true
        );

        // A month-state row marks worksheet ownership independently of daily
        // rows. This lets staff clear a worksheet completely without the old
        // aggregate being mistaken for an imported legacy-only month.
        $this->createTable('{{%request_give_month_state}}', [
            'id' => $this->primaryKey(),
            'year' => $this->integer()->notNull(),
            'month' => $this->integer()->notNull(),
            'revision' => $this->bigInteger()->notNull()->defaultValue(0)
                ->check('revision >= 0'),
        ]);

        $this->createIndex(
            'ux_request_give_month_state_year_month',
            '{{%request_give_month_state}}',
            ['year', 'month'],
            true
        );
    }

    public function safeDown()
    {
        $this->dropTable('{{%request_give_month_state}}');
        $this->dropTable('{{%request_give_daily}}');
    }
}
