<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePanelNormalizationRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'status' => strtoupper(trim((string) $this->input('status', 'AUTO'))),
            'standard_name' => trim((string) $this->input('standard_name', '')) ?: null,
            'aliases' => array_values(array_filter(array_map(function ($alias) {
                return trim((string) $alias);
            }, (array) $this->input('aliases', [])), function ($alias) {
                return $alias !== '';
            })),
        ]);
    }

    public function rules()
    {
        return [
            'standard_name' => ['nullable', 'required_if:status,AUTO', 'string', 'max:255'],
            'status' => ['required', Rule::in(['AUTO', 'PENDING'])],
            'aliases' => ['required', 'array', 'min:1', 'max:100'],
            'aliases.*' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
        ];
    }

    public function messages()
    {
        return [
            'standard_name.required_if' => 'PANEL tự động phải có tên chuẩn.',
            'aliases.required' => 'Cần nhập ít nhất một cách viết.',
            'aliases.min' => 'Cần nhập ít nhất một cách viết.',
        ];
    }
}
