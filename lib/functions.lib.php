<?php
// ========================================================================
// Globale Variablen und Konfigurationen
// ========================================================================

/** 
 * Datenbank-Zugangsdaten in folgendem Format ausserhalb des Webroots gespeichert
 * $GLOBALS['db_host'] = '';
 * $GLOBALS['db_name'] = '';
 * $GLOBALS['db_user'] = '';
 * $GLOBALS['db_pass'] = '';
 */
include ../../workout-config.php

// Konfiguriere Session-Optionen für mehr Sicherheit (empfohlen)
ini_set('session.use_strict_mode', 1); // Verhindert, dass leere Session-IDs zugelassen werden
ini_set('session.cookie_httponly', 1); // Session-Cookies sind nur per HTTP erreichbar (nicht per JavaScript)
ini_set('session.use_cookies', 1);    // Nutzt Cookies zur Session-ID-Speicherung
ini_set('session.cookie_secure', 1);  // Sende Cookies nur über HTTPS (falls zutreffend)
ini_set('session.cookie_samesite', 'Lax'); // Schützt vor CSRF-Angriffen

// ========================================================================
// Kernfunktionen: Datenbankzugriff & Session-Management
// ========================================================================

/**
 * Startet oder setzt die bestehende Session fort.
 * Sollte ganz am Anfang jedes Skripts aufgerufen werden, das Sessions benötigt.
 */
function start_session() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Stellt eine sichere Verbindung zur Datenbank her (MariaDB/MySQL).
 * Verwendet mysqli und bereitet sich auf Prepared Statements vor.
 *
 * @return mysqli|false Die mysqli-Verbindung oder false im Fehlerfall.
 */
function db_connect() {
    static $connection = null; // Static, um die Verbindung über mehrere Aufrufe hinweg zu erhalten

    if ($connection === null) {
        $host = $GLOBALS['db_host'];
        $db   = $GLOBALS['db_name'];
        $user = $GLOBALS['db_user'];
        $pass = $GLOBALS['db_pass'];

        // mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); // Optional: Fehler als Exceptions werfen
        $connection = mysqli_connect($host, $user, $pass, $db);

        if (!$connection) {
            error_log("Datenbankverbindungsfehler: " . mysqli_connect_error());
            return false;
        }

        // Setze den Zeichensatz auf UTF-8 für korrekte Zeichenbehandlung
        if (!mysqli_set_charset($connection, "utf8mb4")) {
            error_log("Fehler beim Setzen des Zeichensatzes: " . mysqli_error($connection));
        }
    }
    return $connection;
}

/**
 * Führt eine Prepared Statement Abfrage aus.
 * Nützlich für SELECT, INSERT, UPDATE, DELETE.
 *
 * @param string $sql Die SQL-Abfrage mit Platzhaltern (?).
 * @param array  $params Ein Array mit den Werten für die Platzhalter.
 * @param string $types Ein String, der den Datentyp jedes Parameters angibt ('s' für String, 'i' für Integer, 'd' für Double, 'b' für Blob).
 * @return mysqli_stmt|false Das vorbereitete Statement oder false bei Fehler.
 */
function db_execute_prepared_statement($sql, $params = [], $types = '') {
    $connection = db_connect();
    if (!$connection) {
        return false; // Verbindung konnte nicht hergestellt werden
    }

    $stmt = mysqli_prepare($connection, $sql);
    if ($stmt === false) {
        error_log("SQL Prep Fehler: " . mysqli_error($connection) . " SQL: " . $sql);
        return false;
    }

    // Wenn Parameter vorhanden sind, binde sie
    if (!empty($params)) {
        $bind_params = [];
        $bind_params[] = &$types; // Erste Referenz ist der Typ-String
        for ($i = 0; $i < count($params); $i++) {
            $bind_params[] = &$params[$i]; // Referenzen auf die Daten selbst
        }
        // mysqli_stmt_bind_param benötigt variable Argumente
        if (!call_user_func_array('mysqli_stmt_bind_param', $bind_params)) {
             error_log("SQL Bind Fehler: " . mysqli_error($connection));
             return false;
        }
    }

    if (!mysqli_stmt_execute($stmt)) {
        error_log("SQL Execute Fehler: " . mysqli_error($connection));
        return false;
    }

    return $stmt;
}

/**
 * Führt eine SELECT-Abfrage aus und gibt die Ergebnisse als assoziatives Array zurück.
 *
 * @param string $sql Die SQL-Abfrage mit Platzhaltern (?).
 * @param array  $params Ein Array mit den Werten für die Platzhalter.
 * @param string $types Ein String, der den Datentyp jedes Parameters angibt.
 * @return array|false Ein Array von assoziativen Arrays oder false bei Fehler.
 */
function db_fetch_all($sql, $params = [], $types = '') {
    $stmt = db_execute_prepared_statement($sql, $params, $types);
    if ($stmt === false) {
        return false;
    }

    $result_set = mysqli_stmt_get_result($stmt);
    if ($result_set === false) {
        error_log("Fehler beim Abrufen des Ergebnis-Sets.");
        return false;
    }

    $rows = [];
    while ($row = mysqli_fetch_assoc($result_set)) {
        $rows[] = $row;
    }

    mysqli_free_result($result_set);
    mysqli_stmt_close($stmt);
    return $rows;
}

/**
 * Führt eine Abfrage aus (INSERT, UPDATE, DELETE) und gibt die Anzahl der betroffenen Zeilen zurück.
 *
 * @param string $sql Die SQL-Abfrage mit Platzhaltern (?).
 * @param array  $params Ein Array mit den Werten für die Platzhalter.
 * @param string $types Ein String, der den Datentyp jedes Parameters angibt.
 * @return int|false Die Anzahl der betroffenen Zeilen oder false bei Fehler.
 */
function db_execute_query($sql, $params = [], $types = '') {
    $stmt = db_execute_prepared_statement($sql, $params, $types);
    if ($stmt === false) {
        return false;
    }

    $affected_rows = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    return $affected_rows;
}

/**
 * Gibt die letzte eingefügte ID zurück.
 *
 * @return int|false Die ID des zuletzt eingefügten Datensatzes oder false bei Fehler.
 */
function db_get_last_insert_id() {
    $connection = db_connect();
    if (!$connection) {
        return false;
    }
    return mysqli_insert_id($connection);
}

/**
 * Registriert einen Benutzer in der Datenbank.
 *
 * @param string $username
 * @param string $email
 * @param string $password
 * @return int|false Die User-ID bei Erfolg oder false bei Fehler.
 */
function register_user($username, $email, $password) {
    $hashed_password = password_hash($password, PASSWORD_DEFAULT); // Sicheres Hashing
    if ($hashed_password === false) {
        error_log("Fehler beim Hashing des Passworts.");
        return false;
    }

    // Prüfen, ob Benutzername oder E-Mail bereits existieren
    $existing_user = db_fetch_all("SELECT user_id FROM gym__Users WHERE username = ? OR email = ?", [$username, $email], 'ss');
    if ($existing_user !== false && !empty($existing_user)) {
        return false; // Benutzer existiert bereits
    }

    $sql = "INSERT INTO gym__Users (username, email, password_hash) VALUES (?, ?, ?)";
    $params = [$username, $email, $hashed_password];
    $types = 'sss'; // String, String, String

    $result = db_execute_query($sql, $params, $types);

    if ($result !== false && $result > 0) {
        return db_get_last_insert_id(); // Rückgabe der neuen User-ID
    } else {
        return false;
    }
}


/**
 * Überprüft die Anmeldedaten eines Benutzers.
 *
 * @param string $username Der eingegebene Benutzername.
 * @param string $password Das eingegebene Passwort.
 * @return array|false Ein Array mit Benutzerdaten (user_id, username) bei Erfolg, sonst false.
 */
function login_user($username, $password) {
    $sql = "SELECT user_id, username, password_hash FROM gym__Users WHERE username = ?";
    $user_data = db_fetch_all($sql, [$username], 's'); // 's' für String

    if ($user_data === false || empty($user_data)) {
        return false; // Benutzer nicht gefunden
    }

    $user = $user_data[0]; // Da username eindeutig sein sollte, gibt es nur einen Eintrag

    // Passwort überprüfen
    if (password_verify($password, $user['password_hash'])) {
        // Anmeldung erfolgreich
        return [
            'user_id' => $user['user_id'],
            'username' => $user['username']
        ];
    } else {
        return false; // Falsches Passwort
    }
}

/**
 * Prüft, ob ein Benutzer aktuell eingeloggt ist.
 * Stellt sicher, dass die Session gestartet wurde.
 *
 * @return bool True, wenn ein Benutzer eingeloggt ist, sonst false.
 */
function is_user_logged_in() {
    start_session(); // Stellt sicher, dass die Session aktiv ist
    return isset($_SESSION['user_id']);
}

/**
 * Holt die ID des aktuell eingelogten Benutzers.
 *
 * @return int|false Die User-ID oder false, wenn niemand eingeloggt ist.
 */
function get_current_user_id() {
    if (is_user_logged_in()) {
        return $_SESSION['user_id'];
    }
    return false;
}

/**
 * Holt den Benutzernamen des aktuell eingeloggten Benutzers.
 *
 * @return string|false Der Benutzername oder false, wenn niemand eingeloggt ist.
 */
function get_current_username() {
    if (is_user_logged_in()) {
        return $_SESSION['username'];
    }
    return false;
}

/**
 * Meldet den aktuellen Benutzer ab.
 */
function logout_user() {
    start_session(); // Stellt sicher, dass die Session aktiv ist
    // Entferne alle Session-Variablen
    $_SESSION = array();

    // Lösche das Session-Cookie, falls es existiert
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    // Zerstöre die Session selbst
    session_destroy();
}

?>
