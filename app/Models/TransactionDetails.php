<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionDetails extends Model
{
    use HasFactory;
    
    protected $fillable = ['transaction_id','sender_account_id','receiver_account_id','payment_intent_id','charge_id','transfer_id','refund_id','save_status','amount_paid'];
}
