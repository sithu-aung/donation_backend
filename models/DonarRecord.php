<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "donar_record".
 *
 * @property int $id
 * @property int|null $amount
 * @property string|null $date
 * @property string|null $name
 */
class DonarRecord extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'donar_record';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['amount'], 'default', 'value' => null],
            [['amount'], 'integer'],
            [['date'], 'safe'],
            [['name'], 'string', 'max' => 255],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'amount' => 'Amount',
            'date' => 'Date',
            'name' => 'Name',
        ];
    }
}
