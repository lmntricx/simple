<?php

namespace app\Controllers;

use app\Models\User;

class AuthController
{
    public function loginForm()
    {
        include 'app/Views/auth/header.php';
        include 'app/Views/auth/login.php';
        include 'app/Views/auth/footer.php';
    }

    public function registerForm()
    {
        include 'app/Views/auth/register.php';
    }

    public function login()
    {
        // Get input data
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        // Validate credentials
        $user = User::findByEmail($email);

        if ($user && password_verify($password, $user['password_hash'])) {
            // Success: Set session or cookie
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['is_logged_in'] = true;

            header('Location: /dashboard');
        } else {
            // Error: Redirect back with error
            header('Location: /login?error=Invalid credentials');
        }
    }

    public function register()
    {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        // Validation (basic example)
        if (filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($password) >= 8) {
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            User::create(['email' => $email, 'password' => $hashedPassword]);
            $user = User::findByEmail($email);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['is_logged_in'] = true;

            header('Location: /dashboard');
        } else {
            header('Location: /register?error=Invalid data');
        }
    }
    public function logout(): void
    {
        // Destroy the session to log out
        session_start();
        session_unset();  // Clear session variables
        session_destroy(); // Destroy the session

        // Redirect to login page
        header('Location: /login');
        exit();
    }
}
