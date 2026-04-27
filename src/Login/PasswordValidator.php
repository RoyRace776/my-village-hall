<?php
namespace MYVH\Login;

class PasswordValidator {
    public function validate(string $password): ?string {
        if (strlen($password) < 9) {
            return 'Password must be at least 9 characters.';
        }

        if (!preg_match('/[A-Z]/', $password)) {
            return 'Password must include at least one uppercase letter.';
        }

        if (!preg_match('/[a-z]/', $password)) {
            return 'Password must include at least one lowercase letter.';
        }

        if (!preg_match('/\d/', $password)) {
            return 'Password must include at least one number.';
        }

        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            return 'Password must include at least one symbol.';
        }

        return null;
    }
}