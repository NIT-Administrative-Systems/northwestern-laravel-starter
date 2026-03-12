<?php

declare(strict_types=1);

use App\Domains\Core\Exceptions\NoRollbackException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('type');
            $table->morphs('notifiable');
            $table->json('data');

            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): never
    {
        throw new NoRollbackException();
    }
};
