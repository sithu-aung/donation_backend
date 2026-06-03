<?php

use yii\db\Migration;

/**
 * Adds a birth_date column plus structured location columns
 * (township, ward, village) to the patient table.
 *
 * birth_date is the authoritative value for computing a patient's age over time.
 * The existing flat `address` column is kept and continues to be populated for
 * backward compatibility and search.
 */
class m260603_000001_add_birthdate_location_to_patient extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('patient', 'birth_date', $this->date()->after('age'));
        $this->addColumn('patient', 'township', $this->string(255)->after('address'));
        $this->addColumn('patient', 'ward', $this->string(255)->after('township'));
        $this->addColumn('patient', 'village', $this->string(255)->after('ward'));
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropColumn('patient', 'village');
        $this->dropColumn('patient', 'ward');
        $this->dropColumn('patient', 'township');
        $this->dropColumn('patient', 'birth_date');
    }
}
