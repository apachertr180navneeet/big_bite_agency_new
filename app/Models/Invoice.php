<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'invoices';

    protected $fillable = [
        'date',
        'invoice_no',
        'firm_id',
        'salesperson_id',
        'amount',
        'status',
    ];

    public function firm()
    {
        return $this->belongsTo(Customer::class);
    }

    public function salesperson()
    {
        return $this->belongsTo(Salesperson::class);
    }

    public function receipts()
    {
        return $this->hasMany(Receipt::class, 'invoice_id');
    }
}
