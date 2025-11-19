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
        $connection = config('audit.drivers.database.connection', config('database.default'));
        $table = config('audit.drivers.database.table', 'audits');

        Schema::connection($connection)->create($table, function (Blueprint $table) {

            $morphPrefix = config('audit.user.morph_prefix', 'user');

            $table->bigIncrements('id');
            $table->foreignId('impersonator_user_id')->nullable()->index();
            $table->uuid('trace_id')->nullable();

            $table->string($morphPrefix . '_type')->nullable();
            $table->unsignedBigInteger($morphPrefix . '_id')->nullable();
            $table->string('event');
            $table->morphs('auditable');
            $table->text('old_values')->nullable();
            $table->text('new_values')->nullable();
            $table->text('url')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent', 1023)->nullable();
            $table->string('tags')->nullable();
            $table->timestamps();

            $table->index([$morphPrefix . '_id', $morphPrefix . '_type']);
        });
    }

    public function down(): void
    {
        throw new NoRollback();
    }
};
