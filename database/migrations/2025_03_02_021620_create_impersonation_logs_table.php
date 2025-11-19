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
        Schema::create('user_impersonation_logs', static function (Blueprint $table) {
            $table->id();

            $table->foreignId('impersonator_user_id')->index();
            $table->foreignId('impersonated_user_id')->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        throw new NoRollback();
    }
};
