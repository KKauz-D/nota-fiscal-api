<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('batches')->cascadeOnDelete();
            $table->string('numero_nfse')->nullable();
            $table->string('codigo_verificacao')->nullable();
            $table->timestamp('data_emissao')->nullable();
            $table->string('tomador_nome')->nullable();
            $table->decimal('valor_servicos', 15, 2)->default(0);
            $table->string('cnpj', 14);
            $table->string('im', 20)->nullable();
            $table->string('status')->default('emitida');
            $table->text('motivo_cancelamento')->nullable();
            $table->timestamps();

            $table->index(['cnpj', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
