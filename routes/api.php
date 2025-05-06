<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\StockMovementController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Получение списка складов
Route::get('/warehouses', [WarehouseController::class, 'index']);

// Получение списка товаров с остатками
Route::get('/products', [ProductController::class, 'index']);

// Получение списка заказов
Route::get('/orders', [OrderController::class, 'index']);

// Создание нового заказа
Route::post('/orders', [OrderController::class, 'store']);

// Обновление заказа
Route::put('/orders/{id}', [OrderController::class, 'update']);

// Завершение заказа
Route::post('/orders/{id}/complete', [OrderController::class, 'complete']);

// Отмена заказа
Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel']);

// Возобновление заказа
Route::post('/orders/{id}/resume', [OrderController::class, 'resume']);

// Получение истории движений товаров
Route::get('/stock-movements', [StockMovementController::class, 'index']);
