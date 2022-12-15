<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransactionDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transaction_details', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger("transaction_id")->nullable();
            $table->foreign("transaction_id")->references("id")->on("transactions")->onDelete("cascade");
            $table->string('sender_account_id')->nullable();
            $table->string('receiver_account_id')->nullable();
            $table->string('payment_intent_id')->nullable();
            $table->string('balance_transaction_id')->nullable();
            $table->string('charge_id')->nullable();
            $table->string('transfer_id')->nullable();
            $table->string('refund_id')->nullable();
            $table->string('save_status')->nullable();
            $table->decimal("amount_paid",10,2)->nullable();
            $table->decimal("stripe_fees",10,2)->nullable();
            $table->decimal("application_fees",10,2)->nullable();
            \App\Helpers\DbExtender::defaultParams($table);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transaction_details');
    }
}
