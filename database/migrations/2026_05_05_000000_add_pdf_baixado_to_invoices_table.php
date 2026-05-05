<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->boolean('pdf_baixado')->default(false)->after('motivo_cancelamento');
            $table->timestamp('pdf_baixado_em')->nullable()->after('pdf_baixado');
            $table->string('pdf_caminho')->nullable()->after('pdf_baixado_em');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['pdf_baixado', 'pdf_baixado_em', 'pdf_caminho']);
        });
    }
};
