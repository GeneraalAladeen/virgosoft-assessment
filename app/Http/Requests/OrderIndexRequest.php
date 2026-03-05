<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OrderIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'symbol' => ['nullable', 'string', 'in:BTC,ETH'],
            'side'   => ['nullable', 'string', 'in:buy,sell'],
            'status' => ['nullable', 'integer', 'in:1,2,3'],
        ];
    }
}
