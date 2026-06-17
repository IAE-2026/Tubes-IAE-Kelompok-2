<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * CreateInvoiceRequest
 *
 * Form Request untuk validasi pembuatan invoice.
 * Jika validasi gagal, otomatis return JSON error response.
 */
class CreateInvoiceRequest extends FormRequest
{
    /**
     * Authorize: semua request yang sudah melewati ApiKeyAuth middleware dianggap authorized.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Aturan validasi request.
     */
    public function rules(): array
    {
        return [
            'auction_id' => ['required', 'string', 'min:3', 'max:100'],
            'use_mock'   => ['nullable', 'boolean'],
        ];
    }

    /**
     * Custom pesan error dalam Bahasa Indonesia.
     */
    public function messages(): array
    {
        return [
            'auction_id.required' => 'auction_id wajib diisi.',
            'auction_id.string'   => 'auction_id harus berupa string.',
            'auction_id.min'      => 'auction_id minimal 3 karakter.',
            'auction_id.max'      => 'auction_id maksimal 100 karakter.',
        ];
    }

    /**
     * Override failed validation untuk return JSON sesuai standard contract.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'status'  => 'error',
                'message' => 'Validasi request gagal.',
                'errors'  => $validator->errors(),
            ], 422)
        );
    }
}
