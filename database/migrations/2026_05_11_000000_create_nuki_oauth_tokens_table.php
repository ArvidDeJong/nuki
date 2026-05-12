<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nuki_oauth_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('account_key')->unique();
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->dateTime('expires_at');
            $table->string('token_type')->nullable();
            $table->string('scope')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nuki_oauth_tokens');
    }
};
