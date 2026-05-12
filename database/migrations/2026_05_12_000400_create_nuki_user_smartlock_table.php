<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nuki_user_smartlock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nuki_user_id')->constrained('nuki_users')->cascadeOnDelete();
            $table->foreignId('nuki_account_id')->constrained('nuki_accounts')->cascadeOnDelete();
            $table->unsignedBigInteger('smartlock_id');
            $table->boolean('can_lock')->default(false);
            $table->boolean('can_unlock')->default(false);
            $table->boolean('can_view_logs')->default(false);
            $table->boolean('can_manage_auths')->default(false);
            $table->timestamp('allowed_from')->nullable();
            $table->timestamp('allowed_until')->nullable();
            $table->unsignedTinyInteger('allowed_weekdays')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['nuki_user_id', 'smartlock_id']);
            $table->unique(['nuki_user_id', 'nuki_account_id', 'smartlock_id'], 'nuki_user_smartlock_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nuki_user_smartlock');
    }
};
