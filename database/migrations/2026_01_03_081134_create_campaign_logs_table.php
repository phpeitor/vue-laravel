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
        Schema::create('campaign_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();

            $table->string('type', 50);
            $table->string('message');
            $table->jsonb('meta')->nullable();

            $table->timestamps();

            $table->index('type');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_logs');
    }
};
