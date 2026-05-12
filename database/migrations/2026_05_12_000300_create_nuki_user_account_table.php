<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nuki_user_account', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nuki_user_id')->constrained('nuki_users')->cascadeOnDelete();
            $table->foreignId('nuki_account_id')->constrained('nuki_accounts')->cascadeOnDelete();
            $table->string('role')->default('member');
            $table->timestamps();

            $table->unique(['nuki_user_id', 'nuki_account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nuki_user_account');
    }
};
