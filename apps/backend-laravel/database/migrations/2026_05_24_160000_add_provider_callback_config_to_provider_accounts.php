<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provisioning_provider_accounts', function (Blueprint $table): void {
            $table->text('callback_secret')->nullable()->after('api_key_last_four');
            $table->string('callback_secret_last_four', 4)->nullable()->after('callback_secret');
            $table->string('callback_event_id_path', 120)->default('event_id')->after('response_config_path');
            $table->string('callback_external_id_path', 120)->default('external_id')->after('callback_event_id_path');
            $table->string('callback_action_path', 120)->default('action')->after('callback_external_id_path');
            $table->string('callback_status_path', 120)->default('status')->after('callback_action_path');
        });
    }

    public function down(): void
    {
        Schema::table('provisioning_provider_accounts', function (Blueprint $table): void {
            $table->dropColumn([
                'callback_secret',
                'callback_secret_last_four',
                'callback_event_id_path',
                'callback_external_id_path',
                'callback_action_path',
                'callback_status_path',
            ]);
        });
    }
};
