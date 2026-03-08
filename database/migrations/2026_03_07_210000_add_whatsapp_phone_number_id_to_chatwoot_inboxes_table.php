<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chatwoot_inboxes', function (Blueprint $table) {
            $table->string('whatsapp_phone_number_id')->nullable()->after('phone_number');
        });
    }

    public function down(): void
    {
        Schema::table('chatwoot_inboxes', function (Blueprint $table) {
            $table->dropColumn('whatsapp_phone_number_id');
        });
    }
};
