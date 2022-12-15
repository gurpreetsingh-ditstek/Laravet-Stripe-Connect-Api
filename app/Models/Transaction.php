<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = ['user_id','user_order_id','egift_id','transaction_status','transaction_type'];

    public function TransactionDetail() {
        return $this->hasOne(TransactionDetails::class);
    }
}
