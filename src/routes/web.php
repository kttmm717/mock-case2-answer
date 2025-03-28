<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::post('/register', [AuthController::class, 'store']);

// メール認証画面で再送するボタン押した時
Route::post('/email/verify', function() {
    session()->get('unauthenticated_user')->sendEmailVerificationNotification();
    return back()->with('message', '認証メールを再送しました！');
});
// ユーザーがメール認証した時
Route::get('/email/verify/{id}/{hash}', function(EmailVerificationRequest $request) {
    $request->fulfill();
    session()->forget('unauthenticated_user');
    return redirect('/attendance');
})->middleware(['auth', 'signed'])->name('verification.verify');

//----------------------------------------------------------------------------------------------

//一般ルート
Route::middleware('auth')->group(function() {
    Route::get('/attendance', [UserController::class, 'index']);
    Route::post('/attendance', [UserController::class, 'attendance']);
    Route::get('/attendance/list', [UserController::class, 'list']);
    Route::get('/attendance/{id}', [UserController::class, 'detail']);
    Route::post('/attendance/{id}', [UserController::class, 'amendmentApplication']);
    Route::get('/stamp_correction_request/list', [UserController::class, 'applicationList']);
});

