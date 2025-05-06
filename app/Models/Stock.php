<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Stock extends Model
{
    // Поля, доступные для массового заполнения
    protected $fillable = ['product_id', 'warehouse_id', 'stock'];
    // Указываем составной первичный ключ
    protected $primaryKey = ['product_id', 'warehouse_id'];
    // Отключаем автоинкремент
    public $incrementing = false;

    /**
     * Получение товара для остатка.
     * @return BelongsTo
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Получение склада для остатка.
     * @return BelongsTo
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
