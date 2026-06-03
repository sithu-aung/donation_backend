<?php

namespace app\models;

/**
 * This is the model class for table "monthly_sponsor".
 *
 * @property int $id
 * @property string $name
 * @property string|null $phone
 * @property string|null $note
 * @property string|null $created_at
 * @property string $owner_id
 *
 * @property MonthlySponsorDonation[] $donations
 */
class MonthlySponsor extends \yii\db\ActiveRecord
{
    public static function tableName()
    {
        return 'monthly_sponsor';
    }

    public function rules()
    {
        return [
            [['name', 'owner_id'], 'required'],
            [['note'], 'string'],
            [['created_at'], 'safe'],
            [['name', 'phone', 'owner_id'], 'string', 'max' => 255],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'name' => 'Name',
            'phone' => 'Phone',
            'note' => 'Note',
            'created_at' => 'Created At',
            'owner_id' => 'Owner ID',
        ];
    }

    public function getDonations()
    {
        return $this->hasMany(MonthlySponsorDonation::class, ['sponsor_id' => 'id']);
    }
}
