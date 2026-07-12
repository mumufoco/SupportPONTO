<?php

namespace App\Support;

final class InitialAdminPolicy
{
    public const MIN_PASSWORD_LENGTH = 12;

    private const PASSWORD_PATTERN_UPPER = '/[A-Z]/';
    private const PASSWORD_PATTERN_LOWER = '/[a-z]/';
    private const PASSWORD_PATTERN_NUMBER = '/[0-9]/';
    private const PASSWORD_PATTERN_SPECIAL = '/[^a-zA-Z0-9]/';
    private const PASSWORD_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%^&*()-_=+';

    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public static function validateBootstrapPassword(string $password): array
    {
        $errors = [];

        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            $errors[] = 'Senha deve ter no minimo 12 caracteres';
        }

        if (! preg_match(self::PASSWORD_PATTERN_UPPER, $password)) {
            $errors[] = 'Senha deve ter pelo menos uma letra maiuscula';
        }

        if (! preg_match(self::PASSWORD_PATTERN_LOWER, $password)) {
            $errors[] = 'Senha deve ter pelo menos uma letra minuscula';
        }

        if (! preg_match(self::PASSWORD_PATTERN_NUMBER, $password)) {
            $errors[] = 'Senha deve ter pelo menos um numero';
        }

        if (! preg_match(self::PASSWORD_PATTERN_SPECIAL, $password)) {
            $errors[] = 'Senha deve ter pelo menos um caractere especial';
        }

        return $errors;
    }

    public static function generateBootstrapPassword(int $length = 20): string
    {
        $password = '';
        $maxIndex = strlen(self::PASSWORD_ALPHABET) - 1;

        for ($i = 0; $i < $length; $i++) {
            $password .= self::PASSWORD_ALPHABET[random_int(0, $maxIndex)];
        }

        return $password;
    }

    public static function hashBootstrapPassword(string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID);
    }

    public static function initialPasswordState(): array
    {
        return [
            'must_change_password' => true,
            'password_changed_at' => null,
        ];
    }

    public static function formatCpf(string $cpf): string
    {
        $digits = preg_replace('/\D/', '', $cpf);

        if (strlen($digits) !== 11) {
            return $cpf;
        }

        return substr($digits, 0, 3) . '.' . substr($digits, 3, 3) . '.' . substr($digits, 6, 3) . '-' . substr($digits, 9, 2);
    }
}
