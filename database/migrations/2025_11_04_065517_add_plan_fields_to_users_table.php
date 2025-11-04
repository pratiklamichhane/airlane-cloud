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
        Schema::table('users', function (Blueprint $table) {
            $table->string('plan')
                ->default(config('airlane.default_plan', 'basic'))
                ->after('email')
                ->index();
            $table->unsignedBigInteger('storage_used_bytes')
                ->default(0)
                ->after('plan');
            $table->unsignedBigInteger('max_storage_bytes')
                ->default((int) config('airlane.plans.basic.storage_limit_bytes'))
                ->after('storage_used_bytes');
            $table->unsignedBigInteger('max_file_size_bytes')
                ->default((int) config('airlane.plans.basic.max_file_size_bytes'))
                ->after('max_storage_bytes');
            $table->unsignedInteger('version_cap')
                ->default((int) config('airlane.plans.basic.version_cap'))
                ->after('max_file_size_bytes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'plan',
                'storage_used_bytes',
                'max_storage_bytes',
                'max_file_size_bytes',
                'version_cap',
            ]);
        });
    }
};
