<?php

namespace App\Http\Controllers\automotive\Admin\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function login()
    {
        return View('automotive.auth.login');
    }
}
