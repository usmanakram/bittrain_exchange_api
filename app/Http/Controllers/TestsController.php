<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TestsController extends Controller
{
    public function firstTest(Request $request)
    {
    	echo 'reached inside controller';
    }
}
