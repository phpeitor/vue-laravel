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
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('description')->nullable();

            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('communication_channel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_id')->constrained('message_templates');

            $table->date('start_date');
            $table->date('end_date');

            $table->enum('status', [
                'DRAFT',
                'UPLOADED',
                'PROCESSING',
                'FINISHED',
                'FAILED',
                'CANCELLED'
            ])->default('DRAFT');

            $table->string('type');
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
