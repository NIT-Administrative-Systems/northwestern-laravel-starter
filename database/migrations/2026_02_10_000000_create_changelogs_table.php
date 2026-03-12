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
        Schema::create('changelogs', static function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title')->nullable();
            $table->date('authored_at');
            $table->text('body');
            $table->softDeletes();
            $table->timestamps();

            $table->index('authored_at');
        });
    }

    public function down(): never
    {
        throw new NoRollbackException();
    }
};
