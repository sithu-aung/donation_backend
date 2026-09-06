<?php

namespace app\models;

/**
 * One saved row in the request/give month worksheet.
 *
 * @property int $id
 * @property string $date
 * @property int|null $request
 * @property int|null $give
 */
class RequestGiveDaily extends \yii\db\ActiveRecord
{
    public static function tableName()
    {
        return 'request_give_daily';
    }

    public function rules()
    {
        return [
            [['date'], 'required'],
            [['date'], 'date', 'format' => 'php:Y-m-d'],
            [['date'], 'unique'],
            [['request', 'give'], 'integer', 'min' => 0],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'date' => 'Date',
            'request' => 'Request',
            'give' => 'Give',
        ];
    }
}
