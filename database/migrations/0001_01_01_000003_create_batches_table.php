<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->string('cnpj', 14);
            $table->string('im', 20)->nullable();
            $table->string('numero_lote')->nullable();
            $table->integer('rps_count')->default(0);
            $table->string('xml_file')->nullable();
            $table->string('protocolo')->nullable()->unique();
            $table->string('ambiente', 10)->default('homolog');
            $table->string('status')->default('Transmitido');
            $table->json('dados_originais')->nullable();
            $table->integer('situacao_code')->nullable();
            $table->json('errors')->nullable();
            $table->timestamps();

            $table->index('cnpj');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batches');
    }
};
