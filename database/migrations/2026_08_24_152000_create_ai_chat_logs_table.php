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
        Schema::create('ai_chat_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_key_id')->nullable()->constrained('ai_api_keys')->nullOnDelete();
            $table->string('api_key', 128)->nullable()->index();
            $table->string('store_url', 255)->nullable()->index();
            $table->string('session_id', 128)->nullable()->index();
            $table->string('role', 32)->default('user'); // 'user' | 'assistant'
            $table->longText('message');
            $table->json('actions')->nullable();
            $table->boolean('has_audio')->default(false);
            $table->longText('audio_url')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['api_key', 'created_at']);
            $table->index(['store_url', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_chat_logs');
    }
};
