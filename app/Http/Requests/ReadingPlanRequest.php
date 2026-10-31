<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReadingPlanRequest extends FormRequest
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
        // 読書計画作成か編集かでルールを切り替える(storeRulesかupdateRules)
        if ($this->isMethod('post')) {
            return $this->storeRules();
        }

        return $this->updateRules();
    }

    /**
     * 読書計画作成時のバリデーションルール
     */
    protected function storeRules(): array
    {
        return [
            'book_id' => [
                'required',
                'exists:books,id',
                Rule::unique('reading_plans', 'book_id')->where(function ($query) {
                    return $query->where('user_id', auth()->id());
                }),
            ],
            'target_date' => [
                'required',
                'date',
                'date_format:Y/m/d',
                'after_or_equal:today',
            ],
        ];
    }

    protected function updateRules(): array
    {
        return [
            'target_date' => [
                'required',
                'date',
                'date_format:Y/m/d',
                'after_or_equal:today',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'book_id.required' => '対象の書籍を選択してください。',
            'book_id.exists' => '選択された書籍は存在しません。',
            'book_id.unique' => 'この書籍はすでに読書計画に登録されています。',
            'target_date.required' => '目標期日を入力してください。',
            'target_date.date' => '正しい日付の形式で入力してください。',
            'target_date.date_format' => '日付は「YYYY/MM/DD」の形式で入力してください。',
            'target_date.after_or_equal' => '目標期日には今日以降の日付を指定してください。',
        ];
    }
}
