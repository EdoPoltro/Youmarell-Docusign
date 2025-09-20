<?php

require_once '../vendor/autoload.php';

/**
 * Ottiene un nuovo access token utilizzando il refresh token.
 * Questo metodo viene chiamato solo quando l'access token corrente è scaduto.
 * * @return string L'access token valido.
 */
function getAccessTokenFromRefreshToken() {
    $clientId = '7f56f926-ee8f-4bfb-be72-113f01951b52';
    $clientSecret = 'cd9645a0-d72b-48b9-87c6-f9c3209c8fc2';
    
    if (!file_exists('../storage/refresh_token.txt')) {
        die("❌ Errore: File refresh_token.txt non trovato. Esegui la procedura di autorizzazione iniziale.");
    }
    
    $refreshToken = trim(file_get_contents('../storage/refresh_token.txt'));

    $authServer = 'https://account-d.docusign.com/oauth/token';

    $data = [
        'grant_type' => 'refresh_token',
        'refresh_token' => $refreshToken,
        'client_id' => $clientId,
        'client_secret' => $clientSecret
    ];

    $options = [
        'http' => [
            'header'  => "Content-Type: application/x-www-form-urlencoded",
            'method'  => 'POST',
            'content' => http_build_query($data)
        ]
    ];

    $context  = stream_context_create($options);
    $result = file_get_contents($authServer, false, $context);

    if ($result === FALSE) {
        $error_message = "❌ Errore nel recuperare l'access token dal refresh token.";
        if (isset($http_response_header)) {
            $error_message .= " Codice di stato HTTP: " . $http_response_header[0];
        }
        die($error_message);
    }

    $response = json_decode($result, true);
    
    if (!isset($response['access_token'])) {
        die("❌ Errore: la risposta non contiene un access token valido.");
    }

    file_put_contents('../storage/access_token.json', json_encode([
        'access_token' => $response['access_token'],
        'expires_at' => time() + $response['expires_in']
    ]));

    if (isset($response['refresh_token'])) {
        file_put_contents('../storage/refresh_token.txt', $response['refresh_token']);
    } else {
        die("❌ Errore: la risposta non contiene un nuovo refresh token.");
    }

    return $response['access_token'];
}

/**
 * Controlla se l'access token in cache è valido.
 * Se è scaduto, ne ottiene uno nuovo.
 * * @return string L'access token valido.
 */
function getValidAccessToken() {
    $file = '../storage/access_token.json';

    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true);
        if ($data && isset($data['access_token']) && isset($data['expires_at']) && time() < $data['expires_at'] - 60) {
            return $data['access_token'];
        }
    }

    return getAccessTokenFromRefreshToken();
}