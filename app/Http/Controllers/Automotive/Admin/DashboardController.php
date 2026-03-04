<?php

namespace App\Http\Controllers\automotive\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        return view('automotive.admin.dashboard');
    }
}
