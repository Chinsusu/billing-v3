<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('lifecycle_source', 40)->default('local_policy');
            $table->string('lifecycle_unit', 40)->default('day');
            $table->unsignedInteger('lifecycle_count')->default(30);
            $table->string('provider_lifecycle_path')->nullable();
            $table->string('provider_lifecycle_ordered_at_path', 160)->nullable();
            $table->string('provider_lifecycle_expires_at_path', 160)->nullable();
            $table->string('provider_lifecycle_date_format', 40)->default('iso8601');
            $table->string('provider_lifecycle_timezone', 80)->default('UTC');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn([
                'lifecycle_source',
                'lifecycle_unit',
                'lifecycle_count',
                'provider_lifecycle_path',
                'provider_lifecycle_ordered_at_path',
                'provider_lifecycle_expires_at_path',
                'provider_lifecycle_date_format',
                'provider_lifecycle_timezone',
            ]);
        });
    }
};
