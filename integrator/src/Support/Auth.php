<?php

declare(strict_types=1);

namespace Integrator\Support;

/**
 * Session based authentication for the integrator dashboard.
 *
 * The dashboard can trigger real pushes into Neo Feeder, so it is never public:
 * `bin/console user:create` seeds the first operator.
 */
final class Auth
{
    private ?array $user = null;

    public function __construct(private readonly Database $db)
    {
    }

    public function attempt(string $email, string $password): bool
    {
        $user = $this->db->first('SELECT * FROM users WHERE email = :email', ['email' => mb_strtolower(trim($email))]);

        if ($user === null || ! password_verify($password, (string) $user['password_hash'])) {
            return false;
        }

        $_SESSION['user_id'] = (int) $user['id'];
        session_regenerate_id(true);

        $this->db->update('users', ['last_login_at' => $this->db->now()], ['id' => (int) $user['id']]);

        return true;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function user(): ?array
    {
        if ($this->user !== null) {
            return $this->user;
        }

        $id = $_SESSION['user_id'] ?? null;

        if (! $id) {
            return null;
        }

        $user = $this->db->first('SELECT id, name, email, last_login_at FROM users WHERE id = :id', ['id' => (int) $id]);

        return $this->user = $user;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function logout(): void
    {
        unset($_SESSION['user_id']);
        $this->user = null;
    }

    public function createUser(string $name, string $email, string $password): int
    {
        return $this->db->insert('users', [
            'name' => $name,
            'email' => mb_strtolower(trim($email)),
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'created_at' => $this->db->now(),
            'updated_at' => $this->db->now(),
        ]);
    }

    public function changePassword(int $userId, string $password): void
    {
        $this->db->update('users', [
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'updated_at' => $this->db->now(),
        ], ['id' => $userId]);
    }

    public function userCount(): int
    {
        return (int) $this->db->scalar('SELECT COUNT(*) FROM users');
    }
}
