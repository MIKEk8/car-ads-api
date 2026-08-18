<?php

declare(strict_types=1);

namespace app\Form;

use app\Entity\CarOption;
use app\Schema\CarSchema;
use yii\base\Model;

/**
 * Технические характеристики объявления на входе.
 *
 * Отдельная модель, потому что Yii не валидирует вложенные структуры: правило
 * умеет проверять атрибут, но не объект внутри атрибута. Поэтому блок options
 * разбирается своей моделью, а её ошибки переносятся в родительскую с
 * префиксом — см. {@see CarCreateForm::validateOptions()}.
 *
 * По ТЗ блок необязателен, но если он передан, обязательны все поля.
 */
final class CarOptionForm extends Model
{
    public mixed $brand = null;
    public mixed $model = null;
    public mixed $year = null;
    public mixed $body = null;
    public mixed $mileage = null;

    /** Метки — имена полей внутри блока options, см. CarCreateForm. */
    public function attributeLabels(): array
    {
        return [
            'brand' => 'brand',
            'model' => 'model',
            'year' => 'year',
            'body' => 'body',
            'mileage' => 'mileage',
        ];
    }

    public function rules(): array
    {
        return [
            [['brand', 'model', 'year', 'body', 'mileage'], 'required',
                'message' => 'Поле options.{attribute} обязательно.'],

            // Вложенный массив или объект отсекаем до приведения к строке,
            // иначе он молча превратился бы в «Array».
            [['brand', 'model', 'body'], 'validateScalar'],
            [['brand', 'model', 'body'], 'filter',
                'filter' => static fn (mixed $v): mixed => is_string($v) ? trim($v) : $v],
            [['brand', 'model', 'body'], 'string', 'max' => CarSchema::OPTION_TEXT_MAX,
                'tooLong' => 'Поле options.{attribute} не должно превышать '
                    . CarSchema::OPTION_TEXT_MAX . ' символов.'],

            ['year', 'validateInteger', 'params' => [
                'min' => CarSchema::YEAR_MIN,
                'max' => null, // считается на лету: текущий год + 1
            ]],
            ['mileage', 'validateInteger', 'params' => [
                'min' => CarSchema::MILEAGE_MIN,
                'max' => CarSchema::INT32_MAX,
            ]],
        ];
    }

    public function validateScalar(string $attribute): void
    {
        $value = $this->$attribute;

        if ($value !== null && !is_scalar($value)) {
            $this->addError($attribute, "Поле options.$attribute должно быть простым значением.");
        }
    }

    /**
     * Целое число в границах колонки и в осмысленных пределах.
     *
     * Сравниваем со строковым представлением: значение может не помещаться
     * даже в int64, и тогда приведение (int) молча его исказит.
     *
     * @param array{min: int, max: int|null} $params
     */
    public function validateInteger(string $attribute, array $params): void
    {
        $value = $this->$attribute;

        if ($value === null || $value === '' || $this->hasErrors($attribute)) {
            return;
        }

        $isInteger = is_int($value)
            || (is_string($value) && preg_match('/^-?\d+$/', $value) === 1);

        if (!$isInteger) {
            $this->addError($attribute, "Поле options.$attribute должно быть целым числом.");
            return;
        }

        $min = $params['min'];
        $max = $params['max'] ?? CarSchema::yearMax();

        $asString = (string)$value;
        $number = (int)$asString;

        if ((string)$number !== $asString || $number < $min || $number > $max) {
            $this->addError(
                $attribute,
                "Поле options.$attribute должно быть в диапазоне от $min до $max.",
            );
        }
    }

    public function toEntity(): CarOption
    {
        return new CarOption(
            (string)$this->brand,
            (string)$this->model,
            (int)$this->year,
            (string)$this->body,
            (int)$this->mileage,
        );
    }
}
