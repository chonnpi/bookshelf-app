<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreBookRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'author' => ['required', 'string', 'max:100'],
            'isbn' => ['required', 'string', 'regex:/^([0-9]{10}|[0-9]{13})$/', 'unique:books,isbn'],
            'published_date' => ['required', 'date_format:Y/m/d'],
            'description' => ['nullable', 'string', 'max:1000'],
            'genres' => ['required', 'array', 'min:1'],
            'genres.*' => ['integer', 'exists:genres,id'],
            'image_url' => ['nullable', 'url', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'タイトルを入力してください',
            'title.max' => 'タイトルは255文字以内で入力してください',
            'author.required' => '著者名を入力してください',
            'author.max' => '著者名は100文字以内で入力してください',
            'isbn.required' => 'isbnを入力してください',
            'isbn.regex' => 'isbnは10桁または13桁の半角数字で入力してください',
            'isbn.unique' => 'このisbnは既に登録されています',
            'published_date.required' => '出版日を入力してください',
            'published_date.date_format' => '正しい日付の形式（YYYY/MM/DD）で入力してください',
            'description.max' => '説明文は1000字以内で入力してください',
            'genres.required' => 'ジャンルを選択してください',
            'genres.min' => 'ジャンルは少なくとも1つ以上選択してください',
            'image_url.url' => '画像URLはURL形式で入力してください',
            'image_url.max' => '画像URLは2048文字以内で入力してください',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'message' => '入力された値に不備があります。',
                'errors' => $validator->errors(),
            ], 422)
        );
    }
}
