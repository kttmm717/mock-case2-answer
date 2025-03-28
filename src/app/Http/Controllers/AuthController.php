<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Actions\Fortify\CreateNewUser;

class AuthController extends Controller
{
    public function store(Request $request, CreateNewUser $creator) {
        $user = $creator->create($request->all()); //フォームで入力されたものを受け取って新規登録
        $user->sendEmailVerificationNotification(); //入力されたemailに認証メール送信

        session()->put('unauthenticated_user', $user);
        return view('auth.verify-email');
    }
}
