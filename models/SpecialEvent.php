<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "special_event".
 *
 * @property int $id
 * @property string|null $date
 * @property int|null $haemoglobin
 * @property int|null $hbs_ag
 * @property int|null $hcv_ab
 * @property int|null $mp_ict
 * @property int|null $retro_test
 * @property int|null $vdrl_test
 * @property string|null $lab_name
 * @property int|null $total
 */
class SpecialEvent extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'special_event';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['haemoglobin', 'hbs_ag', 'hcv_ab', 'mp_ict', 'retro_test', 'vdrl_test', 'total'], 'default', 'value' => null],
            [['haemoglobin', 'hbs_ag', 'hcv_ab', 'mp_ict', 'retro_test', 'vdrl_test', 'total'], 'integer'],
            [['date', 'lab_name'], 'string', 'max' => 255],
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
            'haemoglobin' => 'Haemoglobin',
            'hbs_ag' => 'Hbs Ag',
            'hcv_ab' => 'Hcv Ab',
            'mp_ict' => 'Mp Ict',
            'retro_test' => 'Retro Test',
            'vdrl_test' => 'Vdrl Test',
            'lab_name' => 'Lab Name',
            'total' => 'Total',
        ];
    }
}
