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
        Schema::create('ops_events', function (Blueprint $table) {
            $table->id();
            $table->string('type', 32);
            // Salted hash of the user id or session id, so a visitor counts once per type and day and cannot be traced back
            $table->string('visitor', 40)->nullable();
            $table->date('occurred_on');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['type', 'visitor', 'occurred_on']);
            $table->index(['type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ops_events');
    }
};
