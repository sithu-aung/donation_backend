<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "member".
 *
 * @property int $id
 * @property string|null $birth_date
 * @property string|null $blood_bank_card
 * @property string|null $blood_type
 * @property string|null $father_name
 * @property string|null $last_date
 * @property string|null $member_count
 * @property string|null $member_id
 * @property string|null $name
 * @property string|null $note
 * @property string|null $nrc
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $gender
 * @property string|null $profile_url
 * @property string|null $register_date
 * @property string|null $total_count
 * @property string|null $status
 * @property string $owner_id
 *
 * @property Comment[] $comments
 * @property Donation[] $donations
 * @property Reaction[] $reactions
 */
class Member extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'member';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['last_date', 'register_date'], 'safe'],
            [['owner_id'], 'required'],
            [['birth_date', 'blood_bank_card', 'blood_type', 'father_name', 'member_count', 'member_id', 'name', 'note', 'nrc', 'phone', 'address', 'gender', 'profile_url', 'total_count', 'status', 'owner_id'], 'string', 'max' => 255],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'birth_date' => 'Birth Date',
            'blood_bank_card' => 'Blood Bank Card',
            'blood_type' => 'Blood Type',
            'father_name' => 'Father Name',
            'last_date' => 'Last Date',
            'member_count' => 'Member Count',
            'member_id' => 'Member ID',
            'name' => 'Name',
            'note' => 'Note',
            'nrc' => 'Nrc',
            'phone' => 'Phone',
            'address' => 'Address',
            'gender' => 'Gender',
            'profile_url' => 'Profile Url',
            'register_date' => 'Register Date',
            'total_count' => 'Total Count',
            'status' => 'Status',
            'owner_id' => 'Owner ID',
        ];
    }

    /**
     * Gets query for [[Comments]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getComments()
    {
        return $this->hasMany(Comment::class, ['member_id' => 'id']);
    }

    /**
     * Gets query for [[Donations]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getDonations()
    {
        return $this->hasMany(Donation::class, ['member' => 'id']);
    }

    /**
     * Gets query for [[Reactions]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getReactions()
    {
        return $this->hasMany(Reaction::class, ['member_id' => 'id']);
    }
}
