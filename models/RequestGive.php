<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "request_give".
 *
 * @property int $id
 * @property int|null $request
 * @property int|null $give
 * @property string|null $date
 */
class RequestGive extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'request_give';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['request', 'give'], 'default', 'value' => null],
            [['request', 'give'], 'integer'],
            [['date'], 'safe'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'request' => 'Request',
            'give' => 'Give',
            'date' => 'Date',
        ];
    }
}
