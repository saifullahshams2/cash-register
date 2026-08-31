<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->decimal('subtotal', 10, 3)->default(0.000);
            $table->decimal('discount', 10, 3)->default(0.000);
            $table->decimal('tax', 10, 3)->default(0.000);
            $table->decimal('total', 10, 3)->default(0.000);
            $table->decimal('tendered', 10, 3)->default(0.000);
            $table->decimal('change', 10, 3)->default(0.000);
            $table->string('payment_method')->default('CASH'); // CASH, CARD, KNET
            $table->string('status')->default('COMPLETED'); // COMPLETED, HELD, CANCELLED
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
