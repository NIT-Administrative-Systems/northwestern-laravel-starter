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
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->string('subject');
            $table->text('details');
            $table->string('requester_email')->nullable();
            $table->string('ticketing_system')->nullable();
            $table->string('ticket_number')->nullable();
            $table->boolean('post_error')->default(false);
            $table->text('error_message')->nullable();
            $table->timestamp('posted_to_ticketing_system_at')->nullable();
            $table->timestamp('fallback_sent_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('user_id');
            $table->index('ticketing_system');
            $table->index(['post_error', 'created_at']);
        });
    }

    public function down(): never
    {
        throw new NoRollback();
    }
};
