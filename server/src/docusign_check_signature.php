<?php

require "../config/database_connection.php";

$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['id_utente'], $data['tier'])) {

    $sql = "SELECT id_envelope, date, status FROM docusign WHERE id_utente = ? AND tier = ? ORDER BY date DESC LIMIT 1";

    if ($stmt = $conn->prepare($sql)) {

        $stmt->bind_param("ss", $data['id_utente'], $data['tier']);

        if ($stmt->execute()) {

            $stmt->store_result();

            if ($stmt->num_rows > 0) {

                $stmt->bind_result($id_envelope, $date, $status);
                $stmt->fetch();

                if ($status === 'signed') {
                    echo json_encode([
                        "success" => true,
                        "verify" => true,
                        "id_envelope" => $id_envelope,
                        "date" => $date,
                        "message" => "Record trovato e firmato."
                    ]);
                } else {
                    echo json_encode([
                        "success" => true,
                        "verify" => false,
                        "id_envelope" => $id_envelope,
                        "date" => $date,
                        "message" => "Record trovato, ma non firmato."
                    ]);
                }

            } else {
                echo json_encode([
                    "success" => false,
                    "verify" => false,
                    "id_envelope" => null,
                    "date" => null,
                    "message" => "Nessun record trovato."
                ]);
            }

        } else {
            echo json_encode([
                "error" => "Errore nell'esecuzione della query: " . $stmt->error
            ]);
        }

        $stmt->close();
    } else {
        echo json_encode([
            "error" => "Errore nella preparazione della query: " . $conn->error
        ]);
    }

} else {
    echo json_encode([
        "error" => "Dati incompleti o non validi (richiesti id_utente e tier)"
    ]);
}
