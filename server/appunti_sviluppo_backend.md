# Integrazione DocuSign per Youmarell.com

---

## Panoramica del Flusso

L'obiettivo di questa integrazione è permettere agli utenti di Youmarell.com di firmare elettronicamente un accordo tramite **DocuSign** **prima** di procedere al pagamento dell'abbonamento.

Il processo si articola come segue:

1.  **Selezione Abbonamento:** L'utente seleziona una tipologia di abbonamento. Il **tier selezionato** viene salvato nel `localStorage` del browser client.
2.  **Preparazione Firma:** L'applicazione reindirizza temporaneamente l'utente a una pagina di controllo che verifica se ha gia generato un link di firma e ha completato il processo, se l'ha solo generato in passato (in questo caso probabilmente è scaduto) o se non ha mai generato un link.
3.  **Richiesta DocuSign:** Il file `envelope.php` effettua una chiamata alle **API DocuSign** per:
    * Ottenere un **URL di reindirizzamento** per la sessione di firma.
    * Inviare un'**email di notifica** per la firma all'utente.
4.  **Reindirizzamento e Firma:** Il backend restituisce l'URL di DocuSign al client, che reindirizza l'utente per completare la firma elettronica.
5.  **Pagamento:** Una volta completata la firma, l'utente può procedere al pagamento tramite **Stripe**.

---

## Dettagli di Implementazione DocuSign

Per l'integrazione con DocuSign, è importante distinguere tra l'account "normale" e l'account "developer". Sebbene sia possibile creare template con l'account normale, si consiglia di operare sempre tramite la **dashboard developer** per la configurazione delle API, data la potenziale confusione tra le due interfacce.

Le nostre credenziali di accesso per DocuSign sono:

* **Account ID:** `40485541`
* **Email:** `alisa@youmarell.com`
* **Password:** `Timelapse1-`

---

### 1. Creazione del Template DocuSign

Il primo passo consiste nel creare il **template di firma** all'interno della dashboard DocuSign (preferibilmente tramite l'accesso developer).

* Accedi alla dashboard DocuSign e naviga nella sezione **"Templates"**.
* Crea un nuovo template.
* Definisci due ruoli per i firmatari: **"Cliente"** e **"Youmarell"**.
    * Per il ruolo "Cliente", ometti nome ed email, poiché questi saranno forniti **dinamicamente** dal backend.
    * Per il ruolo "Youmarell", nome ed email verranno inseriti automaticamente dal backend.
* **Salva l'ID del Template:** Questo ID è cruciale per le chiamate API. Lo trovi come parte finale dell'URL quando visualizzi il template.

    *ID Template:* `b9e385a3-59d7-4a8b-b594-a1320127cdf9`

---

### 2. Configurazione dell'App DocuSign (Developer Dashboard)

Accedendo alla dashboard DocuSign come sviluppatore, nella sezione **"My Apps and Keys"**, puoi creare e configurare l'applicazione associata a DocuSign.

Identificativi essenziali da salvare:

* **Integration Key (Client ID):** `7f56f926-ee8f-4bfb-be72-113f01951b52`
* **Client Secret (Chiave Segreta):** `cd9645a0-d72b-48b9-87c6-f9c3209c8fc2`

---

### 3. Gestione del Token di Accesso

Per autenticare le chiamate alle API DocuSign, è necessario un **Access Token**, che ha una validità di **8 ore**. Esistono due metodi per ottenerlo:

#### Metodo 1: Recupero Manuale (per Test)

Questo metodo è utile per scopi di test o per la prima acquisizione del Refresh Token.

1.  **Configura URI di Reindirizzamento:** Nella configurazione della tua integrazione tramite la dashboard DocuSign, imposta l'URI di reindirizzamento su `https://www.youmarell.com`.
2.  **Genera Codice di Autorizzazione:** Incolla il seguente URL nel browser:
    ```
    [https://account-d.docusign.com/oauth/auth?response_type=code&scope=signature&client_id=7f56f926-ee8f-4bfb-be72-113f01951b52&redirect_uri=https://www.youmarell.com](https://account-d.docusign.com/oauth/auth?response_type=code&scope=signature&client_id=7f56f926-ee8f-4bfb-be72-113f01951b52&redirect_uri=https://www.youmarell.com)
    ```
    Sarai reindirizzato al tuo URI con un `code` (authorization token) aggiunto come parametro URL.
3.  **Richiedi Access Token (tramite cURL):** Esegui la seguente chiamata cURL, sostituendo `YOUR_AUTHORIZATION_CODE` con il codice ottenuto dal reindirizzamento:
    ```bash
    curl -X POST [https://account-d.docusign.com/oauth/token](https://account-d.docusign.com/oauth/token) \
     -H "Content-Type: application/x-www-form-urlencoded" \
     -d "grant_type=authorization_code" \
     -d "code=YOUR_AUTHORIZATION_CODE" \
     -d "client_id=7f56f926-ee8f-4bfb-be72-113f01951b52" \
     -d "client_secret=cd9645a0-d72b-48b9-87c6-f9c3209c8fc2" \
     -d "redirect_uri=[https://www.youmarell.com](https://www.youmarell.com)"
    ```
    Questa chiamata restituirà un pacchetto JSON contenente l'**Access Token** corrente e un **Refresh Token**.

#### Metodo 2: Recupero Automatico (tramite Refresh Token)

Una volta ottenuto un **Refresh Token** (tramite il primo metodo), è possibile utilizzarlo per ottenere Access Token sempre aggiornati senza intervento manuale.

* Ho implementato un file PHP chiamato `token.php`.
* Questo file contiene una funzione `getValidAccessToken()` che si occupa di utilizzare il Refresh Token per ottenere e restituire un Access Token valido e aggiornato.
* Il Refresh Token è salvato in un file di testo denominato `refresh_token.txt`.

---

### 4. Implementazione del Backend (`envelope.php`)

Il file `envelope.php` è il cuore dell'integrazione backend. Per il suo funzionamento, è necessario installare la libreria **DocuSign eSign Client** tramite Composer:

```bash
composer require docusign/esign-client
```
E richiamare le seguenti librerie / file

```bash 
    require 'vendor/autoload.php';
    require 'token.php'; // Contiene la funzione getValidAccessToken()

    use DocuSign\eSign\Client\ApiClient;
    use DocuSign\eSign\Api\EnvelopesApi;
    use DocuSign\eSign\Model\EnvelopeDefinition;
    use DocuSign\eSign\Model\TemplateRole;
    use DocuSign\eSign\Model\RecipientViewRequest;
```
Questo file viene chiamato quando il cliente genera per la prima volta un link di firma elettronica e restituisce nel pacchetto json proprio il link di cui ha bisogno.

Durante lo sviluppo di questo file il problema principale era che richiede PHP 8 o superiore per far funzionare le librerie ed è stato necessario prendere alcune precauzioni per farlo funzionare sul nostro server che globalmente dispone di PHP 7.4.

### 5. Implementazione del Backend (`regenerate_envelope.php`)

Questo file viene chiamato quando nel database è presente già un record che identifica la sessione di firma ma il link precedente e già stato usato o è scaduto e la procedura non è stata terminata. il file richiede la mail e il nome del firmatario e l'id dell'envelope creata dal file `envelope.php` in precedenza. Questo file è essenziale perchè molto spesso i link a docusign valgono solo una volta o scadono dopo che è passato molto tempo.

### 6. Implementazione del Backend (`check_docusign_signature.php`)

E' il file che si occupa di restituire al frontend il risultato della verifica dello stato della firma, i possibili stati sono 3:

- `Status 'signed' && id_envelope != null` : l'utente può procedere al pagamento --> response.verify = true && response.id_envelope = id.
- `Status 'sent' && id_envelope !=null` : significa che il link è già stato mandato una volta e deve essere rigenerato --> response.verify = false && response.id_envelope = id.
- `nessun record nel database che sia più recente di 6 mesi e che abbia le caratteristiche richieste` : viene chiamato il file envelope.php per la prima volta --> response.verify = false && response.id_envelope = null.

### 7. Implementazione del Backend (`webhook.php`)

Per comunicare con il backend della nostra applicazione docusign utilizza un sistema di callback chiamato docusign connect, per configurarlo si deve accedere alla dashboard di docusign deveopers e configurare un custom connect. una volta inserito l'endpoint del nostro file webhook.php ogni volta che avviene un evento selezionato parte una call verso il nostro file.

Gli eventi che ho gestito sono `envelope-sent` ed `envelope-completed`, il primo crea sul DB il record con lo status sent il secodno modifica lo status in 'signed' del record con l'id_envelope corrente.
Sottolineo che il secondo evento parte quando tutte le parti hanno firmato.

Ho aggiunto dei campi custom alla prima creazione dell'envelope quali il tier e l'id del nostro utente cosi da poterli usare anche qua per caricare i dati nel database.
E' sorto un problema quando ho cercato per la prima volta di recuperarli perche sia chatGPT che Gemini non erano aggiornati e mi fornivano un percorso sbagliato per recuperare i custom fields. (data -> envelopeSummary -> customFields -> textCustomFields ecc, l'errore stava in envelopeSummary che mancava).