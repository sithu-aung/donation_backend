<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "patient".
 *
 * @property int $id
 * @property string $name
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $township
 * @property string|null $ward
 * @property string|null $village
 * @property string|null $age
 * @property string|null $birth_date
 * @property string|null $gender
 * @property string|null $blood_type
 * @property string|null $medical_notes
 * @property string|null $created_at
 * @property string $owner_id
 *
 * @property Donation[] $donations
 */
class Patient extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'patient';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['name', 'owner_id'], 'required'],
            [['medical_notes'], 'string'],
            [['created_at', 'birth_date'], 'safe'],
            [['name', 'phone', 'address', 'township', 'ward', 'village', 'age', 'gender', 'blood_type', 'owner_id'], 'string', 'max' => 255],
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
            'township' => 'Township',
            'ward' => 'Ward',
            'village' => 'Village',
            'age' => 'Age',
            'birth_date' => 'Birth Date',
            'gender' => 'Gender',
            'blood_type' => 'Blood Type',
            'medical_notes' => 'Medical Notes',
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
        return $this->hasMany(Donation::class, ['patient_id' => 'id'])
            ->orderBy(['donation_date' => SORT_DESC]);
    }

    /**
     * Get the count of donations for this patient
     *
     * @return int
     */
    public function getDonationCount()
    {
        return $this->hasMany(Donation::class, ['patient_id' => 'id'])->count();
    }
}
