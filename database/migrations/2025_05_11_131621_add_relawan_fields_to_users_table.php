<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Add role field
            $table->enum('role', ['user', 'relawan', 'admin'])->default('user')->after('email');
            
            // Add relawan specific fields
            $table->string('nik', 16)->nullable()->after('role');
            $table->string('no_telp', 15)->nullable()->after('nik');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'nik', 'no_telp']);
        });
    }
};