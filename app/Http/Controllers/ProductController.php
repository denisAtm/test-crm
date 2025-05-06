<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    /**
     * Получение списка товаров с их остатками на складах.
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        // Загружаем товары с их остатками по складам
        $products = Product::with([
            'stocks.warehouse' => fn($q) => $q->select('id', 'name')
        ])->select('id', 'name', 'price')->get();
        // Возвращаем результат
        return response()->json($products);
    }
}
