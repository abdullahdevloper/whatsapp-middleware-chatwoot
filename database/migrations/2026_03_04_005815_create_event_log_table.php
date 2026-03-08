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
        Schema::create('event_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('event_uid', 64);
            $table->string('event_type')->nullable();
            $table->unsignedBigInteger('conversation_id')->nullable();
            $table->string('message_uid')->nullable();
            $table->timestamp('received_at')->useCurrent();

            $table->unique(['tenant_id', 'event_uid']);
            $table->index(['tenant_id', 'conversation_id']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_log');
    }
};
