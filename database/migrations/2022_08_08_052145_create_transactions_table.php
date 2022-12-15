<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger("user_id")->nullable();
            $table->foreign("user_id")->references("id")->on("users")->onDelete("cascade");
            $table->unsignedBigInteger("user_order_id")->nullable();
            $table->foreign("user_order_id")->references("id")->on("user_orders")->onDelete("cascade");
            $table->unsignedBigInteger("egift_id")->nullable();
            $table->foreign("egift_id")->references("id")->on("egifts");
            $table->enum("transaction_status", ["1", "2", "3", "4", "5", "6"])->default("1")->comment("1=>Uncapture ,2=>Captured ,3=>Succeeded,4=>Incomplete,5=>Partial refund,6=>Cancelled");
            $table->enum("transaction_type", ["1", "2"])->default("1")->comment("1=>Treatment Payment ,2=>E-Gift");
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
        Schema::dropIfExists('transactions');
    }
}
