<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\ApiResponse;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Validator;
use App\Repositories\UserRepository;
use PDOException;

final class AuthController
{
    public function me(): void
    {
        ApiResponse::success(['user' => Auth::user(), 'csrf_token' => Csrf::token()]);
    }

    public function register(): void
    {
        Csrf::verify();
        $data = Request::data();
        $validator = (new Validator($data))
            ->required('full_name', 'Ному насаб')->string('full_name', 3, 120, 'Ному насаб')
            ->required('phone', 'Телефон')->phone('phone')
            ->email('email')
            ->required('password', 'Рамз');
        $password = (string) ($data['password'] ?? '');
        if (strlen($password) < 10 || strlen($password) > 72) {
            ApiResponse::error('validation_failed', 'The submitted data is invalid.', 422, ['password' => 'Парол бояд аз 10 то 72 аломат дошта бошад.']);
        }
        if ($validator->fails()) ApiResponse::error('validation_failed', 'The submitted data is invalid.', 422, $validator->errors());
        $role = strtoupper(trim((string) ($data['account_type'] ?? 'CUSTOMER')));
        if (!in_array($role, ['CUSTOMER', 'OWNER'], true)) {
            ApiResponse::error('validation_failed', 'account_type must be CUSTOMER or OWNER.', 422);
        }

        try {
            $id = (new UserRepository())->createAccount([
                'full_name' => trim((string) $data['full_name']),
                'phone' => trim((string) $data['phone']),
                'email' => trim((string) ($data['email'] ?? '')),
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'role' => $role,
            ]);
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() === '23000') ApiResponse::error('account_exists', 'This phone number or email is already registered.', 409);
            throw $exception;
        }
        session_regenerate_id(true);
        $_SESSION['uid'] = $id;
        ApiResponse::success(['user' => (new UserRepository())->findPublicById($id), 'csrf_token' => Csrf::rotate()], 201);
    }

    public function login(): void
    {
        Csrf::verify();
        $data = Request::data();
        $identity = trim((string) ($data['identity'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        if ($identity === '' || $password === '') ApiResponse::error('validation_failed', 'Enter your phone or email and password.', 422);
        $user = (new UserRepository())->findByIdentity($identity);
        if (!$user || !password_verify($password, $user['password_hash']) || $user['status'] !== 'active') {
            ApiResponse::error('invalid_credentials', 'The supplied credentials are not valid.', 401);
        }
        session_regenerate_id(true);
        $_SESSION['uid'] = (int) $user['id'];
        ApiResponse::success(['user' => (new UserRepository())->findPublicById((int) $user['id']), 'csrf_token' => Csrf::rotate()]);
    }

    public function logout(): void
    {
        Auth::requireUser();
        Csrf::verify();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) $params['secure'], (bool) $params['httponly']);
        }
        session_destroy();
        ApiResponse::success(['logged_out' => true]);
    }
}
