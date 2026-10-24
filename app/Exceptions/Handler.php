<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->renderable(function (Throwable $e, $request) {
            if ($request->is('api/*')) {

                // 1. バリデーションエラー
                if ($e instanceof ValidationException) {
                    return response()->json([
                        'message' => '入力内容にエラーがあります。',
                        'errors' => $e->errors(),
                    ], 422);
                }

                // 2. 404モデル不在エラー
                if ($e instanceof NotFoundHttpException) {
                    return response()->json([
                        'message' => '指定されたリソースが見つかりません。',
                    ], 404);
                }

                // 3. その他すべてのエラー
                return response()->json([
                    'message' => 'サーバーエラーが発生しました。',
                    'error' => config('app.debug') ? $e->getMessage() : null,
                ], 500);
            }
        });
    }
}
