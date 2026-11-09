<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProductPhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'disk',
        'path',
        'alt',
        'is_cover',
        'position',
    ];

    protected $casts = [
        'is_cover' => 'boolean',
        'position' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    public function thumbPathConvention(): string
    {
        return preg_replace('/\.[^.]+$/', '', $this->path).'_thumb.jpg';
    }

    public function thumbUrl(): string
    {
        $disk = Storage::disk($this->disk);
        $thumb = $this->thumbPathConvention();

        return $disk->url($disk->exists($thumb) ? $thumb : $this->path);
    }
}
