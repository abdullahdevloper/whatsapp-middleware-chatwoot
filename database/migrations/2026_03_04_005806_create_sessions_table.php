<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('conversation_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('chatwoot_conversation_id');
            $table->unsignedBigInteger('chatwoot_contact_id')->nullable();
            $table->string('state_key')->nullable();
            $table->json('state_payload')->nullable();
            $table->string('status')->default('active');
            $table->string('paused_reason')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('expired_notified_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'chatwoot_conversation_id']);
            $table->index(['tenant_id', 'status']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        DB::statement("CREATE UNIQUE INDEX conversation_sessions_one_active_per_conversation ON conversation_sessions (tenant_id, chatwoot_conversation_id) WHERE status = 'active'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("DROP INDEX IF EXISTS conversation_sessions_one_active_per_conversation");
        Schema::dropIfExists('conversation_sessions');
    }
};
