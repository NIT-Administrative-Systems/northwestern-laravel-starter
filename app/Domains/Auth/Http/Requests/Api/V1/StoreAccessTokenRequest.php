<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Northwestern\SysDev\Chassis\Rules\ValidIpOrCidrRule;

class StoreAccessTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string|ValidIpOrCidrRule>>
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
