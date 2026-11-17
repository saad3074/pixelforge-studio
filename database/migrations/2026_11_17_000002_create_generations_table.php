<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('provider_id')->nullable()->unique();
            $table->string('status', 32)->default('pending')->index();
            $table->text('prompt');
            $table->string('size', 32);
            $table->string('quality', 16);
            $table->json('reference_images')->nullable();
            $table->json('rates')->nullable();
            $table->json('usage')->nullable();
            $table->bigInteger('estimated_provider_min')->nullable();
            $table->bigInteger('estimated_provider_expected')->nullable();
            $table->bigInteger('estimated_provider_max')->nullable();
            $table->bigInteger('estimated_user_min')->nullable();
            $table->bigInteger('estimated_user_expected')->nullable();
            $table->bigInteger('estimated_user_max')->nullable();
            $table->bigInteger('provider_cost')->nullable();
            $table->bigInteger('user_cost')->nullable();
            $table->unsignedSmallInteger('markup_percent')->default(25);
            $table->text('image_url')->nullable();
            $table->timestamp('image_url_expires_at')->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('provider_created_at')->nullable();
            $table->timestamp('provider_started_at')->nullable();
            $table->timestamp('provider_completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generations');
    }
};
