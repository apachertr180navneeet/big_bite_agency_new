<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Receipt extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'receipts';

    protected $fillable = [
        'date',
        'receipt_no',
        'firm_id',
        'invoice_id',
        'amount',
        'given_amount',
        'discount',
        'final_amount',
        'sales_person',
        'mode',
        'manager_status',
        'status',
    ];

    public function firm()
    {
        return $this->belongsTo(Customer::class, 'firm_id');
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }
}

