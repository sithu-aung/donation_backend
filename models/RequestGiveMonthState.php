<?php

namespace app\models;

/**
 * Persistent ownership and optimistic-concurrency state for one worksheet month.
 *
 * The presence of a row means the month belongs to the daily worksheet, even
 * when every daily value has subsequently been cleared.
 *
 * @property int $id
 * @property int $year
 * @property int $month
 * @property int $revision
 */
class RequestGiveMonthState extends \yii\db\ActiveRecord
{
    public static function tableName()
    {
        return 'request_give_month_state';
    }

    public function rules()
    {
        return [
            [['year', 'month', 'revision'], 'required'],
            [['year'], 'integer', 'min' => 1900, 'max' => 9999],
            [['month'], 'integer', 'min' => 1, 'max' => 12],
            [['revision'], 'integer', 'min' => 0],
            [['year', 'month'], 'unique', 'targetAttribute' => ['year', 'month']],
        ];
    }
}
