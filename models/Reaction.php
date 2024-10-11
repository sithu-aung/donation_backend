<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "reaction".
 *
 * @property int $id
 * @property string|null $emoji
 * @property string|null $type
 * @property int|null $member_id
 * @property string|null $created_at
 *
 * @property Member $member
 */
class Reaction extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'reaction';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['member_id'], 'default', 'value' => null],
            [['member_id'], 'integer'],
            [['created_at'], 'safe'],
            [['emoji', 'type'], 'string', 'max' => 255],
            [['member_id'], 'exist', 'skipOnError' => true, 'targetClass' => Member::class, 'targetAttribute' => ['member_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'emoji' => 'Emoji',
            'type' => 'Type',
            'member_id' => 'Member ID',
            'created_at' => 'Created At',
        ];
    }

    /**
     * Gets query for [[Member]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getMember()
    {
        return $this->hasOne(Member::class, ['id' => 'member_id']);
    }
}
