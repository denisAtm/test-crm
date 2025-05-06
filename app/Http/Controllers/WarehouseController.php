<?php

namespace App\Http\Controllers;

use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;

class WarehouseController extends Controller
{
    /**
     * Получение списка складов.
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        // Получаем все склады
        $warehouses = Warehouse::select('id', 'name')->get();
        // Возвращаем результат
        return response()->json($warehouses);
    }
}
