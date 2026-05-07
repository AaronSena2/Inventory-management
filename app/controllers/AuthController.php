<?php
/**
 * AuthController – handles login/logout UI.
 */
class AuthController extends Controller
{
    public function loginForm(): void
    {
        if (Auth::isLoggedIn()) {
            $this->redirect('/dashboard');
        }
        $this->render('auth/login', [
            'pageTitle' => 'Login – ' . APP_NAME,
            'error'     => null,
        ]);
    }

    public function login(): void
    {
        if (Auth::isLoggedIn()) {
            $this->redirect('/dashboard');
        }

        $csrf     = $_POST['csrf_token'] ?? '';
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!Auth::validateCsrfToken($csrf)) {
            $this->render('auth/login', [
                'pageTitle' => 'Login – ' . APP_NAME,
                'error'     => 'Invalid CSRF token. Please try again.',
            ]);
            return;
        }

        if ($username === '' || $password === '') {
            $this->render('auth/login', [
                'pageTitle' => 'Login – ' . APP_NAME,
                'error'     => 'Username and password are required.',
            ]);
            return;
        }

        if (Auth::login($username, $password)) {
            $this->redirect('/dashboard');
        } else {
            $this->render('auth/login', [
                'pageTitle' => 'Login – ' . APP_NAME,
                'error'     => 'Invalid username or password.',
            ]);
        }
    }

    public function logout(): void
    {
        Auth::logout();
        $this->redirect('/login');
    }
}
