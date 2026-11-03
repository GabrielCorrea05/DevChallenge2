<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void //FALTA CONECGTAR LAS CATEGORIAS DE CADA ALIMENTO A UNA FREE API DE COMIDA
    {
        Schema::create('product_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category_name');
            $table->timestamps();
        });

        Schema::create('category_keywords', function (Blueprint $table) {
            $table->id();
            $table->string('keyword')->unique();
            $table->string('category_name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_keywords');
        Schema::dropIfExists('product_templates');
    }
};
