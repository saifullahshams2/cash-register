<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('code')->nullable()->change();
            $table->string('category')->nullable()->default('General')->change();
            $table->decimal('price', 10, 3)->nullable()->default(0.000)->change();
            $table->decimal('cost', 10, 3)->nullable()->default(0.000)->change();
            $table->integer('stock')->nullable()->default(9999)->change();
            $table->string('image')->nullable()->change();
            $table->string('color')->nullable()->default('slate')->change();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            //
        });
    }
};
