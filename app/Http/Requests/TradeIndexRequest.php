<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TradeIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'symbol' => ['nullable', 'string', 'in:BTC,ETH'],
        ];
    }
}
