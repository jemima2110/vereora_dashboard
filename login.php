<?php

session_start();

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../connexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Méthode non autorisée.'
    ]);

    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$email = trim($data['identifiant'] ?? '');
$password = $data['password'] ?? '';

if ($email === '' || $password === '') {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Veuillez remplir tous les champs.'
    ]);

    exit;
}

try {
    $sql = "SELECT id_utilisateur, nom, prenom, email,
                   mot_de_passe, role, statut
            FROM utilisateurs
            WHERE email = :email
            LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['email' => $email]);

    $user = $stmt->fetch();

    if (
        !$user ||
        !password_verify($password, $user['mot_de_passe']) ||
        (int) $user['statut'] !== 1
    ) {
        http_response_code(401);

        echo json_encode([
            'success' => false,
            'message' => 'Identifiant ou mot de passe incorrect.'
        ]);

        exit;
    }

    session_regenerate_id(true);

    $_SESSION['user_id'] = $user['id_utilisateur'];
    $_SESSION['user_nom'] = $user['nom'];
    $_SESSION['user_prenom'] = $user['prenom'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];

    echo json_encode([
        'success' => true,
        'message' => 'Connexion réussie.',
        'redirect' => '/Dashboard1/dash.php'
    ]);

} catch (PDOException $e) {
    error_log($e->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Une erreur est survenue. Veuillez réessayer.'
    ]);
}
