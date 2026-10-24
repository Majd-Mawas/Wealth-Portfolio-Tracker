<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('kind', 24);
            $table->decimal('quantity_delta', 24, 8);
            $table->decimal('unit_price', 20, 8)->nullable();
            $table->decimal('fees', 20, 8)->default(0);
            $table->timestamp('occurred_at');
            $table->string('note', 500)->nullable();
            $table->string('source', 16);
            $table->foreignId('reversal_of_id')
                ->nullable()
                ->unique()
                ->constrained('transactions')
                ->restrictOnDelete();
            $table->timestamps();

            $table->index(['asset_id', 'occurred_at', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
