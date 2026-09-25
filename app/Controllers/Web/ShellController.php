<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Controllers\BaseController;

final class ShellController extends BaseController
{
    public function shop(): string { return view('shop/index', ['pageTitle' => 'Shop']); }
    public function authentication(): string { return view('auth/index', ['pageTitle' => 'Authentication']); }
    public function admin(): string { return view('admin/index', ['pageTitle' => 'Platform Administration']); }
}
