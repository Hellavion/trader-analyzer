<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExchangeConnectionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'exchange' => ['required', Rule::in(['bybit', 'mexc'])],
            'api_key' => ['required', 'string', 'min:10', 'max:200'],
            'secret' => ['required', 'string', 'min:10', 'max:200'],
            'sync_settings' => ['sometimes', 'array'],
            'sync_settings.auto_sync' => ['boolean'],
            'sync_settings.sync_interval_hours' => ['integer', 'min:1', 'max:24'],
            'sync_settings.symbols_filter' => ['sometimes', 'array'],
            'sync_settings.symbols_filter.*' => ['string', 'max:20'],
            'sync_settings.categories' => ['sometimes', 'array'],
            'sync_settings.categories.*' => [Rule::in(['spot', 'linear', 'inverse', 'option'])],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'exchange.required' => 'Exchange name is required.',
            'exchange.in' => 'Unsupported exchange. Currently supported: Bybit, MEXC.',
            'api_key.required' => 'API Key is required.',
            'api_key.min' => 'API Key must be at least 10 characters long.',
            'api_key.max' => 'API Key must not exceed 200 characters.',
            'secret.required' => 'Secret Key is required.',
            'secret.min' => 'Secret Key must be at least 10 characters long.',
            'secret.max' => 'Secret Key must not exceed 200 characters.',
            'sync_settings.sync_interval_hours.min' => 'Sync interval must be at least 1 hour.',
            'sync_settings.sync_interval_hours.max' => 'Sync interval must not exceed 24 hours.',
            'sync_settings.categories.*.in' => 'Invalid category. Supported categories: spot, linear, inverse, option.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'api_key' => 'API Key',
            'secret' => 'Secret Key',
            'sync_settings.auto_sync' => 'Auto Sync',
            'sync_settings.sync_interval_hours' => 'Sync Interval',
            'sync_settings.symbols_filter' => 'Symbols Filter',
            'sync_settings.categories' => 'Categories',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validate API key format based on exchange
            $exchange = $this->input('exchange');
            $apiKey = $this->input('api_key');

            if ($exchange === 'bybit' && $apiKey) {
                // Bybit API keys typically start with specific patterns
                if (!preg_match('/^[A-Za-z0-9]{20,}$/', $apiKey)) {
                    $validator->errors()->add('api_key', 'Invalid Bybit API Key format.');
                }
            }

            // Validate categories based on exchange
            $categories = $this->input('sync_settings.categories', []);
            if ($exchange === 'mexc' && in_array('option', $categories)) {
                $validator->errors()->add('sync_settings.categories', 'MEXC does not support options trading.');
            }
        });
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Trim whitespace from sensitive fields
        if ($this->has('api_key')) {
            $this->merge([
                'api_key' => trim($this->input('api_key'))
            ]);
        }

        if ($this->has('secret')) {
            $this->merge([
                'secret' => trim($this->input('secret'))
            ]);
        }

        // Ensure exchange is lowercase
        if ($this->has('exchange')) {
            $this->merge([
                'exchange' => strtolower($this->input('exchange'))
            ]);
        }
    }
}
