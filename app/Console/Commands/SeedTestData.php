<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Stock;
use App\Models\Warehouse;
use App\Models\StockMovement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedTestData extends Command
{
    protected $signature = 'seed:test-data';
    protected $description = 'Наполнение таблиц тестовыми данными для продуктов, складов, остатков и движений';

    public function handle()
    {
        // Отключаем проверку внешних ключей для очистки таблиц
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        // Очищаем существующие данные
        Product::truncate();
        Warehouse::truncate();
        Stock::truncate();
        StockMovement::truncate();
        // Включаем проверку внешних ключей
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Создаем тестовые склады
        $warehouses = [
            ['name' => 'Главный склад'],
            ['name' => 'Вторичный склад'],
            ['name' => 'Региональный склад'],
        ];
        foreach ($warehouses as $warehouse) {
            Warehouse::create($warehouse);
        }

        // Создаем тестовые продукты
        $products = [
            ['name' => 'Ноутбук', 'price' => 999.99],
            ['name' => 'Смартфон', 'price' => 499.99],
            ['name' => 'Наушники', 'price' => 79.99],
            ['name' => 'Планшет', 'price' => 299.99],
            ['name' => 'Клавиатура', 'price' => 49.99],
        ];
        foreach ($products as $product) {
            Product::create($product);
        }

        // Создаем тестовые остатки
        $stocks = [
            ['product_id' => 1, 'warehouse_id' => 1, 'stock' => 50],
            ['product_id' => 1, 'warehouse_id' => 2, 'stock' => 20],
            ['product_id' => 1, 'warehouse_id' => 3, 'stock' => 10],
            ['product_id' => 2, 'warehouse_id' => 1, 'stock' => 100],
            ['product_id' => 2, 'warehouse_id' => 2, 'stock' => 30],
            ['product_id' => 2, 'warehouse_id' => 3, 'stock' => 15],
            ['product_id' => 3, 'warehouse_id' => 1, 'stock' => 200],
            ['product_id' => 3, 'warehouse_id' => 2, 'stock' => 50],
            ['product_id' => 3, 'warehouse_id' => 3, 'stock' => 25],
            ['product_id' => 4, 'warehouse_id' => 1, 'stock' => 80],
            ['product_id' => 4, 'warehouse_id' => 2, 'stock' => 40],
            ['product_id' => 5, 'warehouse_id' => 1, 'stock' => 150],
            ['product_id' => 5, 'warehouse_id' => 3, 'stock' => 60],
        ];
        foreach ($stocks as $stock) {
            Stock::create($stock);
        }

        // Создаем тестовые движения товаров
        $movements = [
            ['product_id' => 1, 'warehouse_id' => 1, 'quantity' => -10, 'reason' => 'Тестовое списание для заказа'],
            ['product_id' => 2, 'warehouse_id' => 2, 'quantity' => 20, 'reason' => 'Тестовое поступление на склад'],
            ['product_id' => 3, 'warehouse_id' => 1, 'quantity' => -50, 'reason' => 'Тестовое списание'],
            ['product_id' => 4, 'warehouse_id' => 2, 'quantity' => 30, 'reason' => 'Тестовое поступление'],
        ];
        foreach ($movements as $movement) {
            StockMovement::create($movement);
        }

        // Сообщаем об успешном выполнении
        $this->info('Тестовые данные успешно добавлены.');
    }
}
