<?php

declare(strict_types=1);

namespace App\Domains\User\Models;

use App\Domains\Core\Models\BaseModel;
use App\Domains\User\Enums\UserSegmentEnum;
use Database\Factories\Domains\User\Models\UserLoginRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLoginRecord extends BaseModel
{
    /** @use HasFactory<UserLoginRecordFactory> */
    use HasFactory;

    public static $auditingDisabled = true;

    protected $casts = [
        'segment' => UserSegmentEnum::class,
        'logged_in_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
