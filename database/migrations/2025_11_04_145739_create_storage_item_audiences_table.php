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
        Schema::create('storage_item_audiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('storage_item_id')
                ->constrained('storage_items')
                ->cascadeOnDelete();
            $table->string('audience');
            $table->foreignId('team_id')
                ->nullable()
                ->constrained('teams')
                ->cascadeOnDelete();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('permission')->default('viewer');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['storage_item_id', 'audience', 'team_id']);
            $table->index(['audience']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('storage_item_audiences');
    }
};
