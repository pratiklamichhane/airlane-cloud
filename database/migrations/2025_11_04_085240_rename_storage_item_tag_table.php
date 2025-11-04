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
        if (Schema::hasTable('storage_item_tag') && ! Schema::hasTable('storage_item_storage_tag')) {
            Schema::rename('storage_item_tag', 'storage_item_storage_tag');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('storage_item_storage_tag') && ! Schema::hasTable('storage_item_tag')) {
            Schema::rename('storage_item_storage_tag', 'storage_item_tag');
        }
    }
};
