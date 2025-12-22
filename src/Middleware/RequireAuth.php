<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Auth\Auth;
use App\Http\Response;

final class RequireAuth
{
    public static function handle(): void
    {
        if (!Auth::check()) {
            Response::redirect('/login');
        }
    }
}