<?php

declare(strict_types=1);

namespace app\Form;

use app\Entity\Car;
use app\Schema\CarSchema;
use yii\base\Model;

/**
 * Объявление на входе: приём и проверка данных запроса.
 *
 * Это граница, за которой данным не доверяют, и единственная из трёх границ
 * приложения, где валидация уместна. Дальше внутрь идут уже проверенные
 * сущности: маппер, пишущий их в таблицу, ничего не перепроверяет, потому что
 * получает данные от самого приложения, а не снаружи.
 *
 * Границы полей берутся из {@see CarSchema} — того же описания, по которому
 * миграции создают колонки. Полноту покрытия сторожит
 * {@see \app\tests\unit\CarSchemaCoverageTest}.
 */
final class CarCreateForm extends Model
{
    public mixed $title = null;
    public mixed $description = null;
    public mixed $price = null;
    public mixed $photo_url = null;
    public mixed $contacts = null;

    /** Блок характеристик: отсутствует, null либо объект. */
    public mixed $options = null;

    /** Цена, приведённая к виду «12345.67». Заполняется при валидации. */
    private ?string $normalizedPrice = null;

    private ?CarOptionForm $optionForm = null;

    /**
     * Метки совпадают с именами полей запроса.
     *
     * Без этого Yii подставляет в {attribute} автосгенерированную метку и
     * сообщения превращаются в «Поле Title обязательно». Сообщения говорят
     * о полях JSON, значит и называть их должны так же.
     */
    public function attributeLabels(): array
    {
        return [
            'title' => 'title',
            'description' => 'description',
            'price' => 'price',
            'photo_url' => 'photo_url',
            'contacts' => 'contacts',
            'options' => 'options',
        ];
    }

    public function rules(): array
    {
        $trim = static fn (mixed $v): mixed => is_string($v) ? trim($v) : $v;

        return [
            [['title', 'description', 'photo_url', 'contacts'], 'filter', 'filter' => $trim],

            [['title', 'contacts'], 'required',
                'message' => 'Поле {attribute} обязательно и не может быть пустым.'],
            ['price', 'required', 'message' => 'Поле price обязательно и должно быть числом.'],

            ['title', 'string', 'max' => CarSchema::TITLE_MAX,
                'tooLong' => 'Поле title не должно превышать ' . CarSchema::TITLE_MAX . ' символов.'],
            ['contacts', 'string', 'max' => CarSchema::CONTACTS_MAX,
                'tooLong' => 'Поле contacts не должно превышать ' . CarSchema::CONTACTS_MAX . ' символов.'],
            ['photo_url', 'string', 'max' => CarSchema::PHOTO_URL_MAX,
                'tooLong' => 'Поле photo_url не должно превышать ' . CarSchema::PHOTO_URL_MAX . ' символов.'],

            // description по ТЗ не обязателен; правило нужно ещё и затем,
            // чтобы атрибут считался безопасным и вообще загружался из запроса.
            ['description', 'string'],
            ['description', 'default', 'value' => ''],

            ['price', 'validatePrice'],
            // skipOnEmpty обязателен: у inline-валидаторов Yii он по умолчанию
            // true, а пустой массив считается пустым значением — и правило для
            // `"options": {}` не вызывалось бы вовсе, пропуская объявление без
            // характеристик вместо пяти ошибок «поле обязательно».
            ['options', 'validateOptions', 'skipOnEmpty' => false],
        ];
    }

    /**
     * Разбор и нормализация цены под numeric(PRICE_PRECISION, PRICE_SCALE).
     *
     * Работаем со строкой, а не с float: на границе диапазона float уже теряет
     * значащие знаки, и проверка «влезает ли в колонку» стала бы недостоверной.
     * Лишние знаки после запятой не округляются молча, а отклоняются — иначе
     * клиент не узнал бы, что сумма изменилась.
     */
    public function validatePrice(): void
    {
        $raw = $this->price;

        if ($raw === null || $raw === '' || $this->hasErrors('price')) {
            return;
        }

        if (is_bool($raw) || !is_scalar($raw) || !is_numeric(trim((string)$raw))) {
            $this->addError('price', 'Поле price обязательно и должно быть числом.');
            return;
        }

        $value = trim((string)$raw);

        if ((float)$value < 0) {
            $this->addError('price', 'Поле price не может быть отрицательным.');
            return;
        }

        // Экспоненциальная запись и прочие числовые формы отклоняются: привести
        // их к десятичному виду можно только через float, то есть с потерей знаков.
        if (preg_match('/^(\d+)(?:\.(\d*))?$/', $value, $matches) !== 1) {
            $this->addError('price', 'Поле price должно быть записано десятичным числом, например 4500000.99.');
            return;
        }

        [, $whole, $fraction] = $matches + [2 => ''];

        if (strlen($fraction) > CarSchema::PRICE_SCALE) {
            $this->addError('price', 'Поле price не должно иметь больше '
                . CarSchema::PRICE_SCALE . ' знаков после запятой.');
            return;
        }

        $whole = ltrim($whole, '0');

        if (strlen($whole) > CarSchema::priceWholeDigits()) {
            $this->addError('price', 'Поле price не должно превышать ' . CarSchema::priceMax() . '.');
            return;
        }

        $this->normalizedPrice = ($whole === '' ? '0' : $whole)
            . '.' . str_pad($fraction, CarSchema::PRICE_SCALE, '0');
    }

    /**
     * Разбор необязательного блока options.
     *
     * Три случая по ТЗ: ключа нет, значение null, значение объект. Первые два
     * дают отсутствие характеристик, третий требует всех полей.
     *
     * Ошибки вложенной модели переносятся сюда с префиксом `options.` — это
     * контракт, на который завязан фронтенд, и менять его нельзя.
     */
    public function validateOptions(): void
    {
        if ($this->options === null) {
            return;
        }

        if (!is_array($this->options)) {
            $this->addError('options', 'Поле options должно быть объектом или null.');
            return;
        }

        $form = new CarOptionForm();
        $form->load($this->options, '');

        if ($form->validate()) {
            $this->optionForm = $form;
            return;
        }

        foreach ($form->getFirstErrors() as $attribute => $message) {
            $this->addError("options.$attribute", $message);
        }
    }

    /**
     * Сборка доменной сущности. Вызывать только после успешной валидации.
     */
    public function toEntity(): Car
    {
        return new Car(
            (string)$this->title,
            (string)($this->description ?? ''),
            (string)$this->normalizedPrice,
            // Отсутствующий ключ даёт null, пустая строка остаётся строкой —
            // ровно как было до выделения формы.
            $this->photo_url === null ? null : (string)$this->photo_url,
            (string)$this->contacts,
            $this->optionForm?->toEntity(),
        );
    }
}
