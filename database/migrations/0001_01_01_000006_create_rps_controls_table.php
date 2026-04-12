<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rps_controls', function (Blueprint $table) {
            $table->string('cnpj', 14);
            $table->string('serie', 5);
            $table->unsignedInteger('ultimo_numero')->default(0);
            $table->primary(['cnpj', 'serie']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rps_controls');
    }
};
