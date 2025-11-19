<?php

declare(strict_types=1);

use App\Domains\Core\Exceptions\NoRollback;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_one_time_login_links', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');

            $table->string('token')->unique();
            $table->string('email')->index();
            $table->timestamp('expires_at')->index();
            $table->timestamp('used_at')->nullable();
            $table->string('requested_ip_address', 45)->nullable();
            $table->string('used_ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['token', 'expires_at', 'used_at']);
            $table->index(['email', 'created_at']);
        });
    }

    public function down(): void
    {
        throw new NoRollback();
    }
};
