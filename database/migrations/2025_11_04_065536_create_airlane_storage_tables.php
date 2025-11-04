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
        Schema::create('storage_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('storage_items')
                ->cascadeOnDelete();
            $table->string('type');
            $table->string('name');
            $table->string('slug');
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('disk')->default('private');
            $table->string('stored_path')->nullable();
            $table->string('mime_type')->nullable();
            $table->string('checksum', 128)->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_favorite')->default(false);
            $table->unsignedBigInteger('latest_version_id')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['user_id', 'parent_id', 'slug']);
            $table->index(['user_id', 'type']);
        });

        Schema::create('storage_item_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('storage_item_id')
                ->constrained('storage_items')
                ->cascadeOnDelete();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->unsignedInteger('version');
            $table->string('disk')->default('private');
            $table->string('stored_path')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('checksum', 128)->nullable();
            $table->json('metadata')->nullable();
            $table->longText('content')->nullable();
            $table->timestamps();

            $table->unique(['storage_item_id', 'version']);
        });

        Schema::table('storage_items', function (Blueprint $table) {
            $table->foreign('latest_version_id')
                ->references('id')
                ->on('storage_item_versions')
                ->nullOnDelete();
        });

        Schema::create('storage_item_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('storage_item_id')
                ->constrained('storage_items')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('granted_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('permission');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['storage_item_id', 'user_id']);
        });

        Schema::create('storage_share_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('storage_item_id')
                ->constrained('storage_items')
                ->cascadeOnDelete();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('token')->unique();
            $table->string('permission');
            $table->unsignedInteger('max_views')->nullable();
            $table->unsignedInteger('view_count')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('storage_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('color')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'slug']);
        });

        Schema::create('storage_item_storage_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('storage_item_id')
                ->constrained('storage_items')
                ->cascadeOnDelete();
            $table->foreignId('storage_tag_id')
                ->constrained('storage_tags')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['storage_item_id', 'storage_tag_id']);
        });

        Schema::create('storage_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('storage_item_id')
                ->nullable()
                ->constrained('storage_items')
                ->nullOnDelete();
            $table->string('action');
            $table->string('ip_address', 45)->nullable();
            $table->json('properties')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index('occurred_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('storage_items', function (Blueprint $table) {
            $table->dropForeign(['latest_version_id']);
        });

        Schema::dropIfExists('storage_activities');
    Schema::dropIfExists('storage_item_storage_tag');
        Schema::dropIfExists('storage_tags');
        Schema::dropIfExists('storage_share_links');
        Schema::dropIfExists('storage_item_permissions');
        Schema::dropIfExists('storage_item_versions');
        Schema::dropIfExists('storage_items');
    }
};
