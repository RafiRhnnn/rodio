<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Creates a conversion request (Tahap 6). Note what is NOT accepted from the
 * client: user_id (taken from the session) and any file path.
 */
class StoreConversionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isActive();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // Presets (2.1 - 2.9) and custom values share one safe range check.
            'speed' => [
                'required',
                'numeric',
                'decimal:0,2',
                'min:'.config('audio.speed.min'),
                'max:'.config('audio.speed.max'),
            ],
            'output_format' => ['nullable', Rule::in(array_keys(config('audio.outputs')))],
            'preserve_pitch' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'speed.required' => 'Pilih kecepatan terlebih dahulu.',
            'speed.numeric' => 'Kecepatan harus berupa angka.',
            'speed.decimal' => 'Kecepatan maksimal dua angka di belakang koma.',
            'speed.min' => 'Kecepatan minimal '.config('audio.speed.min').'x.',
            'speed.max' => 'Kecepatan maksimal '.config('audio.speed.max').'x.',
            'output_format.in' => 'Format output tidak didukung.',
        ];
    }

    public function outputFormat(): string
    {
        return strtolower((string) ($this->input('output_format') ?: config('audio.default_output_format')));
    }

    public function speed(): float
    {
        return round((float) $this->input('speed'), 2);
    }
}
