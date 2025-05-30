<?php
session_start();

$data = json_decode(file_get_contents("php://input"), true);
$idToken = $data['idToken'] ?? null;

if (!$idToken) {
    http_response_code(400);
    echo "Token no recibido";
    exit;
}

// Verificar token con Firebase
$client = new GuzzleHttp\Client();
try {
    $response = $client->get("https://identitytoolkit.googleapis.com/v1/accounts:lookup?key=" . getenv('FIREBASE_API_KEY'), [
        'json' => ['idToken' => $idToken]
    ]);
    $userInfo = json_decode($response->getBody(), true);

    if (!empty($userInfo['users'][0]['localId'])) {
        $_SESSION['firebase_uid'] = $userInfo['users'][0]['localId'];
        $_SESSION['firebase_email'] = $userInfo['users'][0]['email'];
        echo "ok";
    } else {
        http_response_code(401);
        echo "Token inválido";
    }
} catch (Exception $e) {
    http_response_code(401);
    echo "Error al verificar token";
}

