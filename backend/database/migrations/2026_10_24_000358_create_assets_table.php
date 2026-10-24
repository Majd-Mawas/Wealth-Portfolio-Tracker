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
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_id')->constrained()->restrictOnDelete();
            $table->string('type', 24);
            $table->string('name', 160);
            $table->string('symbol', 32)->nullable();
            $table->char('currency', 3);
            $table->decimal('current_price', 20, 8);
            $table->timestamp('price_as_of');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->timestamp('archived_at')->nullable();

            $table->index(['portfolio_id', 'archived_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
