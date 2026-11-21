<?php
/**
 * Model akun: masuk dengan username atau email. Peran per organisasi ada di organization_members.
 */
class User_model {

    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    /** @return array|false the active user row when the password matches */
    public function validate_credentials($username, $password) {
        if (!$this->db->isConnected()) return false;
        $this->db->query("SELECT id, username, password, nama_lengkap, email, auth_version, email_verified_at FROM users WHERE (username = :u OR email = :u) AND status = 'active' LIMIT 1");
        $this->db->bind(':u', trim((string)$username));
        $user = $this->db->single();
        if (!$user) {
            // Same bcrypt work as a real check, so the response time does not reveal whether the account exists.
            password_hash((string)$password, PASSWORD_DEFAULT);
            return false;
        }
        if (!Security::verify_password($password, $user['password'])) return false;

        $this->db->query('UPDATE users SET last_login = NOW() WHERE id = :id');
        $this->db->bind(':id', $user['id']);
        $this->db->execute();
        unset($user['password']);
        return $user;
    }
}
