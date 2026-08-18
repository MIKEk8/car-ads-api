<?php

declare(strict_types=1);

use app\Schema\CarSchema;
use yii\db\Migration;

/**
 * Таблица `car` — объявления об автомобилях.
 *
 * Границы колонок берутся из {@see CarSchema} — из того же описания, по
 * которому формы слоя ввода проверяют запросы. Так проверка и схема не могут
 * разойтись: раньше расхождение превращало некорректный ввод в HTTP 500.
 */
class m260624_000001_create_car_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('car', [
            'id' => $this->primaryKey(),
            'title' => $this->string(CarSchema::TITLE_MAX)->notNull(),
            'description' => $this->text()->notNull()->defaultValue(''),
            'price' => $this->decimal(CarSchema::PRICE_PRECISION, CarSchema::PRICE_SCALE)->notNull(),
            'photo_url' => $this->string(CarSchema::PHOTO_URL_MAX)->null(),
            'contacts' => $this->string(CarSchema::CONTACTS_MAX)->notNull(),
            'created_at' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
        ]);
    }

    public function safeDown(): void
    {
        $this->dropTable('car');
    }
}
