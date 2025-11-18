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
        Schema::create('user_login_records', static function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->index();
            $table->datetime('logged_in_at');
            $table->string('segment');

            $table->timestamps();

            $table->index('logged_in_at');
            $table->index(['logged_in_at', 'segment']);
            $table->index(['logged_in_at', 'user_id']);
        });
    }

    public function down(): void
    {
        throw new NoRollback();
    }
};
