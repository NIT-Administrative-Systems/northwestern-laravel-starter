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
        Schema::create('login_challenges', static function (Blueprint $table) {
            $table->id();

            $table->string('email')->index();
            $table->string('code_hash');

            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('locked_until')->nullable();

            $table->timestamp('expires_at')->index();
            $table->timestamp('email_sent_at')->nullable();

            $table->timestamp('consumed_at')->nullable()->index();

            $table->string('requested_ip', 45)->nullable();
            $table->string('requested_user_agent', 512)->nullable();
            $table->string('consumed_ip', 45)->nullable();
            $table->string('consumed_user_agent', 512)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        throw new NoRollback();
    }
};
