<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenreRequest extends FormRequest
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
        $genre = $this->route('genre');

        $genreRule = Rule::unique('genres', 'name');

        if ($genre) {
            $genreRule->ignore($genre);
        }

        return [
            'name' => ['required', 'string', 'max:50', $genreRule],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'ジャンル名を入力してください',
            'name.max' => 'ジャンル名は50文字以内で入力してください',
            'name.unique' => 'このジャンル名はすでに登録されています',
        ];
    }
}
