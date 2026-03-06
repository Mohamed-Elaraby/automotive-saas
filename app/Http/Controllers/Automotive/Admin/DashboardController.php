<?php

namespace App\Http\Controllers\automotive\Admin;

use App\Http\Controllers\Controller;

class DashboardController extends Controller
{
    public function index()
    {
        return view('automotive.admin.dashboard.index');
    }
}
