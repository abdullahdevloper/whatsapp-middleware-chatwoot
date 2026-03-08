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
        Schema::table('conversation_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('inbox_id')->nullable()->after('chatwoot_conversation_id');
        });

        DB::statement("DROP INDEX IF EXISTS conversation_sessions_one_active_per_conversation");
        DB::statement("CREATE UNIQUE INDEX conversation_sessions_one_active_per_conversation_inbox ON conversation_sessions (tenant_id, chatwoot_conversation_id, inbox_id) WHERE status = 'active'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("DROP INDEX IF EXISTS conversation_sessions_one_active_per_conversation_inbox");
        DB::statement("CREATE UNIQUE INDEX conversation_sessions_one_active_per_conversation ON conversation_sessions (tenant_id, chatwoot_conversation_id) WHERE status = 'active'");

        Schema::table('conversation_sessions', function (Blueprint $table) {
            $table->dropColumn('inbox_id');
        });
    }
};
