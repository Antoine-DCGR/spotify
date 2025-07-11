<?php
// app/Models/SpotifyModel.php

class SpotifyModel
{
    /** @var \PDO */
    private $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Insère ou met à jour les tokens OAuth pour un user donné.
     */
    public function upsertToken(int $userId, string $accessToken, string $refreshToken, string $expiresAt): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO oauth_tokens (user_id, access_token, refresh_token, expires_at)
            VALUES (:uid, :at, :rt, :exp)
            ON DUPLICATE KEY UPDATE
              access_token  = :at,
              refresh_token = :rt,
              expires_at    = :exp
        ");
        $stmt->execute([
            ':uid' => $userId,
            ':at'  => $accessToken,
            ':rt'  => $refreshToken,
            ':exp' => $expiresAt,
        ]);
    }

    /**
     * Récupère les tokens OAuth pour un user donné.
     */
    public function getTokenByUserId(int $userId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT access_token, refresh_token, expires_at
            FROM oauth_tokens
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
