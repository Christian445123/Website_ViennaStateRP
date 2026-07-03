<?php
declare(strict_types=1);
// ============================================================
//  ViennaStateRP – Datenbankverbindung (PDO Singleton)
//  Läuft auf localhost → Unix-Socket, kein Netzwerkverkehr
//  Schutz durch: Prepared Statements, stricte Error-Modi
// ============================================================


class DB {
    private static ?PDO $instance = null;

    /**
     * Gibt die PDO-Instanz zurück (Singleton).
     * Verbindung wird lazy erstellt.
     */
    public static function get(): PDO {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
            );

            $options = [
                // Fehler immer als Exception werfen
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                // Ergebnisse als assoziative Arrays
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // Kein Emulate-Prepares → echte Prepared Statements (SQL-Injection-Schutz)
                PDO::ATTR_EMULATE_PREPARES   => false,
                // Persistente Verbindung auf Localhost für Performance
                PDO::ATTR_PERSISTENT         => true,
                // Strict Mode – keine impliziten Typ-Konvertierungen
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, sql_mode='STRICT_ALL_TABLES'",
            ];

            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                // Kein Stack-Trace nach außen geben (Sicherheit)
                error_log('[DB] Verbindung fehlgeschlagen: ' . $e->getMessage());
                http_response_code(503);
                die(json_encode(['error' => 'Datenbankfehler. Bitte später erneut versuchen.']));
            }
        }

        return self::$instance;
    }

    // --- Hilfsfunktionen ---

    /** SELECT – gibt alle Zeilen zurück */
    public static function fetchAll(string $sql, array $params = []): array {
        $stmt = self::get()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** SELECT – gibt eine einzelne Zeile zurück */
    public static function fetchOne(string $sql, array $params = []): ?array {
        $stmt = self::get()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** INSERT / UPDATE / DELETE – gibt Anzahl betroffener Zeilen zurück */
    public static function execute(string $sql, array $params = []): int {
        $stmt = self::get()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /** Gibt die letzte INSERT-ID zurück */
    public static function lastInsertId(): string {
        return self::get()->lastInsertId();
    }
}
