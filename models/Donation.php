<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "donation".
 *
 * @property int $id
 * @property string|null $date
 * @property string|null $donation_date
 * @property string|null $hospital
 * @property string|null $member_id
 * @property int|null $member
 * @property string|null $patient_address
 * @property string|null $patient_age
 * @property string|null $patient_disease
 * @property string|null $patient_name
 * @property int|null $patient_id
 * @property string|null $facebook_post_time
 * @property string $owner_id
 *
 * @property Member $member0
 * @property Patient $patient
 */
class Donation extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'donation';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['donation_date'], 'safe'],
            [['member', 'patient_id'], 'default', 'value' => null],
            [['member', 'patient_id'], 'integer'],
            [['owner_id'], 'required'],
            [['date', 'hospital', 'member_id', 'patient_address', 'patient_age', 'patient_disease', 'patient_name', 'owner_id'], 'string', 'max' => 255],
            [['facebook_post_time'], 'string', 'max' => 64],
            [['member'], 'exist', 'skipOnError' => true, 'targetClass' => Member::class, 'targetAttribute' => ['member' => 'id']],
            [['patient_id'], 'exist', 'skipOnError' => true, 'targetClass' => Patient::class, 'targetAttribute' => ['patient_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'date' => 'Date',
            'donation_date' => 'Donation Date',
            'hospital' => 'Hospital',
            'member_id' => 'Member ID',
            'member' => 'Member',
            'patient_address' => 'Patient Address',
            'patient_age' => 'Patient Age',
            'patient_disease' => 'Patient Disease',
            'patient_name' => 'Patient Name',
            'patient_id' => 'Patient ID',
            'facebook_post_time' => 'Facebook Post Time',
            'owner_id' => 'Owner ID',
        ];
    }

    /**
     * Gets query for [[Member0]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getMember0()
    {
        return $this->hasOne(Member::class, ['id' => 'member']);
    }

    /**
     * Gets query for [[Patient]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getPatient()
    {
        return $this->hasOne(Patient::class, ['id' => 'patient_id']);
    }
}
