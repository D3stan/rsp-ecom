# Errors

The error catalogue published with the EasyParcel API reference.

[API overview and shared conventions](./README.md) · [Original sanitized HTML export](../vendor/dvaexpress-doc.html)


## Error catalogue

Questa sezione contiene l'elenco e il significato di tutti gli eventuali errori che le chiamate API al sistema restituiscono.

**HTTP status:** HTTP `200` identifies a positive response; HTTP `400` identifies a request error.

**Error payload:** JSON normally includes `result`, `errorcode`, `errormessage`, and, when supplied, `errordetails` and `timestamp`.

| ErrorCode | ErrorMessage | Description |
|---|---|---|
| 0 | NESSUN ERRORE | L'operazione è stata eseguita correttamente. |
| 101 | APIKEY - Non valida | Il codice APIKEY utilizzato nella chiamata non risulta una chiave valida. |
| 102 | APIKEY - Chiave scaduta | Il codice APIKEY utilizzato nella chiamata è scaduto e necessita del rinnovo per poter continuare a funzionare. |
| 103 | APIKEY - Servizio non disponibile per questo indirizzo IP | La chiamata proviene da un indirizzo IP non autorizzato per questo codice APIKEY. |
| 104 | APIKEY - Limite giornaliero chiamate superato | La chiamata ha superato il limite giornaliero per questo codice APIKEY. |
| 105 | JSON - Manca il file | Manca il file JSON che dev'essere passato alla funzione via POST. |
| 110 | JSON - Errore sui campi | Il file JSON passato non rispetta i requisiti per la chiamata. Nel campo `errordetails` sono dettagliati i valori errati o mancanti. |
| 120 | USER Credito esaurito | Il credito residuo non consente di perfezionare l'acquisto. |
| 130 | ORDER Già memorizzato | Il codice offerta è già stato elaborato e quindi l'acquisto non è duplicabile. Acquisto non perfezionato. |
| 131 | ORDER Errore in fase di memorizzazione | La memorizzazione dell'acquisto non è andata a buon fine. Acquisto non perfezionato. Contattare l'assistenza. |
| 132 | ORDER Errore in fase di memorizzazione (2) | La memorizzazione dell'acquisto non è andata a buon fine perché è stato selezionato un vettore che al momento non consente un acquisto via API. Acquisto non perfezionato. |
| 140 | NEWCUSTOMER Funzione non disponibile | Funzione non disponibile per questo account. |
| 141 | NEWCUSTOMER Errore in fase di memorizzazione | La memorizzazione del nuovo cliente non è andata a buon fine. Contattare l'assistenza. |
| 150 | ADDFUND Funzione non disponibile | Funzione non disponibile per questo account. |
| 151 | ADDFUND Credito non sufficiente | L'account master non ha credito sufficiente per ricaricare il cliente. |
| 152 | ADDFUND Cliente non corretto | Il cliente indicato non esiste o non fa parte del network. |
| 160 | BALANCE Funzione non disponibile | Funzione non disponibile per questo account. |
| 161 | BALANCE Cliente non corretto | Il cliente indicato non esiste o non fa parte del network. |
