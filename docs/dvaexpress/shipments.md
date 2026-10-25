# Shipments

Quote shipments, create standard orders, or use the combined fast-order flow.

[API overview and shared conventions](./README.md) · [Original sanitized HTML export](../vendor/dvaexpress-doc.html)


### `POST https://api.easyparcel.it/quotation/{APIKEY}`

Price a shipment. Pass the returned `codice_offerta` to `order` to purchase the selected quote.

**Request body:** JSON.

**Request fields**

| Field | Type | Example | Required | Description |
|---|---|---|---|---|
| `call` | string | `quotation` | yes | Inserire `quotation` |
| `dettagli.idcustomer` | numeric | `12345` | optional | In questo campo può essere indicato l'id del cliente del proprio network sul cui listino calcolare il preventivo. |
| `dettagli.tipo_spedizione` | string | `N` | yes | `N` = nazionale<br>`E` = estero<br>`I` = import |
| `dettagli.cosa_spedire` | string | `M` | yes | `D` = documenti<br>`M` = merce/pacchi<br>`P` = pallets |
| `dettagli.contenuto` | string | `BISCOTTI` | yes | Inserire la descrizione del contenuto |
| `dettagli.accessori.contrassegno_importo` | decimal | `250.00` | optional | Indicare l'importo del contrassegno<br>Il servizio potrebbe non essere previsto per alcuni vettori |
| `dettagli.accessori.assicurazione_importo` | decimal | `350.00` | optional | Indicare l'importo dell'assicurazione<br>Il servizio potrebbe non essere previsto per alcuni vettori |
| `dettagli.accessori.consegnaalpiano` | string | `S` | optional | `S` = acquistare<br>`N` = non acquistare<br>Il servizio potrebbe non essere previsto per alcuni vettori |
| `dettagli.accessori.consegnasuappuntamento` | string | `S` | optional | `S` = acquistare<br>`N` = non acquistare<br>Il servizio potrebbe non essere previsto per alcuni vettori |
| `dettagli.accessori.priornotice` | string | `S` | optional | `S` = acquistare<br>`N` = non acquistare<br>Il servizio "Prior Notice" è acquistabile solo per spedizioni estere dirette negli USA |
| `colli.peso` | decimal | `3.000` | yes | Peso complessivo di questa serie di colli, in kg |
| `colli.larghezza` | integer | `30` | yes | Larghezza del singolo collo di questa serie di colli (in cm) |
| `colli.profondita` | integer | `20` | yes | Profondità del singolo collo di questa serie di colli (in cm) |
| `colli.altezza` | integer | `15` | yes | Altezza del singolo collo di questa serie di colli (in cm) |
| `colli.nr_colli` | integer | `1` | yes | Numero di colli con stesse dimensioni di questa serie di colli |
| `mittente.cap` | string | `00175` | yes | CAP del mittente |
| `mittente.localita` | string | `ROMA` | yes | Località del mittente |
| `mittente.provincia` | string | `RM` | yes | Provincia del mittente |
| `mittente.nazione` | string | `IT` | yes | Inserire `IT` |
| `destinatario.cap` | string | `20121` | yes | CAP del destinatario |
| `destinatario.localita` | string | `MILANO` | yes | Località del destinatario |
| `destinatario.provincia` | string | `MI` | yes | Provincia del destinatario, se spedizione internazionale inserire `--` |
| `destinatario.country` | string | `IT` | Obbligatorio solo su internazionale verso USA e Canada | Country del destinatario, da valorizzare solo per USA ([link](https://it.wikipedia.org/wiki/Stati_federati_degli_Stati_Uniti_d%27America)) e Canada ([link](https://it.wikipedia.org/wiki/Province_e_territori_del_Canada)) |
| `destinatario.nazione` | string | `IT` | yes | Nazione del destinatario, usare il codice ISO 3166-1 Alpha-2 ([link](https://it.wikipedia.org/wiki/ISO_3166-1_alpha-2)) |

**Response shape:** object: `errorcode` (number), `errormessage` (string), `quotation` (array<object>), `result` (string), `timestamp` (string), `version` (string)


### `POST https://api.easyparcel.it/order/{APIKEY}`

Purchase a shipment from a previous quotation and provide sender, recipient, and optional-service details.

**Request body:** JSON.

**Request fields**

| Field | Type | Example | Required | Description |
|---|---|---|---|---|
| `call` | string | `order` | yes | Inserire `order` |
| `dettagli.codice_offerta` | string | `5dcc4a53b00fc` | yes | Inserire un codice_offerta fra quelli ricevuti in risposta alla precedente chiamata "quotation" |
| `dettagli.note_cliente` | string | `ACQUISTO DI TEST` | optional | Inserire un testo di descrizione dell'acquisto |
| `dettagli.custom` | string | `XYZ123456` | optional | Campo custom a testo libero per eventuale ricerca ordine |
| `dettagli.coupon` | string | `ILOVEUSA21` | optional | Inserire il codice di un eventuale coupon di sconto.<br>`N.B.` Il coupon dovrebbe prima essere verificato con la chiamata `CheckCoupon`. |
| `dettagli.accessori.contrassegno` | string | `S` | optional | `S` = acquistare<br>`N` = non acquistare<br>Il servizio potrebbe non essere previsto per alcuni vettori |
| `dettagli.accessori.contrassegno_modalita` | string | `C` | optional | `C` = contanti<br>`A` = assegno bancario al mittente<br>La modalità accettata è specificata nella quotation e potrebbe non essere prevista per alcuni vettori.<br>Se questo campo non viene passato, viene assunto con C = contanti. |
| `dettagli.accessori.assicurazione` | string | `S` | optional | `S` = acquistare<br>`N` = non acquistare<br>Il servizio potrebbe non essere previsto per alcuni vettori |
| `dettagli.accessori.consegnaalpiano` | string | `S` | optional | `S` = acquistare<br>`N` = non acquistare<br>Il servizio potrebbe non essere previsto per alcuni vettori |
| `dettagli.accessori.consegnasuappuntamento` | string | `S` | optional | `S` = acquistare<br>`N` = non acquistare<br>Il servizio potrebbe non essere previsto per alcuni vettori |
| `dettagli.accessori.trackingavanzato` | string | `S` | optional | `S` = acquistare<br>`N` = non acquistare |
| `dettagli.accessori.priornotice` | string | `S` | optional | `S` = acquistare<br>`N` = non acquistare<br>Il servizio "Prior Notice" è acquistabile solo per spedizioni estere dirette negli USA |
| `mittente.nominativo` | string | `Rossi Mario` | yes | Nominativo del mittente |
| `mittente.indirizzo` | string | `Via del Colosseo, 1` | yes | Indirizzo del mittente |
| `mittente.email` | string | `mario@rossi.it` | yes | Indirizzo email del mittente |
| `mittente.telefono` | string | `0611111111` | optional | Telefono fisso del mittente |
| `mittente.cellulare` | string | `3471111111` | yes | Cellulare del mittente |
| `mittente.contatto` | string | `Rossi Mario` | yes | Contatto del mittente |
| `mittente.codicefiscale` | string | `RSSMRA80A01H501U` | yes | Codice fiscale del mittente |
| `destinatario.nominativo` | string | `Verdi Giuseppe` | yes | Nominativo del destinatario |
| `destinatario.indirizzo` | string | `Piazza Duomo, 1` | yes | Indirizzo del destinatario |
| `destinatario.email` | string | `giuseppe@verdi.it` | yes | Indirizzo email del destinatario |
| `destinatario.telefono` | string | `0222222222` | optional | Telefono fisso del destinatario |
| `destinatario.cellulare` | string | `34722222222` | yes | Cellulare del destinatario |
| `destinatario.contatto` | string | `Verdi Giuseppe` | yes | Contatto del destinatario |
| `destinatario.codicefiscale` | string | `VRDGPP80A01H501U` | optional | Codice fiscale del mittente |
| `ritiro.prenotazione` | string | `S` | optional | `S` = richiesto<br>`N` = non richiesto<br>Il servizio consiste nel ritiro della spedizione presso il mittente. |
| `ritiro.dettagli.ritirodove` | string | `M` | yes | `M` = presso l'indirizzo del Mittente<br>`P` = presso l'indirizzo del Point, indicato nel campo `idcustomer` della chiamata `quotation`. |
| `ritiro.dettagli.disponibile_dal` | date | `2026-10-19` | yes | Data (nel formato AAAA-MM-DD) che identifica da quando la spedizione è disponibile per essere ritirata. |
| `ritiro.dettagli.disponibile_ora` | time | `08:00` | yes | Orario (nel formato HH:MM) che identifica da quando la spedizione è disponibile per essere ritirata. |
| `ritiro.dettagli.ritiro_mattina_dalle` | time | `08:00` | yes | Orario (nel formato HH:MM) che identifica da quando la spedizione è disponibile per essere ritirata. |
| `ritiro.dettagli.ritiro_mattina_alle` | time | `12:00` | yes | Orario (nel formato HH:MM) che identifica da quando la spedizione è disponibile per essere ritirata. |
| `ritiro.dettagli.ritiro_pomeriggio_dalle` | time | `14:00` | yes | Orario (nel formato HH:MM) che identifica da quando la spedizione è disponibile per essere ritirata. |
| `ritiro.dettagli.ritiro_pomeriggio_alle` | time | `18:00` | yes | Orario (nel formato HH:MM) che identifica da quando la spedizione è disponibile per essere ritirata. |
| `dettagli > documenti (documenti doganali PDF)` | — | — | — | — |
| `dettagli.documenti[].tipo_documento` | string | `export_declaration` | Condizionale | Tipo documento: `export_declaration` (dichiarazione di libera esportazione), `proforma` (fattura proforma), `invoice` (fattura commerciale), `extra` (documenti accessori, multipli). Gli allegati confluiscono nei "Documenti Extra" dell'ordine e, per i vettori UPS/DHL, vengono allegati automaticamente alla spedizione (paperless). |
| `dettagli.documenti[].nome_file` | string | `packing_list.pdf` | Opzionale | Nome del file (usato per i documenti di tipo `extra`). |
| `dettagli.documenti[].contenuto_base64` | string | `JVBERi0xLjQ...` | Condizionale | Contenuto del file PDF codificato in base64 (max 15 MB). |
| `destinatario > country (sigla stato per spedizioni US/CA)` | — | — | — | — |
| `destinatario.country` | string | `NY` | Condizionale | Sigla dello stato/provincia per spedizioni verso US e Canada. Obbligatoria per tali destinazioni: in mancanza l'ordine viene rifiutato (errorcode 178). |

**Response shape:** object: `codice_offerta` (string), `custom` (string), `errorcode` (string), `errormessage` (string), `id_ordine` (string), `importo` (string), `ordine` (string), `result` (string), `timestamp` (string), `version` (string)


### `POST https://api.easyparcel.it/order-fast/{APIKEY}`

Create a quote, purchase it, and obtain the waybill in one request.

**Request body:** JSON.

**Request fields**

| Field | Type | Example | Required | Description |
|---|---|---|---|---|
| `call` | string | `order-fast` | Si | Tipo di chiamata. Deve essere `order-fast` |
| `dettagli` | — | — | — | — |
| `dettagli.note_cliente` | string | `TEST-SPEDIZIONE` | Opzionale | Note visibili al cliente sulla spedizione |
| `dettagli.note_contenuto` | string | `Merce varia` | Opzionale | Descrizione del contenuto della spedizione |
| `dettagli.file_ldv` | boolean | `true` | Opzionale | Se `true`, restituisce il PDF della lettera di vettura in base64 nel campo `ldv_base64` |
| `dettagli.idcustomer` | integer | `15` | Opzionale | ID cliente per calcolo su tariffario specifico (non pubblico) |
| `dettagli.vettore` | string | `SDAM` | Si | Codice vettore per la spedizione (es. SDAM, PDB, INPOST, BRT, GLS, ecc.) |
| `dettagli.cosa_spedire` | string | `M` | Si | Tipo di merce: `M` = Merce, `D` = Documenti, `P` = Pallet |
| `dettagli.custom` | string | `CUSTOMTEST` | Opzionale | Riferimento personalizzato del cliente. Viene restituito nella risposta. |
| `dettagli.tipo_spedizione` | string | `N` | Si | Tipo spedizione: `N` = Nazionale, `E` = Estero (export, verso qualsiasi paese estero), `I` = Import (dall'estero verso l'Italia) |
| `dettagli > accessori` | — | — | — | — |
| `dettagli.accessori.contrassegno` | string | `S` | Opzionale | Attiva contrassegno. `S` = Si, `N` = No. Se il servizio non e' disponibile per il vettore, l'ordine viene bloccato (errore 175). |
| `dettagli.accessori.contrassegno_importo` | decimal | `1000` | Condizionale | Importo del contrassegno. Obbligatorio se `contrassegno=S`. Se supera il limite massimo del vettore, l'ordine viene bloccato. |
| `dettagli.accessori.assicurazione` | string | `S` | Opzionale | Attiva assicurazione. `S` = Si, `N` = No. Se il servizio non e' disponibile per il vettore, l'ordine viene bloccato (errore 175). |
| `dettagli.accessori.assicurazione_importo` | decimal | `500` | Condizionale | Importo dell'assicurazione. Obbligatorio se `assicurazione=S`. Se supera il limite massimo del vettore, l'ordine viene bloccato. |
| `dettagli.accessori.consegnaalpiano` | string | `S` | Opzionale | Consegna al piano. `S` = Si, `N` = No. Servizio soggetto a disponibilita' del vettore. |
| `dettagli.accessori.consegnasuappuntamento` | string | `S` | Opzionale | Consegna su appuntamento. `S` = Si, `N` = No. Servizio soggetto a disponibilita' del vettore. |
| `dettagli.accessori.priornotice` | string | `S` | Opzionale | Preavviso telefonico. `S` = Si, `N` = No. Servizio soggetto a disponibilita' del vettore. |
| `dettagli.accessori.portoassegnato` | string | `S` | Opzionale | Porto assegnato (spese a carico del destinatario). `S` = Si, `N` = No. Servizio soggetto a disponibilita' del vettore. |
| `dettagli.accessori.pudo_destinatario` | string | `Z6426` | Condizionale | Codice PUDO destinatario. Per PDB: codice ottenuto dalla chiamata `pudo` (validato su tabella `pdb_pudo`). Per INPOST: codice locker (validato su tabella `inpost_punti`), obbligatorio insieme a `pudo_mittente`. |
| `dettagli.accessori.pudo_mittente` | string | `Z1234` | Condizionale | Codice PUDO mittente. Per PDB: utilizzabile solo con spedizioni reverse. Per INPOST: obbligatorio insieme a `pudo_destinatario`, i due codici devono essere diversi tra loro. |
| `dettagli.accessori.reverse` | string | `S` | Opzionale | Spedizione reverse (reso). `S` = attiva, `N` = non attiva (default). Disponibile per PDB. |
| `dettagli > hscodes (obbligatorio per paesi extra-UE con dogana)` | — | — | — | — |
| `dettagli.hscodes[].hscode` | string | `3004.90.10` | Si | Codice HS (Harmonized System). Formato: 6-10 cifre, con o senza punti. I primi 6 cifre sono validati contro il database internazionale WCO. |
| `dettagli.hscodes[].descrizione` | string | `Medicinale` | Si | Descrizione della merce in linguaggio naturale. |
| `dettagli.hscodes[].quantita` | integer | `2` | Si | Quantita' di articoli. Deve essere maggiore di 0. |
| `dettagli.hscodes[].importo` | decimal | `15.00` | Si | Valore della merce in EUR. |
| `dettagli.hscodes[].peso` | decimal | `0.100` | Si | Peso della merce in kg. |
| `dettagli.hscodes[].motivo_esportazione` | string | `SALE` | Si | Motivo dell'esportazione. Valori comuni: `SALE`, `GIFT`, `SAMPLE`, `RETURN`, `REPAIR`, `PERSONAL_EFFECTS`. |
| `dettagli > documenti (documenti doganali PDF)` | — | — | — | — |
| `dettagli.documenti[].tipo_documento` | string | `export_declaration` | Condizionale | Tipo documento: `export_declaration` (dichiarazione di libera esportazione), `proforma` (fattura proforma), `invoice` (fattura commerciale), `extra` (documenti accessori, multipli). Gli allegati confluiscono nei "Documenti Extra" dell'ordine e, per i vettori UPS/DHL, vengono allegati automaticamente alla spedizione (paperless). |
| `dettagli.documenti[].nome_file` | string | `packing_list.pdf` | Opzionale | Nome del file (usato per i documenti di tipo `extra`). |
| `dettagli.documenti[].contenuto_base64` | string | `JVBERi0xLjQ...` | Condizionale | Contenuto del file PDF codificato in base64 (solo PDF, max 15 MB). |
| `colli` | — | — | — | — |
| `colli[].peso` | decimal | `1.000` | Si | Peso del collo in kg |
| `colli[].larghezza` | integer | `20` | Si | Larghezza del collo in cm |
| `colli[].profondita` | integer | `22` | Si | Profondita' del collo in cm |
| `colli[].altezza` | integer | `24` | Si | Altezza del collo in cm |
| `colli[].nr_colli` | integer | `1` | Si | Numero di colli con queste dimensioni |
| `mittente` | — | — | — | — |
| `mittente.nominativo` | string | `ROSSI MARIO` | Si | Nome/ragione sociale del mittente |
| `mittente.indirizzo` | string | `VIA COLOSSEO, 1` | Si | Indirizzo del mittente |
| `mittente.cap` | string | `00175` | Si | CAP del mittente |
| `mittente.localita` | string | `ROMA` | Si | Citta'/localita' del mittente |
| `mittente.provincia` | string | `RM` | Si | Sigla provincia del mittente |
| `mittente.nazione` | string | `IT` | Si | Codice nazione ISO 3166-1 alpha-2 del mittente |
| `mittente.email` | string | `mario@rossi.it` | Opzionale | Email del mittente |
| `mittente.telefono` | string | `0612345678` | Opzionale | Telefono fisso del mittente |
| `mittente.cellulare` | string | `3471111111` | Opzionale | Cellulare del mittente |
| `mittente.contatto` | string | `ROSSI MARIO` | Opzionale | Persona di contatto del mittente |
| `mittente.codicefiscale` | string | `RSSMRA80A01H501U` | Opzionale | Codice fiscale o P.IVA del mittente |
| `destinatario` | — | — | — | — |
| `destinatario.nominativo` | string | `VERDI GIUSEPPE` | Si | Nome/ragione sociale del destinatario |
| `destinatario.indirizzo` | string | `PIAZZA DUOMO, 1` | Si | Indirizzo del destinatario |
| `destinatario.cap` | string | `20121` | Si | CAP del destinatario |
| `destinatario.localita` | string | `MILANO` | Si | Citta'/localita' del destinatario |
| `destinatario.provincia` | string | `MI` | Si | Sigla provincia del destinatario |
| `destinatario.nazione` | string | `IT` | Si | Codice nazione ISO 3166-1 alpha-2 del destinatario |
| `destinatario.email` | string | `giuseppe@verdi.it` | Opzionale | Email del destinatario |
| `destinatario.telefono` | string | `0287654321` | Opzionale | Telefono fisso del destinatario |
| `destinatario.cellulare` | string | `3472222222` | Opzionale | Cellulare del destinatario |
| `destinatario.contatto` | string | `VERDI GIUSEPPE` | Opzionale | Persona di contatto del destinatario |
| `destinatario.codicefiscale` | string | `` | Opzionale | Codice fiscale o P.IVA del destinatario |
| `destinatario.country` | string | `CA` | Condizionale | Codice stato/provincia per spedizioni verso US/CA (es. stato USA o provincia Canada) |
| `ritiro` | — | — | — | — |
| `ritiro.prenotazione` | string | `S` | Opzionale | Prenotazione ritiro. `S` = Si, `N` = No |
| `ritiro.dettagli.ritirodove` | string | `M` | Condizionale | Dove effettuare il ritiro (obbligatorio se prenotazione=S) |
| `ritiro.dettagli.disponibile_dal` | string | `2025-01-15` | Condizionale | Data disponibilita' ritiro (formato YYYY-MM-DD) |
| `ritiro.dettagli.disponibile_ora` | string | `08:00` | Condizionale | Ora disponibilita' ritiro (formato HH:MM) |
| `ritiro.dettagli.ritiro_mattina_dalle` | string | `08:00` | Condizionale | Orario inizio ritiro mattina |
| `ritiro.dettagli.ritiro_mattina_alle` | string | `12:00` | Condizionale | Orario fine ritiro mattina |
| `ritiro.dettagli.ritiro_pomeriggio_dalle` | string | `14:00` | Condizionale | Orario inizio ritiro pomeriggio |
| `ritiro.dettagli.ritiro_pomeriggio_alle` | string | `18:00` | Condizionale | Orario fine ritiro pomeriggio |

**Response shape:** object: `codice_offerta` (string), `codice_ritiro` (string), `custom` (string), `errorcode` (string), `errormessage` (string), `id_ordine` (number), `importo` (string), `ldv_base64` (string), `lettera_vettura` (string), `ordine` (string), `result` (string), `single_waybills` (array<object>), `timestamp` (string), `url_bordero` (string), `url_ldv` (string), `version` (string)
