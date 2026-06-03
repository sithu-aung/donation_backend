<?php

namespace app\models;

/**
 * This is the model class for table "monthly_sponsor_donation".
 *
 * @property int $id
 * @property int $sponsor_id
 * @property int|null $amount
 * @property string|null $date
 * @property string|null $note
 * @property string|null $created_at
 *
 * @property MonthlySponsor $sponsor
 */
class MonthlySponsorDonation extends \yii\db\ActiveRecord
{
    public static function tableName()
    {
        return 'monthly_sponsor_donation';
    }

    public function rules()
    {
        return [
            [['sponsor_id'], 'required'],
            [['sponsor_id', 'amount'], 'integer'],
            [['amount'], 'default', 'value' => 0],
            [['date', 'created_at'], 'safe'],
            [['note'], 'string', 'max' => 255],
            [['sponsor_id'], 'exist', 'skipOnError' => true, 'targetClass' => MonthlySponsor::class, 'targetAttribute' => ['sponsor_id' => 'id']],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'sponsor_id' => 'Sponsor ID',
            'amount' => 'Amount',
            'date' => 'Date',
            'note' => 'Note',
            'created_at' => 'Created At',
        ];
    }

    public function getSponsor()
    {
        return $this->hasOne(MonthlySponsor::class, ['id' => 'sponsor_id']);
    }
}
