<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Customer photos attached to a review. Files live on the public disk under reviews/{review_id}/{uuid}.webp
     * (re-encoded, EXIF stripped) with a 600 px thumbnail next to them. Every photo starts `pending` and is only
     * shown on the storefront once a moderator approves it.
     */
    public function up(): void
    {
        Schema::create('review_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained('reviews')->cascadeOnDelete();
            $table->string('path');
            $table->string('thumbnail_path');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamps();

            $table->index(['review_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_media');
    }
};
