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
        Schema::create('campaign_recipients', function (Blueprint $table) {
            $table->id();

            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_upload_id')->constrained()->cascadeOnDelete();

            $table->string('phone', 20);

            // Variables dinámicas {{1}} {{2}} {{3}} ...
            $table->jsonb('variables');

            $table->enum('status', [
                'PENDING',
                'SENT',
                'FAILED'
            ])->default('PENDING');

            $table->string('provider_message_id')->nullable();
            $table->text('error_message')->nullable();

            $table->timestamps();

            $table->index('phone');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_recipients');
    }
};
