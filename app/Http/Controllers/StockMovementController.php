<?php

namespace App\Http\Controllers;

use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockMovementController extends Controller
{
    /**
     * Получение списка движений товаров с фильтрами и пагинацией.
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // Валидация параметров запроса
        $request->validate([
            'product_id' => 'nullable|exists:products,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        // Формируем запрос с учетом фильтров
        $query = StockMovement::with([
            'product' => fn($q) => $q->select('id', 'name'),
            'warehouse' => fn($q) => $q->select('id', 'name')
        ])
            // Фильтр по товару
            ->when($request->product_id, fn($q) => $q->where('product_id', $request->product_id))
            // Фильтр по складу
            ->when($request->warehouse_id, fn($q) => $q->where('warehouse_id', $request->warehouse_id))
            // Фильтр по дате начала
            ->when($request->start_date, fn($q) => $q->where('created_at', '>=', $request->start_date))
            // Фильтр по дате окончания
            ->when($request->end_date, fn($q) => $q->where('created_at', '<=', $request->end_date));

        // Применяем пагинацию
        $perPage = $request->per_page ?? 15;
        $movements = $query->paginate($perPage);

        // Возвращаем результат в формате JSON
        return response()->json($movements);
    }
}
