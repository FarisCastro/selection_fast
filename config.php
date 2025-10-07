<?php
// Configuration de la base de données
define('DB_HOST', 'localhost');
define('DB_NAME', 'selection_fast');
define('DB_USER', 'root');
define('DB_PASS', '');

// Connexion à la base de données
function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // Vérifier la connexion
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    // Définir l'encodage
    $conn->set_charset("utf8");
    
    return $conn;
}

// Fonction de sécurisation des données
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Validation d'email
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Validation de numéro de téléphone (format international)
function isValidPhone($phone) {
    return preg_match('/^\+[0-9]{1,3}[0-9]{4,14}$/', $phone);
}
?>