<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Requests\Api\V1;

use App\Domains\Core\Rules\ValidIpOrCidrRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreAccessTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'expires_at' => ['nullable', 'integer', 'min:' . now()->addDay()->timestamp, 'max:' . now()->addYears(10)->timestamp],
            'allowed_ips' => ['nullable', 'array'],
            'allowed_ips.*' => ['string', new ValidIpOrCidrRule()],
        ];
    }
}
