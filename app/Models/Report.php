<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'photo_path',
        'location',
        'problem_type',
        'description',
        'status',
        'admin_notes',
    ];

    /**
     * Aksesor yang akan ditambahkan ke array form model.
     *
     * @var array
     */
    protected $appends = ['photo_url'];

    /**
     * Relasi yang otomatis dimuat.
     *
     * @var array
     */
    protected $with = ['user'];

    /**
     * Dapatkan URL untuk foto laporan.
     */
    public function getPhotoUrlAttribute()
    {
        if ($this->photo_path) {
            return url('storage/' . $this->photo_path);
        }
        
        return null;
    }

    /**
     * Pengguna yang membuat laporan.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}