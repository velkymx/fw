<?php

declare(strict_types=1);

use Fw\Database\Migration\Blueprint;
use Fw\Database\Migration\Migration;

final class CreatePostsTable extends Migration
{
    public function up(): void
    {
        $this->createTable('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('content')->nullable();
            $table->datetime('published_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        $this->dropTable('posts');
    }
}
