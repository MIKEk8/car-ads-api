<?php

declare(strict_types=1);

namespace app\Service;

use app\Entity\Car;
use app\Entity\CarOption;
use app\Exception\ValidationException;
use app\Repository\CarRepositoryInterface;

/**
 * Прикладной сервис объявлений: валидация входных данных, сборка доменных
 * сущностей и оркестрация хранилища. Не знает о HTTP и о способе хранения —
 * зависит только от абстракции {@see CarRepositoryInterface}.
 */
final class CarService
{
    /** Обязательные поля блока характеристик. */
    private const OPTION_FIELDS = ['brand', 'model', 'year', 'body', 'mileage'];

    public function __construct(private readonly CarRepositoryInterface $repository)
    {
    }

    /**
     * Создаёт новое объявление из «сырого» массива запроса.
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
        } elseif (mb_strlen($title) > 255) {
            $errors['title'] = 'Поле title не должно превышать 255 символов.';
        }

        $description = $this->readString($data, 'description') ?? '';

        $price = $data['price'] ?? null;
        if ($price === null || $price === '' || !is_numeric($price)) {
            $errors['price'] = 'Поле price обязательно и должно быть числом.';
        } elseif ((float)$price < 0) {
            $errors['price'] = 'Поле price не может быть отрицательным.';
        }

        $photoUrl = $this->readString($data, 'photo_url');
        if ($photoUrl !== null && mb_strlen($photoUrl) > 255) {
            $errors['photo_url'] = 'Поле photo_url не должно превышать 255 символов.';
        }

        $contacts = $this->readString($data, 'contacts');
        if ($contacts === null || $contacts === '') {
            $errors['contacts'] = 'Поле contacts обязательно и не может быть пустым.';
        }

        $option = $this->buildOption($data, $errors);

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $car = new Car(
            (string)$title,
            $description,
            $this->normalizePrice((string)$price),
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
            }
        }

        foreach (['year', 'mileage'] as $field) {
            if (isset($raw[$field]) && !$this->isInteger($raw[$field])) {
                $errors["options.$field"] = "Поле options.$field должно быть целым числом.";
            }
        }

        // Если уже есть ошибки по блоку — сущность не собираем.
        foreach ($errors as $key => $_) {
            if (str_starts_with($key, 'options')) {
                return null;
            }
        }

        return new CarOption(
            (string)$raw['brand'],
            (string)$raw['model'],
            (int)$raw['year'],
            (string)$raw['body'],
            (int)$raw['mileage'],
        );
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

    private function normalizePrice(string $price): string
    {
        // Храним decimal как строку с двумя знаками — без потерь точности на float.
        return number_format((float)$price, 2, '.', '');
    }
}
