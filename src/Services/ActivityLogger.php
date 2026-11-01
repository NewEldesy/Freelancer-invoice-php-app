<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Database;

final class ActivityLogger
{
    /**
     * Enregistre une action pour l'utilisateur authentifié courant.
     *
     * @param string $action  Catégorie en notation pointée, ex : 'invoice.create'
     * @param string $label   Description lisible, ex : 'Création facture #20250705-1'
     */
    public static function log(string $action, string $label): void
    {
        $userId   = $_SESSION['user_id']              ?? null;
        $username = $_SESSION['auth_user']['username'] ?? 'système';
        $role     = $_SESSION['user_role']             ?? '';
        // Utiliser uniquement REMOTE_ADDR ; X-Forwarded-For est contrôlable par le client et peut être falsifié
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        try {
            $pdo  = Database::connection();
            $stmt = $pdo->prepare("
                INSERT INTO activity_logs (user_id, username, role, action, label, ip)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$userId, $username, $role, $action, $label, $ip]);
        } catch (\Exception) {
            // Ne jamais faire planter l'app pour un log raté
        }
    }

    /**
     * Récupère les journaux avec des filtres optionnels. Retourne les lignes du plus récent au plus ancien.
     *
     * @param array{from?:string,to?:string,user_id?:int,action?:string} $filters
     */
    public static function fetch(array $filters = [], int $limit = 500): array
    {
        $pdo    = Database::connection();
        $where  = [];
        $params = [];

        if (!empty($filters['from'])) {
            $where[]  = "date(created_at) >= date(?)";
            $params[] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $where[]  = "date(created_at) <= date(?)";
            $params[] = $filters['to'];
        }
        if (!empty($filters['user_id'])) {
            $where[]  = "user_id = ?";
            $params[] = (int) $filters['user_id'];
        }
        if (!empty($filters['action'])) {
            $where[]  = "action LIKE ?";
            $params[] = $filters['action'] . '%';
        }

        $sql  = "SELECT * FROM activity_logs";
        if ($where) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        $sql .= " ORDER BY created_at DESC LIMIT " . (int) $limit;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
