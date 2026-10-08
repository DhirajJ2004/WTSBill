<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToTenant;

class Product extends Model
{
    use SoftDeletes, BelongsToTenant;

    protected $table = 'products';

    protected $fillable = [
        'company_id',
        'category_id',
        'subcategory_id',
        'product_type', // Goods, Services
        'name',
        'sku',
        'barcode',
        'hsn_sac',
        'unit',
        'secondary_unit',
        'conversion_ratio',
        'sales_price',
        'selling_price',
        'purchase_price',
        'mrp',
        'wholesale_price',
        'is_tax_inclusive',
        'tax_rate',
        'gst_rate',
        'cess_rate',
        'current_stock',
        'opening_stock',
        'min_stock_alert',
        'min_stock_level',
        'track_inventory',
        'track_batch',
        'track_serial',
        'track_expiry',
        'allow_negative_stock',
        'reorder_level',
        'reorder_quantity',
        'valuation_method',
        'default_warehouse_id',
        'is_batch_tracked',
        'is_serial_tracked',
        'is_expiry_tracked',
        'image_url',
        'description',
        'is_active',
        'opening_stock_value',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'category_id' => 'integer',
        'subcategory_id' => 'integer',
        'sales_price' => 'float',
        'purchase_price' => 'float',
        'mrp' => 'float',
        'wholesale_price' => 'float',
        'tax_rate' => 'float',
        'cess_rate' => 'float',
        'current_stock' => 'float',
        'min_stock_alert' => 'float',
        'conversion_ratio' => 'float',
        'is_tax_inclusive' => 'boolean',
        'track_inventory' => 'boolean',
        'track_batch' => 'boolean',
        'track_serial' => 'boolean',
        'track_expiry' => 'boolean',
        'allow_negative_stock' => 'boolean',
        'is_batch_tracked' => 'boolean',
        'is_serial_tracked' => 'boolean',
        'is_expiry_tracked' => 'boolean',
        'is_active' => 'boolean',
        'opening_stock_value' => 'float',
        'reorder_level' => 'float',
        'reorder_quantity' => 'float',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    public function prices()
    {
        return $this->hasMany(ProductPrice::class);
    }

    public function batches()
    {
        return $this->hasMany(Batch::class);
    }

    public function serialNumbers()
    {
        return $this->hasMany(SerialNumber::class);
    }

    public function stockBalances()
    {
        return $this->hasMany(StockBalance::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function tags()
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }
}
