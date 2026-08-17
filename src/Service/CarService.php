<?php

declare(strict_types=1);

namespace app\Service;

use app\Entity\Car;
use app\Entity\CarOption;
use app\Exception\ValidationException;
use app\Repository\CarRepositoryInterface;
use app\Schema\CarSchema;

/**
 * Прикладной сервис объявлений: валидация входных данных, сборка доменных
 * сущностей и оркестрация хранилища. Не знает о HTTP и о способе хранения —
 * зависит только от абстракции {@see CarRepositoryInterface}.
 *
 * Все границы полей берутся из {@see CarSchema} — того же описания, по которому
 * миграции создают колонки. Это принципиально: пока ограничение схемы не
 * проверено здесь, некорректный ввод доходит до PostgreSQL и возвращается
 * клиенту пятисоткой вместо 422.
 */
final class CarService
{
    /** Обязательные поля блока характеристик. */
    private const OPTION_FIELDS = ['brand', 'model', 'year', 'body', 'mileage'];

    /** Текстовые поля характеристик — попадают в varchar. */
    private const OPTION_TEXT_FIELDS = ['brand', 'model', 'body'];

    public function __construct(private readonly CarRepositoryInterface $repository)
    {
    }

    /**
     * Создаёт новое объявление из «сырого» массива запроса.
     *
     * Валидация не прерывается на первой ошибке: собирается полная карта
     * `поле → сообщение`, чтобы клиент увидел все проблемы запроса сразу.
     *
     * @param array<string,mixed> $data
     * @throws ValidationException если данные некорректны.
     */
    public function create(array $data): Car
    {
        $errors = [];

        $title = $this->readString($data, 'title');
        if ($title === null || $title === '') {
            $errors['title'] = 'Поле title обязательно и не может быть пустым.';
        } elseif (mb_strlen($title) > CarSchema::TITLE_MAX) {
            $errors['title'] = 'Поле title не должно превышать ' . CarSchema::TITLE_MAX . ' символов.';
        }

        $description = $this->readString($data, 'description') ?? '';

        $price = $this->readPrice($data, $errors);

        $photoUrl = $this->readString($data, 'photo_url');
        if ($photoUrl !== null && mb_strlen($photoUrl) > CarSchema::PHOTO_URL_MAX) {
            $errors['photo_url'] = 'Поле photo_url не должно превышать ' . CarSchema::PHOTO_URL_MAX . ' символов.';
        }

        $contacts = $this->readString($data, 'contacts');
        if ($contacts === null || $contacts === '') {
            $errors['contacts'] = 'Поле contacts обязательно и не может быть пустым.';
        } elseif (mb_strlen($contacts) > CarSchema::CONTACTS_MAX) {
            $errors['contacts'] = 'Поле contacts не должно превышать ' . CarSchema::CONTACTS_MAX . ' символов.';
        }

        $option = $this->buildOption($data, $errors);

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $car = new Car(
            (string)$title,
            $description,
            (string)$price,
            $photoUrl,
            (string)$contacts,
            $option,
        );

        return $this->repository->save($car);
    }

    public function getById(int $id): ?Car
    {
        return $this->repository->getById($id);
    }

    /**
     * @return array{items: Car[], total: int, page: int, perPage: int, pages: int}
     */
    public function list(int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        $result = $this->repository->paginate($page, $perPage);
        $total = $result['total'];

        return [
            'items' => $result['items'],
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'pages' => (int)ceil($total / $perPage),
        ];
    }

    /**
     * Разбор и нормализация цены под numeric(PRICE_PRECISION, PRICE_SCALE).
     *
     * Работаем со строкой, а не с float: на границе диапазона float уже теряет
     * значащие знаки, и проверка «влезает ли в колонку» стала бы недостоверной.
     * Лишние знаки после запятой не округляются молча, а отклоняются — иначе
     * клиент не узнал бы, что сумма изменилась.
     *
     * @param array<string,mixed> $data
     * @param array<string,string> $errors
     * @return string|null Цена в виде «12345.67» либо null, если есть ошибка.
     */
    private function readPrice(array $data, array &$errors): ?string
    {
        $raw = $data['price'] ?? null;

        if ($raw === null || $raw === '' || is_bool($raw) || !is_scalar($raw)
            || !is_numeric(trim((string)$raw))
        ) {
            $errors['price'] = 'Поле price обязательно и должно быть числом.';
            return null;
        }

        $value = trim((string)$raw);

        if ((float)$value < 0) {
            $errors['price'] = 'Поле price не может быть отрицательным.';
            return null;
        }

        // Экспоненциальная запись и прочие числовые формы отклоняются: привести
        // их к десятичному виду можно только через float, то есть с потерей знаков.
        if (preg_match('/^(\d+)(?:\.(\d*))?$/', $value, $m) !== 1) {
            $errors['price'] = 'Поле price должно быть записано десятичным числом, например 4500000.99.';
            return null;
        }

        [, $whole, $fraction] = $m + [2 => ''];

        if (strlen($fraction) > CarSchema::PRICE_SCALE) {
            $errors['price'] = 'Поле price не должно иметь больше '
                . CarSchema::PRICE_SCALE . ' знаков после запятой.';
            return null;
        }

        $whole = ltrim($whole, '0');
        if (strlen($whole) > CarSchema::priceWholeDigits()) {
            $errors['price'] = 'Поле price не должно превышать ' . CarSchema::priceMax() . '.';
            return null;
        }

        return ($whole === '' ? '0' : $whole)
            . '.' . str_pad($fraction, CarSchema::PRICE_SCALE, '0');
    }

    /**
     * Разбор необязательного блока options.
     *
     * Возможные случаи по ТЗ:
     *  - ключ отсутствует    → характеристики не добавляются (null);
     *  - options === null     → характеристики не добавляются (null);
     *  - options === { ... }  → все поля обязательны.
     *
     * @param array<string,mixed> $data
     * @param array<string,string> $errors
     */
    private function buildOption(array $data, array &$errors): ?CarOption
    {
        if (!array_key_exists('options', $data) || $data['options'] === null) {
            return null;
        }

        $raw = $data['options'];
        if (!is_array($raw)) {
            $errors['options'] = 'Поле options должно быть объектом или null.';
            return null;
        }

        foreach (self::OPTION_FIELDS as $field) {
            if (!array_key_exists($field, $raw) || $raw[$field] === null || $raw[$field] === '') {
                $errors["options.$field"] = "Поле options.$field обязательно.";
                continue;
            }

            // Без этой проверки вложенный массив или объект дошёл бы до
            // приведения (string) и превратился в строку «Array».
            if (!is_scalar($raw[$field])) {
                $errors["options.$field"] = "Поле options.$field должно быть простым значением.";
            }
        }

        foreach (self::OPTION_TEXT_FIELDS as $field) {
            if (isset($errors["options.$field"]) || !isset($raw[$field]) || !is_scalar($raw[$field])) {
                continue;
            }

            if (mb_strlen(trim((string)$raw[$field])) > CarSchema::OPTION_TEXT_MAX) {
                $errors["options.$field"] = "Поле options.$field не должно превышать "
                    . CarSchema::OPTION_TEXT_MAX . ' символов.';
            }
        }

        $this->validateInteger($raw, 'year', CarSchema::YEAR_MIN, CarSchema::yearMax(), $errors);
        $this->validateInteger($raw, 'mileage', CarSchema::MILEAGE_MIN, CarSchema::INT32_MAX, $errors);

        // Если по блоку есть хоть одна ошибка — сущность не собираем.
        foreach ($errors as $key => $_) {
            if (str_starts_with($key, 'options')) {
                return null;
            }
        }

        return new CarOption(
            trim((string)$raw['brand']),
            trim((string)$raw['model']),
            (int)$raw['year'],
            trim((string)$raw['body']),
            (int)$raw['mileage'],
        );
    }

    /**
     * Проверяет, что поле — целое число в заданных границах.
     *
     * Границы двойные: колонка в БД четырёхбайтная, поэтому значение обязано
     * помещаться в integer, и одновременно должно быть осмысленным — год не
     * раньше первого автомобиля, пробег не отрицательный.
     *
     * @param array<string,mixed> $raw
     * @param array<string,string> $errors
     */
    private function validateInteger(array $raw, string $field, int $min, int $max, array &$errors): void
    {
        if (isset($errors["options.$field"]) || !isset($raw[$field])) {
            return;
        }

        if (!$this->isInteger($raw[$field])) {
            $errors["options.$field"] = "Поле options.$field должно быть целым числом.";
            return;
        }

        // Сравниваем со строковым представлением: значение может не помещаться
        // даже в int64, и тогда приведение (int) молча его исказит.
        $asString = (string)$raw[$field];
        $value = (int)$asString;

        if ((string)$value !== $asString || $value < $min || $value > $max) {
            $errors["options.$field"] = "Поле options.$field должно быть в диапазоне от $min до $max.";
        }
    }

    /**
     * @param array<string,mixed> $data
     */
    private function readString(array $data, string $key): ?string
    {
        if (!array_key_exists($key, $data) || $data[$key] === null) {
            return null;
        }

        return is_scalar($data[$key]) ? trim((string)$data[$key]) : null;
    }

    private function isInteger(mixed $value): bool
    {
        return is_int($value) || (is_string($value) && preg_match('/^-?\d+$/', $value) === 1);
    }
}
