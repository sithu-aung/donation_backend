<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "money_donor".
 *
 * @property int $id
 * @property string $name
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $note
 * @property bool|null $is_organization
 * @property string|null $created_at
 * @property string $owner_id
 *
 * @property DonarRecord[] $donations
 */
class MoneyDonor extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'money_donor';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['name', 'owner_id'], 'required'],
            [['note'], 'string'],
            [['is_organization'], 'boolean'],
            [['created_at'], 'safe'],
            [['name', 'phone', 'address', 'owner_id'], 'string', 'max' => 255],
            [['is_organization'], 'default', 'value' => false],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'name' => 'Name',
            'phone' => 'Phone',
            'address' => 'Address',
            'note' => 'Note',
            'is_organization' => 'Is Organization',
            'created_at' => 'Created At',
            'owner_id' => 'Owner ID',
        ];
    }

    /**
     * Gets query for [[Donations]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getDonations()
    {
        return $this->hasMany(DonarRecord::class, ['money_donor_id' => 'id'])
            ->orderBy(['date' => SORT_DESC]);
    }

    /**
     * Get the count of donations from this donor
     *
     * @return int
     */
    public function getDonationCount()
    {
        return $this->hasMany(DonarRecord::class, ['money_donor_id' => 'id'])->count();
    }

    /**
     * Get total amount donated by this donor
     *
     * @return int
     */
    public function getTotalAmount()
    {
        return (int) $this->hasMany(DonarRecord::class, ['money_donor_id' => 'id'])->sum('amount');
    }
}
