<?php

namespace App\Http\Controllers;

class WeavingManagementPageController extends Controller
{
    public function dashboard()
    {
        return view('client.weaving-dashboard');
    }

    public function createOrder()
    {
        return view('client.weaving-order-form');
    }

    public function bom()
    {
        return view('client.internal-weaving');
    }
}
