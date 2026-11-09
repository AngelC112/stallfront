<?php

namespace App\Http\Controllers;

use App\Support\LegalPages;
use Illuminate\View\View;

class LegalController extends Controller
{
    public function privacy(): View
    {
        return $this->show('privacy');
    }

    public function cookies(): View
    {
        return $this->show('cookies');
    }

    public function terms(): View
    {
        return $this->show('terms');
    }

    private function show(string $page): View
    {
        return view('legal.show', LegalPages::render($page));
    }
}
