<?php
require '../vendor/autoload.php';
require_once 'docuisgn_token.php'; 

use DocuSign\eSign\Client\ApiClient;
use DocuSign\eSign\Api\EnvelopesApi;
use DocuSign\eSign\Model\EnvelopeDefinition;
use DocuSign\eSign\Model\TemplateRole;
use DocuSign\eSign\Model\RecipientViewRequest;
use DocuSign\eSign\Model\TextCustomField; 
use DocuSign\eSign\Model\CustomFields; 

header("Access-Control-Allow-Origin: *");
header('Content-Type: application/json');
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit;
}

class ApplyBrandToTemplateService
{
    /**
     * Crea l'envelope utilizzando il template e restituisce l'URL di firma.
     *
     * @param array $args
     * @param ApiClient $clientService
     * @return array
     */
    public static function applyBrandToTemplate(array $args, $clientService): array
    {

        $envelope_definition = ApplyBrandToTemplateService::makeEnvelope($args);
        
        $envelopeApi = new EnvelopesApi($clientService);
        $createdEnvelope = $envelopeApi->createEnvelope($args['account_id'], $envelope_definition);

        $recipientViewRequestSigner1 = new RecipientViewRequest();
        $recipientViewRequestSigner1->setReturnUrl($args['return_url']);
        $recipientViewRequestSigner1->setAuthenticationMethod('email');
        $recipientViewRequestSigner1->setEmail($args['envelope_args']['signer1_email']);
        $recipientViewRequestSigner1->setUserName($args['envelope_args']['signer1_name']);
        $recipientViewRequestSigner1->setRecipientId('1');
        $recipientViewRequestSigner1->setClientUserId('1');

        $viewUrlSigner1 = $envelopeApi->createRecipientView($args['account_id'], $createdEnvelope->getEnvelopeId(), $recipientViewRequestSigner1);

        return [
            'envelope_id' => $createdEnvelope->getEnvelopeId(),
            'signer_url' => $viewUrlSigner1->getUrl(),
        ];
    }

    /**
     * Crea la definizione dell'envelope (ruoli, template, custom fields, etc.)
     *
     * @param array $args
     * @return EnvelopeDefinition
     */
    public static function makeEnvelope(array $args): EnvelopeDefinition
    {

        $signer1 = new TemplateRole();
        $signer1->setName($args['envelope_args']['signer1_name']);
        $signer1->setEmail($args['envelope_args']['signer1_email']);
        $signer1->setRoleName('Cliente'); 
        $signer1->setClientUserId('1');

        $textCustomFieldTier = new TextCustomField([
            'name' => 'tier',
            'value' => $args['envelope_args']['tier'],
            'required' => 'false',
            'show' => 'true'
        ]);

        $textCustomFieldUserId = new TextCustomField([
            'name' => 'id_utente',
            'value' => (string)$args['envelope_args']['id_utente'],
            'required' => 'false',
            'show' => 'true'
        ]);

        $allTextCustomFields = [$textCustomFieldTier, $textCustomFieldUserId];
        
        $customFields = new CustomFields([
            'text_custom_fields' => $allTextCustomFields
        ]);

        return new EnvelopeDefinition([
            'template_id' => $args['envelope_args']['template_id'],
            'template_roles' => [$signer1],
            'status' => "sent",
            'custom_fields' => $customFields
        ]);
    }
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['email'], $data['nome'], $data['tier'], $data['id_utente'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Dati mancanti o invalidi (richiesti email, nome, tier, id_utente).']);
    exit;
}

$nome = $data['nome'];
$email = $data['email'];
$tier = $data['tier']; 
$id_utente = (int)$data['id_utente'];

$args = [
    'account_id' => '40485541', 
    'envelope_args' => [
        'signer1_name' => $nome, 
        'signer1_email' => $email,
        'template_id' => 'b9e385a3-59d7-4a8b-b594-a1320127cdf9',
        'tier' => $tier,
        'id_utente' => $id_utente,
    ],
    'return_url' => 'https://www.youmarell.com/user/abbonamento/contratto', 
];

$access_token = getValidAccessToken();

$apiClient = new ApiClient();
$apiClient->getConfig()->setHost('https://demo.docusign.net/restapi')
    ->addDefaultHeader("Authorization", "Bearer " . "$access_token"); 

$clientService = $apiClient;

try {
    $response = ApplyBrandToTemplateService::applyBrandToTemplate($args, $clientService);

    echo json_encode([
        "status" => "success",
        "url" => $response['signer_url']
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to create envelope: ' . $e->getMessage()]);
    error_log("DocuSign Envelope Creation Error: " . $e->getMessage());
}