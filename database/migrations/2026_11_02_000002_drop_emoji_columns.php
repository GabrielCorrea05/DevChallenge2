<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Elimina las columnas de emojis. La interfaz ya no usa iconos decorativos:
 * los nombres de lista, categoría y producto son texto plano.
 *
 * Va protegida con hasColumn para que también funcione en instalaciones nuevas,
 * donde las migraciones de creación ya no definen estas columnas.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['lists', 'categories', 'product_templates'] as $tableName) {
            if (Schema::hasColumn($tableName, 'emoji')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->dropColumn('emoji');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['lists', 'categories', 'product_templates'] as $tableName) {
            if (! Schema::hasColumn($tableName, 'emoji')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->string('emoji')->nullable();
                });
            }
        }
    }
};
