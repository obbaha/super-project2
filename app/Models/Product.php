<?php // app/Models/Product.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'sku',
        'name',
        'description',
        'price',
        'old_price',
        'is_available',
    ];

    protected $casts = [
        'is_available' => 'boolean',
        'price' => 'decimal:2',
        'old_price' => 'decimal:2',
    ];

    protected $attributes = [
    'is_available' => true,
    ];

public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variations(): HasMany
    {
        return $this->hasMany(ProductVariation::class);
    }




public function getFeaturedImageAttribute()
{
    // نبحث عن أول تنوع يمتلك صورة
    $variationWithImage = $this->variations->whereNotNull('featured_image_id')->first();

    return $variationWithImage ? $variationWithImage->featuredImage : null;
}

/**
 * جلب السعر الأدنى للمنتج
 */
public function getMinPriceAttribute()
{
    return $this->variations->min('additional_price') ?: $this->price;
}




}
