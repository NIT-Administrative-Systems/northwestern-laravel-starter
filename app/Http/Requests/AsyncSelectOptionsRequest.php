<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\View\Components\Select;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Handles validation and retrieval of query parameters for asynchronous select option requests
 * made by a {@see Select} component.
 *
 * The response from this request is expected to be a JSON array of objects with `text` and `value` keys.
 * `text` should be the option label, and `value` should be the option value.
 *
 * TODO link to documentation
 */
class AsyncSelectOptionsRequest extends FormRequest
{
    /**
     * The maximum number of results that should be requested when a limit is not provided.
     */
    private const int DEFAULT_MAX_ALLOWED_LIMIT = 100;

    public function authorize(): bool
    {
        return auth()->check();
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'l' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'q.string' => 'The search query must be a valid string.',
            'q.max' => 'The search query cannot exceed 100 characters.',
            'l.integer' => 'The limit must be a valid integer.',
            'l.min' => 'The limit must be 0 (unlimited) or at least 1.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'q' => 'search query',
            'l' => 'limit',
        ];
    }

    /**
     * Retrieves the sanitized search query from the request.
     *
     * This query should ideally be used to filter results from a database query
     * when fetching results.
     */
    public function getSearchQuery(): ?string
    {
        $query = $this->query('q');

        if (is_array($query)) {
            return null;
        }

        return filled($query) ? (string) $query : null;
    }

    /**
     * Retrieves the limit for the number of results.
     *
     * This limit should ideally be used to apply a {@see Builder::limit()} method
     * to a database query when fetching results.
     *
     * - If a valid integer is provided, it is used as the limit.
     * - If `l=0` or `l=null`, it means the select component likely has {@see Select::$maxOptions} set to null.
     * - For limits that are missing or `0`, it falls back to {@see self::DEFAULT_MAX_ALLOWED_LIMIT}.
     */
    public function getLimit(): int
    {
        $limit = $this->query('l');

        return is_numeric($limit) && (int) $limit > 0
            ? (int) $limit
            : self::DEFAULT_MAX_ALLOWED_LIMIT;
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'message' => 'Invalid request',
                'errors' => $validator->errors(),
            ], 422)
        );
    }
}
