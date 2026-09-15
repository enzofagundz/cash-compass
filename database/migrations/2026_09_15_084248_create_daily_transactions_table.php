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
        Schema::create('daily_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('type', 20);
            $table->decimal('amount', 10, 2);
            $table->string('description')->nullable();
            $table->foreignId('account_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_recurring')->default(false);
            $table->string('status', 20)->default('realized');
            $table->timestamps();

            $table->unique(['user_id', 'account_plan_id', 'date']);
            $table->index(['user_id', 'date', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_transactions');
    }
};
