<?php

declare(strict_types=1);

use Fw\Database\Migration\Blueprint;
use Fw\Database\Migration\Migration;

final class CreateUsersTable extends Migration
{
    public function up(): void
    {
        $this->createTable('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->dropTable('users');
    }
}
