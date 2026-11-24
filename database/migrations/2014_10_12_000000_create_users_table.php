<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('nif')->nullable();
            $table->string('role', 32);
            $table->string('status', 32);
            $table->string('document_path')->nullable();
            $table->string('document_name')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();

            $table->index(['role', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('users');
    }
};
