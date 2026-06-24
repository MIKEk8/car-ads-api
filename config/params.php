<?php

declare(strict_types=1);

return [
    // Количество объявлений на страницу в GET /car/list.
    'car.list.perPage' => (int)(getenv('CAR_LIST_PER_PAGE') ?: 20),
];
