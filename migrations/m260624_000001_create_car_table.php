<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Таблица `car` — объявления об автомобилях.
 */
class m260624_000001_create_car_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('car', [
            'id' => $this->primaryKey(),
            'title' => $this->string(255)->notNull(),
            'description' => $this->text()->notNull()->defaultValue(''),
            'price' => $this->decimal(12, 2)->notNull(),
            'photo_url' => $this->string(255)->null(),
            'contacts' => $this->string(255)->notNull(),
            'created_at' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
        ]);
    }

    public function safeDown(): void
    {
        $this->dropTable('car');
    }
}
