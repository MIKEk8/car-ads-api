<?php

declare(strict_types=1);

namespace app\Controller;

use app\Entity\Car;
use app\Exception\ValidationException;
use app\Service\CarService;
use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * REST-контроллер объявлений автомобилей.
 *
 *  POST /car/create  — создать объявление (201);
 *  GET  /car/{id}    — получить объявление (200/404);
 *  GET  /car/list    — список с пагинацией (?page).
 *
 * Контроллер — тонкий: разбирает HTTP-запрос, делегирует {@see CarService}
 * и сериализует доменные сущности в JSON.
 */
final class CarController extends Controller
{
    public $enableCsrfValidation = false;

    public function __construct(
        $id,
        $module,
        private readonly CarService $carService,
        array $config = [],
    ) {
        parent::__construct($id, $module, $config);
    }

    public function actionCreate(): array
    {
        $data = Yii::$app->request->getBodyParams();

        try {
            $car = $this->carService->create($data);
        } catch (ValidationException $e) {
            Yii::$app->response->statusCode = 422;
            return [
                'message' => 'Ошибка валидации.',
                'errors' => $e->getErrors(),
            ];
        }

        Yii::$app->response->statusCode = 201;
        return $this->serializeCar($car);
    }

    /**
     * @throws NotFoundHttpException
     */
    public function actionView(int $id): array
    {
        $car = $this->carService->getById($id);
        if ($car === null) {
            throw new NotFoundHttpException("Объявление с id=$id не найдено.");
        }

        return $this->serializeCar($car);
    }

    public function actionList(int $page = 1): array
    {
        $perPage = (int)Yii::$app->params['car.list.perPage'];
        $result = $this->carService->list($page, $perPage);

        return [
            'data' => array_map([$this, 'serializeCar'], $result['items']),
            'pagination' => [
                'page' => $result['page'],
                'perPage' => $result['perPage'],
                'total' => $result['total'],
                'pages' => $result['pages'],
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function serializeCar(Car $car): array
    {
        $out = [
            'id' => $car->id,
            'title' => $car->title,
            'description' => $car->description,
            'price' => (float)$car->price,
            'photo_url' => $car->photoUrl,
            'contacts' => $car->contacts,
            'created_at' => $car->createdAt,
            'options' => null,
        ];

        if ($car->option !== null) {
            $out['options'] = [
                'brand' => $car->option->brand,
                'model' => $car->option->model,
                'year' => $car->option->year,
                'body' => $car->option->body,
                'mileage' => $car->option->mileage,
            ];
        }

        return $out;
    }
}
