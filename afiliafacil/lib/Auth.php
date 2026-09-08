<?php
session_start();

class Auth
{
    private static string $usersFile;

    public static function init(): void
    {
        self::$usersFile = Config::getDataDir() . '/users.json';
        if (!file_exists(self::$usersFile)) {
            self::createDefaultAdmin();
        }
    }

    private static function createDefaultAdmin(): void
    {
        $users = [
            [
                'id' => 1,
                'name' => 'Administrador',
                'email' => 'admin@afiliasfacil.com',
                'password' => password_hash('admin123', PASSWORD_DEFAULT),
                'plan' => 'premium',
                'created_at' => date('Y-m-d H:i:s'),
            ]
        ];
        file_put_contents(self::$usersFile, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public static function attempt(string $email, string $password): bool
    {
        $users = self::getUsers();
        foreach ($users as $user) {
            if ($user['email'] === $email && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_plan'] = $user['plan'];
                return true;
            }
        }
        return false;
    }

    public static function logout(): void
    {
        session_destroy();
        header('Location: /');
        exit;
    }

    public static function check(): bool
    {
        return isset($_SESSION['user_id']);
    }

    public static function requireAuth(): void
    {
        if (!self::check()) {
            header('Location: /');
            exit;
        }
    }

    public static function user(): array
    {
        return [
            'id' => $_SESSION['user_id'] ?? 0,
            'name' => $_SESSION['user_name'] ?? '',
            'email' => $_SESSION['user_email'] ?? '',
            'plan' => $_SESSION['user_plan'] ?? 'free',
        ];
    }

    private static function getUsers(): array
    {
        if (!file_exists(self::$usersFile)) return [];
        return json_decode(file_get_contents(self::$usersFile), true) ?? [];
    }
}

Auth::init();
