<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BookRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $book = $this->route('book');
        $isbnUniqueRule = Rule::unique('books', 'isbn');
        if ($book) {
            $isbnUniqueRule->ignore($book);
        }

        return [
            'title' => ['required', 'string', 'max:255'],
            'author' => ['required', 'string', 'max:100'],
            'isbn' => ['required', 'string', 'regex:/^([0-9]{10}|[0-9]{13})$/', $isbnUniqueRule],
            'published_date' => ['required', 'date'],
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
            'published_date.date' => '正しい日付の形式（YYYY/MM/DD）で入力してください',
            'description.max' => '説明文は1000字以内で入力してください',
            'genres.required' => 'ジャンルを選択してください',
            'genres.min' => 'ジャンルは少なくとも1つ以上選択してください',
            'image_url.url' => '画像URLはURL形式で入力してください',
            'image_url.max' => '画像URLは2048文字以内で入力してください',
        ];
    }
}
