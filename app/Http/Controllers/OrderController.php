<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Stock;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * Получение списка заказов с фильтрами и пагинацией.
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // Валидация параметров запроса
        $request->validate([
            'status' => 'nullable|in:active,completed,canceled',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        // Формируем запрос с учетом фильтров
        $query = Order::with([
            'warehouse' => fn($q) => $q->select('id', 'name'),
            'orderItems.product' => fn($q) => $q->select('id', 'name')
        ])
            // Фильтр по статусу
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            // Фильтр по складу
            ->when($request->warehouse_id, fn($q) => $q->where('warehouse_id', $request->warehouse_id));

        // Применяем пагинацию
        $perPage = $request->per_page ?? 15;
        $orders = $query->paginate($perPage);

        // Возвращаем результат
        return response()->json($orders);
    }

    /**
     * Создание нового заказа.
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        // Валидация входных данных
        $request->validate([
            'customer' => 'required|string|max:255',
            'warehouse_id' => 'required|exists:warehouses,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.count' => 'required|integer|min:1|max:10000',
        ]);

        // Проверяем наличие достаточного остатка
        foreach ($request->items as $item) {
            $stock = Stock::where('product_id', $item['product_id'])
                ->where('warehouse_id', $request->warehouse_id)
                ->first();
            if (!$stock || $stock->stock < $item['count']) {
                return response()->json(['error' => 'Недостаточно товара с ID ' . $item['product_id'] . ' на складе'], 400);
            }
        }

        // Выполняем создание заказа в транзакции
        return DB::transaction(function () use ($request) {
            // Создаем заказ
            $order = Order::create([
                'customer' => $request->customer,
                'warehouse_id' => $request->warehouse_id,
                'status' => 'active',
            ]);

            // Создаем позиции заказа и обновляем остатки
            foreach ($request->items as $item) {
                // Создаем позицию заказа
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'count' => $item['count'],
                ]);

                // Уменьшаем остаток
                Stock::where('product_id', $item['product_id'])
                    ->where('warehouse_id', $request->warehouse_id)
                    ->decrement('stock', $item['count']);

                // Записываем движение товара
                StockMovement::create([
                    'product_id' => $item['product_id'],
                    'warehouse_id' => $request->warehouse_id,
                    'quantity' => -$item['count'],
                    'reason' => 'Создание заказа #' . $order->id,
                ]);
            }

            // Возвращаем созданный заказ с данными
            return response()->json($order->load([
                'warehouse' => fn($q) => $q->select('id', 'name'),
                'orderItems.product' => fn($q) => $q->select('id', 'name')
            ]), 201);
        });
    }

    /**
     * Завершение заказа.
     * @param int $id
     * @return JsonResponse
     */
    public function complete(int $id): JsonResponse
    {
        // Находим заказ
        $order = Order::findOrFail($id);

        // Проверяем, что заказ активен
        if ($order->status !== 'active') {
            return response()->json(['error' => 'Только активные заказы могут быть завершены'], 400);
        }

        // Обновляем статус и время завершения
        $order->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        // Возвращаем обновленный заказ
        return response()->json($order->load([
            'warehouse' => fn($q) => $q->select('id', 'name'),
            'orderItems.product' => fn($q) => $q->select('id', 'name')
        ]));
    }

    /**
     * Обновление существующего заказа.
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        // Валидация входных данных
        $request->validate([
            'customer' => 'required|string|max:255',
            'warehouse_id' => 'required|exists:warehouses,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.count' => 'required|integer|min:1|max:10000',
        ]);

        // Находим заказ
        $order = Order::findOrFail($id);

        // Проверяем, что заказ активен
        if ($order->status !== 'active') {
            return response()->json(['error' => 'Нельзя обновить неактивный заказ'], 400);
        }

        // Проверяем наличие достаточного остатка
        foreach ($request->items as $item) {
            $stock = Stock::where('product_id', $item['product_id'])
                ->where('warehouse_id', $request->warehouse_id)
                ->first();
            if (!$stock || $stock->stock < $item['count']) {
                return response()->json(['error' => 'Недостаточно товара с ID ' . $item['product_id'] . ' на складе'], 400);
            }
        }

        // Выполняем обновление в транзакции
        return DB::transaction(function () use ($request, $order) {
            // Восстанавливаем остатки для старых позиций
            foreach ($order->orderItems as $item) {
                Stock::where('product_id', $item->product_id)
                    ->where('warehouse_id', $order->warehouse_id)
                    ->increment('stock', $item->count);

                // Записываем движение товара
                StockMovement::create([
                    'product_id' => $item->product_id,
                    'warehouse_id' => $order->warehouse_id,
                    'quantity' => $item->count,
                    'reason' => 'Восстановление остатка при обновлении заказа #' . $order->id,
                ]);
            }

            // Удаляем старые позиции
            $order->orderItems()->delete();

            // Обновляем данные заказа
            $order->update([
                'customer' => $request->customer,
                'warehouse_id' => $request->warehouse_id,
            ]);

            // Создаем новые позиции и обновляем остатки
            foreach ($request->items as $item) {
                // Создаем позицию заказа
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'count' => $item['count'],
                ]);

                // Уменьшаем остаток
                Stock::where('product_id', $item['product_id'])
                    ->where('warehouse_id', $request->warehouse_id)
                    ->decrement('stock', $item['count']);

                // Записываем движение товара
                StockMovement::create([
                    'product_id' => $item['product_id'],
                    'warehouse_id' => $request->warehouse_id,
                    'quantity' => -$item['count'],
                    'reason' => 'Обновление заказа #' . $order->id,
                ]);
            }

            // Возвращаем обновленный заказ
            return response()->json($order->load([
                'warehouse' => fn($q) => $q->select('id', 'name'),
                'orderItems.product' => fn($q) => $q->select('id', 'name')
            ]));
        });
    }

    /**
     * Отмена заказа.
     * @param int $id
     * @return JsonResponse
     */
    public function cancel(int $id): JsonResponse
    {
        // Находим заказ
        $order = Order::findOrFail($id);

        // Проверяем, что заказ активен
        if ($order->status !== 'active') {
            return response()->json(['error' => 'Только активные заказы могут быть отменены'], 400);
        }

        // Выполняем отмену в транзакции
        return DB::transaction(function () use ($order) {
            // Восстанавливаем остатки
            foreach ($order->orderItems as $item) {
                Stock::where('product_id', $item->product_id)
                    ->where('warehouse_id', $order->warehouse_id)
                    ->increment('stock', $item->count);

                // Записываем движение товара
                StockMovement::create([
                    'product_id' => $item->product_id,
                    'warehouse_id' => $order->warehouse_id,
                    'quantity' => $item->count,
                    'reason' => 'Отмена заказа #' . $order->id,
                ]);
            }

            // Обновляем статус заказа
            $order->update(['status' => 'canceled']);

            // Возвращаем обновленный заказ
            return response()->json($order->load([
                'warehouse' => fn($q) => $q->select('id', 'name'),
                'orderItems.product' => fn($q) => $q->select('id', 'name')
            ]));
        });
    }

    /**
     * Возобновление отмененного заказа.
     * @param int $id
     * @return JsonResponse
     */
    public function resume(int $id): JsonResponse
    {
        // Находим заказ
        $order = Order::findOrFail($id);

        // Проверяем, что заказ отменен
        if ($order->status !== 'canceled') {
            return response()->json(['error' => 'Только отмененные заказы могут быть возобновлены'], 400);
        }

        // Проверяем наличие достаточного остатка
        foreach ($order->orderItems as $item) {
            $stock = Stock::where('product_id', $item->product_id)
                ->where('warehouse_id', $order->warehouse_id)
                ->first();
            if (!$stock || $stock->stock < $item->count) {
                return response()->json(['error' => 'Недостаточно товара с ID ' . $item->product_id . ' на складе'], 400);
            }
        }

        // Выполняем возобновление в транзакции
        return DB::transaction(function () use ($order) {
            // Уменьшаем остатки
            foreach ($order->orderItems as $item) {
                Stock::where('product_id', $item->product_id)
                    ->where('warehouse_id', $order->warehouse_id)
                    ->decrement('stock', $item->count);

                // Записываем движение товара
                StockMovement::create([
                    'product_id' => $item->product_id,
                    'warehouse_id' => $order->warehouse_id,
                    'quantity' => -$item->count,
                    'reason' => 'Возобновление заказа #' . $order->id,
                ]);
            }

            // Обновляем статус заказа
            $order->update(['status' => 'active']);

            // Возвращаем обновленный заказ
            return response()->json($order->load([
                'warehouse' => fn($q) => $q->select('id', 'name'),
                'orderItems.product' => fn($q) => $q->select('id', 'name')
            ]));
        });
    }
}
