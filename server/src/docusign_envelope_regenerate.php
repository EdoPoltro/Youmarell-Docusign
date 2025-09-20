<?php

require '../vendor/autoload.php';
require_once 'token.php'; 

use DocuSign\eSign\Client\ApiClient;
use DocuSign\eSign\Api\EnvelopesApi;
use DocuSign\eSign\Model\RecipientViewRequest;

header("Access-Control-Allow-Origin: *");
header('Content-Type: application/json');
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit;
}

class RegenerateEnvelopeLinkService
{
    /**
     * Genera un nuovo URL di firma per un envelope e un firmatario esistenti.
     *
     * @param string $accountId L'ID del tuo account DocuSign.
     * @param string $envelopeId L'ID dell'envelope esistente per cui generare il link.
     * @param string $signerEmail L'email del firmatario (deve corrispondere a quella nell'envelope).
     * @param string $signerName Il nome del firmatario (deve corrispondere a quello nell'envelope).
     * @param string $clientUserId Il ClientUserId del firmatario ('1' per il primo firmatario, come impostato).
     * @param string $returnUrl L'URL a cui DocuSign reindirizzerà dopo la firma.
     * @param ApiClient $clientService Il client API DocuSign configurato.
     * @return string L'URL di firma generato.
     * @throws Exception Se la generazione del link fallisce.
     */
    public static function getSigningUrl(
        string $accountId,
        string $envelopeId,
        string $signerEmail,
        string $signerName,
        string $clientUserId,
        string $returnUrl,
        ApiClient $clientService
    ): string {
        $recipientViewRequest = new RecipientViewRequest([
            'return_url' => $returnUrl,
            'authentication_method' => 'email', 
            'email' => $signerEmail,
            'user_name' => $signerName,
            'client_user_id' => $clientUserId,
        ]);

        $envelopeApi = new EnvelopesApi($clientService);
        $viewUrl = $envelopeApi->createRecipientView($accountId, $envelopeId, $recipientViewRequest);

        if ($viewUrl && $viewUrl->getUrl()) {
            return $viewUrl->getUrl();
        } else {
            throw new Exception("DocuSign non ha restituito un URL valido per la vista del destinatario.");
        }
    }
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['id_envelope'], $data['signerEmail'], $data['signerName'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Dati mancanti o invalidi (richiesti envelopeId, signerEmail, signerName).']);
    exit;
}

$envelopeId = $data['id_envelope'];
$signerEmail = $data['signerEmail']; 
$signerName = $data['signerName'];   
$clientUserId = '1'; 
$returnUrl = 'https://www.youmarell.com/user/abbonamento/contratto'; 
$accountId = '40485541'; 

try {
    $accessToken = getValidAccessToken();
    $apiClient = new ApiClient();
    $apiClient->getConfig()->setHost('https://demo.docusign.net/restapi') // Usa 'https://www.docusign.net/restapi' per produzione
             ->addDefaultHeader("Authorization", "Bearer " . $accessToken);

    $signingUrl = RegenerateEnvelopeLinkService::getSigningUrl(
        $accountId,
        $envelopeId,
        $signerEmail,
        $signerName,
        $clientUserId,
        $returnUrl,
        $apiClient
    );

    http_response_code(200);
    echo json_encode([
        "status" => "successo",
        "message" => "Link di firma rigenerato con successo.",
        "url" => $signingUrl
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'errore',
        'message' => 'Errore durante la rigenerazione del link di firma: ' . $e->getMessage()
    ]);
    error_log("DocuSign Link Regeneration Error for Envelope ID {$envelopeId}: " . $e->getMessage() . " | Input: " . file_get_contents('php://input'));
}

?>