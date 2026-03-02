<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Salesperson extends Model
{
    use HasFactory, SoftDeletes; // ✅ Added


    // ✅ Explicit table name
    protected $table = 'salespersons';

    protected $fillable = [
        'name',
        'mobile',
        'email',
        'password',
        'address',
        'dob',
        'alternative_phone',
        'status',
        'salesperson_code'
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'dob' => 'date',
    ];
}