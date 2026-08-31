<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique(); // SKU or Barcode
            $table->string('category')->default('General');
            $table->decimal('price', 10, 3); // 3 decimal places for KWD
            $table->decimal('cost', 10, 3)->nullable()->default(0.000);
            $table->integer('stock')->default(100);
            $table->string('image')->nullable();
            $table->string('color')->nullable()->default('emerald');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
