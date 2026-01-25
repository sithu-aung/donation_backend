<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "expenses_record".
 *
 * @property int $id
 * @property int|null $amount
 * @property string|null $date
 * @property string|null $name
 * @property int|null $money_donor_id
 *
 * @property MoneyDonor $moneyDonor
 */
class ExpensesRecord extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'expenses_record';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['amount', 'money_donor_id'], 'default', 'value' => null],
            [['amount', 'money_donor_id'], 'integer'],
            [['date'], 'safe'],
            [['name'], 'string', 'max' => 255],
            [['money_donor_id'], 'exist', 'skipOnError' => true, 'targetClass' => MoneyDonor::class, 'targetAttribute' => ['money_donor_id' => 'id']],
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
            'money_donor_id' => 'Money Donor ID',
        ];
    }

    /**
     * Gets query for [[MoneyDonor]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getMoneyDonor()
    {
        return $this->hasOne(MoneyDonor::class, ['id' => 'money_donor_id']);
    }
}
