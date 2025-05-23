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
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->decimal('rating', 2, 1);
            $table->string('title')->nullable();
            $table->text('comment')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_approved')->default(true);
            $table->json('helpful_votes')->nullable();
            $table->integer('helpful_count')->default(0);
            $table->timestamps();

            // Prevent duplicate reviews from same user for same product
            $table->unique(['product_id', 'user_id']);

            // Index for better query performance
            $table->index(['product_id', 'is_approved']);
            $table->index(['user_id']);
            $table->index(['rating']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
