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
        Schema::create('impersonation_logs', static function (Blueprint $table) {
            $table->id();

            $table->foreignId('impersonator_user_id')->index();
            $table->foreignId('impersonated_user_id')->index();

            $table->timestamps();
        });

        Schema::table('audits', static function (Blueprint $table) {
            $table->foreignId('impersonator_user_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        throw new NoRollback();
    }
};
