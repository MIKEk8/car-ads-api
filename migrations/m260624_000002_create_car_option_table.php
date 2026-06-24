<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Таблица `car_option` — технические характеристики объявления.
 * Связь has-one: car_option.car_id → car.id (уникальный car_id).
 */
class m260624_000002_create_car_option_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('car_option', [
            'id' => $this->primaryKey(),
            'car_id' => $this->integer()->notNull(),
            'brand' => $this->string(255)->notNull(),
            'model' => $this->string(255)->notNull(),
            'year' => $this->integer()->notNull(),
            'body' => $this->string(255)->notNull(),
            'mileage' => $this->integer()->notNull(),
        ]);

        // has-one: на одно объявление не более одной записи характеристик.
        $this->createIndex('uq-car_option-car_id', 'car_option', 'car_id', true);

        $this->addForeignKey(
            'fk-car_option-car_id',
            'car_option',
            'car_id',
            'car',
            'id',
            'CASCADE',
            'CASCADE',
        );
    }

    public function safeDown(): void
    {
        $this->dropTable('car_option');
    }
}
