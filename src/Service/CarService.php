<?php

declare(strict_types=1);

namespace app\Service;

use app\Entity\Car;
use app\Exception\ValidationException;
use app\Form\CarCreateForm;
use app\Repository\CarRepositoryInterface;

/**
 * Прикладной сервис объявлений: оркестрация хранилища.
 *
 * Проверку входных данных ведёт {@see CarCreateForm} — граница, за которой
 * данным не доверяют. Сервис знает только, что данные могут не пройти, и в
 * этом случае отдаёт карту ошибок наверх; сам правил не содержит и потому не
 * растёт вместе с ними.
 */
final class CarService
{
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
        $form = new CarCreateForm();
        // Пустое имя формы: тело запроса приходит плоским объектом,
        // без обёртки вида CarCreateForm[title].
        $form->load($data, '');

        if (!$form->validate()) {
            // Одно сообщение на поле — ровно та форма, которую ждёт клиент.
            throw new ValidationException($form->getFirstErrors());
        }

        return $this->repository->save($form->toEntity());
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
}
