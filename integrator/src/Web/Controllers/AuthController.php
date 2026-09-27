<?php

declare(strict_types=1);

namespace Integrator\Web\Controllers;

use Integrator\App;
use Integrator\Support\View;
use Integrator\Web\Response;

/**
 * Session login for the dashboard.
 */
final class AuthController
{
    public function __construct(private readonly App $app)
    {
    }

    public function showLogin(): Response
    {
        if ($this->app->auth()->check()) {
            return Response::redirect('/');
        }

        return Response::html($this->app->view()->page('auth/login', [
            'title' => 'Masuk',
            'hasUsers' => $this->app->auth()->userCount() > 0,
            'email' => '',
        ]));
    }

    public function login(): Response
    {
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($email === '' || $password === '') {
            View::flash('error', 'Email dan kata sandi wajib diisi.');

            return Response::redirect('/login');
        }

        if (! $this->app->auth()->attempt($email, $password)) {
            View::flash('error', 'Email atau kata sandi salah.');

            return Response::redirect('/login');
        }

        return Response::redirect('/');
    }

    public function logout(): Response
    {
        $this->app->auth()->logout();
        View::flash('success', 'Anda telah keluar.');

        return Response::redirect('/login');
    }
}
