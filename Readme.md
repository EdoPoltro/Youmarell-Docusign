## 📝 Integrazione di Docusign su Youmarell.com

## 📖 Introduzione al progetto

Il progetto prevede lo sviluppo di un’API sul nostro server, finalizzata a comunicare con DocuSign e a ricevere aggiornamenti relativi ai flussi di firma digitale.
L’API dovrà inoltre interagire con l’applicazione frontend, realizzata in Angular, tramite un servizio dedicato che espone funzioni asincrone restituendo Observable.

Questa integrazione si è resa necessaria per adeguare l’applicazione alle esigenze degli avvocati, prima del lancio online del sito.

In particolare, una volta che l’utente seleziona il **tier** dell’abbonamento, viene reindirizzato a un componente dedicato per avviare il processo di firma digitale. Al termine del flusso, l’utente ha la possibilità di completare l’abbonamento tramite l’integrazione con Stripe.

L’API deve anche essere in grado di riconoscere lo stato del flusso di ciascun utente e gestirlo in maniera appropriata in base alla fase in cui si trova.

## 🗂️ Struttura del progetto

Il progetto è strutturato in due parti principali: frontend e backend.
Il frontend è sviluppato in **Angular**; per scopi dimostrativi è stato incluso solo un estratto del codice in **TypeScript** e **HTML**, con esempi di costrutti Angular come ***ngIf**.

Il backend è realizzato in **PHP 8**, con l’uso di Composer per gestire le librerie necessarie, tra cui quelle di DocuSign. Per la persistenza dei dati viene utilizzato un database relazionale MySQL, mentre l’applicazione gira sul nostro server tramite un web server **Apache2**.

## 💻 Backend

La maggior parte del lavoro di implementazione è stata svolta nella parte di backend. Nella cartella config sono presenti file accessori per la configurazione, come **database_connection.php**, che gestisce la connessione al database, e **info.php**, che permette di ottenere informazioni sul PHP in esecuzione qualora fosse necessario.

Quando un utente avvia la procedura di firma per la prima volta, viene richiamato il file **docusign_envelope.php**, che crea la procedura e restituisce il link per reindirizzare l’utente alla firma. Contemporaneamente, **docusign_webhook.php** riceve l’evento che segnala che l’envelope è nello stato “sent” e crea il record corrispondente nel database.

Se l’utente interrompe la procedura e desidera riprenderla, viene richiamato **docusign_envelope_regenerate.php**, che aggiorna l’envelope e restituisce un nuovo link per completare la firma. Quando la firma viene completata, **docusign_webhook.php** riceve l’evento che indica che l’envelope è nello stato “signed” e che l’utente è pronto per procedere con l’abbonamento.

Il file **docusign_token.php** viene utilizzato per ottenere, ogni volta, il file access_token.txt, necessario per autenticare le chiamate all’API di DocuSign, e contemporaneamente aggiorna in automatico il refresh_token.json. La prima volta questi valori devono essere ottenuti manualmente tramite la procedura descritta nella documentazione di DocuSign.

## 🌐 Frontend

Nel frontend ho realizzato un servizio dedicato contenente funzioni asincrone, il cui scopo è comunicare con l’API presente sul server. Di seguito alcuni esempi:

``` 
loadEnvelope(email: string, nome: string, tier: string, idUtente: number) {
    const body = { email, nome, tier, id_utente: idUtente};
    return this.http.post(this.appInfo.apiAddress + 'docusign/envelope.php', body, {
      headers: { 'Content-Type': 'application/json' }
    });
}
```
```
regenerateEnvelope(email: string, nome: string, idEnvelop: string){
    const body = {id_envelope: idEnvelop, signerEmail: email, signerName: nome};
    return this.http.post(this.appInfo.apiAddress + 'docusign/regenerate_envelope.php', body, {
      headers: { 'Content-Type': 'application/json' }
    });
}
```
```
checkDocusignSignature(idUtente: number, tier: string): Observable<any>{ 
    const body = {id_utente: idUtente, tier};
    return this.http.post(this.appInfo.apiAddress + "check_docusign_signature.php", body, {
      headers: { 'Content-Type': 'application/json' }
    });
}
```

Il codice principale del componente è contenuto in **controller.ts**, che rappresenta il file **TypeScript** incaricato della gestione degli stati sul frontend. Il file **index.html** definisce invece la struttura della pagina e la parte di visualizzazione in **HTML**.

## ✅ Considerazioni finali

In generale, ho riscontrato che la documentazione disponibile era scarsa, quindi gran parte del lavoro ha comportato una lunga fase di debug e risoluzione dei problemi man mano che si procedeva con l’implementazione.

Per migliorare ulteriormente l’applicazione, sarebbe utile creare un file dedicato per salvare tutti i dati relativi a DocuSign, in modo da averli centralizzati. Questo file potrebbe essere un semplice file **.env** da non includere nel repository Git, proteggendolo tramite **.gitignore**.

