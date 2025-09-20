<?php

require '../config/database_connection.php';

$docusignEvent = 'unknown';
$envelopeId = null;
$localTierValue = null;
$localUserIdValue = null;
$responseMessage = 'Webhook elaborato con successo.';
$httpStatusCode = 200;

if ($conn->connect_error) {
    $httpStatusCode = 500;
    $responseMessage = 'Errore di connessione al database: ' . $conn->connect_error;
    error_log("DocuSign Webhook DB Connection Error: " . $conn->connect_error);
    echo json_encode(['status' => 'error', 'message' => $responseMessage]);
    exit;
}

$inputData = file_get_contents('php://input');
$data = json_decode($inputData, true);

if (json_last_error() !== JSON_ERROR_NONE || !isset($data['event'])) {
    $httpStatusCode = 400;
    $responseMessage = 'Payload JSON non valido o manca il campo "event".';
    error_log("DocuSign Webhook JSON Error: " . json_last_error_msg() . " | Input: " . $inputData);
    echo json_encode(['status' => 'error', 'message' => $responseMessage]);
    exit;
}

$docusignEvent = $data['event'];

try {
    switch ($docusignEvent) {
        case 'envelope-sent':
           if (isset($data['data']['envelopeSummary']['customFields']['textCustomFields']) && 
                is_array($data['data']['envelopeSummary']['customFields']['textCustomFields'])) {

                $envelopeId = $data['data']['envelopeSummary']['envelopeId'] ?? null;
                $textCustomFieldsArray = $data['data']['envelopeSummary']['customFields']['textCustomFields'];
                
                foreach ($textCustomFieldsArray as $customField) {
                    if (isset($customField['name'])) {
                        $cleanFieldName = strtolower(trim($customField['name']));
                        
                        if ($cleanFieldName === 'tier') {
                            $localTierValue = $customField['value'] ?? null;
                        } elseif ($cleanFieldName === 'id_utente') {
                            $localUserIdValue = (int)($customField['value'] ?? 0);
                        }
                    }
                }
            }
            
            if ($localTierValue === null || $localUserIdValue === null) {
                throw new Exception("Estrazione campi personalizzati fallita. Tier: " . ($localTierValue ?? 'NULL') . ", UserId: " . ($localUserIdValue ?? 'NULL') . ". Verificare i nomi dei campi in DocuSign o la presenza nel payload.");
            }

            $statusToSet = 'sent'; 
            
            $stmt = $conn->prepare("INSERT INTO docusign (status, tier, id_utente, id_envelope) VALUES (?, ?, ?, ?)");
            
            if (!$stmt) {
                throw new Exception("Impossibile preparare la query INSERT: " . $conn->error);
            }

            $stmt->bind_param("ssis", $statusToSet, $localTierValue, $localUserIdValue, $envelopeId);
            
            if (!$stmt->execute()) {
                throw new Exception("Errore durante l'esecuzione della query INSERT: " . $stmt->error);
            }
            
            $responseMessage = "Record inserito con successo nel database. Stato: '{$statusToSet}', Tier: '{$localTierValue}', ID Utente: '{$localUserIdValue}', ID Envelope: '{$envelopeId}'.";
            break;

        case 'envelope-completed':
            if (isset($data['data']['envelopeSummary']['envelopeId'])) {
                $envelopeId = $data['data']['envelopeSummary']['envelopeId'];
                $envelopeStatus = $data['data']['envelopeSummary']['status'] ?? 'unknown';

                if ($envelopeStatus === 'completed' || $envelopeStatus === 'signed') {
                    $stmt = $conn->prepare("UPDATE docusign SET status = ? WHERE id_envelope = ?");
                    if (!$stmt) {
                        throw new Exception("Impossibile preparare la query UPDATE: " . $conn->error);
                    }
                    $statusToSet = 'signed';
                    $stmt->bind_param("ss", $statusToSet, $envelopeId); 
                    if (!$stmt->execute()) {
                        throw new Exception("Errore durante l'esecuzione della query UPDATE: " . $stmt->error);
                    }
                    $responseMessage = "Stato dell'envelope aggiornato a '{$statusToSet}' nel database per ID Envelope: {$envelopeId}.";
                } else {
                    $responseMessage = "Evento 'envelope-completed' ricevuto, ma lo stato interno ('{$envelopeStatus}') non era 'completed' o 'signed'. Nessun aggiornamento al database.";
                }
            } else {
                $responseMessage = "Evento 'envelope-completed' ricevuto, ma l'ID dell'envelope mancava. Nessun aggiornamento al database.";
            }
            break;

        default:
            $responseMessage = "Evento DocuSign '{$docusignEvent}' ricevuto ma non gestito specificamente. Nessuna azione sul database.";
            break;
    }
} catch (Exception $e) {
    $httpStatusCode = 500;
    $responseMessage = 'Errore durante l\'operazione sul database: ' . $e->getMessage();
    error_log("DocuSign Webhook Error: " . $e->getMessage() . " | Event: " . $docusignEvent . " | Envelope ID: " . ($envelopeId ?? 'N/A') . " | Payload: " . $inputData);
} finally {
    if (isset($conn) && $conn) {
        $conn->close();
    }
}

http_response_code($httpStatusCode);
echo json_encode([
    'status' => ($httpStatusCode === 200 ? 'successo' : 'errore'),
    'message' => $responseMessage,
    'docusignEvent' => $docusignEvent,
    'envelopeId' => $envelopeId,
]);



