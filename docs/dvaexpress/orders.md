# Orders and tracking

Read individual orders, list orders, and obtain tracking information.

[API overview and shared conventions](./README.md) · [Original sanitized HTML export](../dvaexpress-doc.html)


### `POST https://api.easyparcel.it/getorder/{APIKEY}`

Read the status, carrier, shipment, and document details for one purchased order.

**Request body:** JSON.

**Request fields**

| Field | Type | Example | Required | Description |
|---|---|---|---|---|
| `call` | string | `getorder` | yes | Inserire `getorder` |
| `dettagli.codice_offerta` | string | `5dcc4a53b00fc` | optional | Inserire un codice_offerta fra quelli ricevuti in risposta ad una precedente chiamata "order".<br>`N.B.` Se non inserito, diventa obbligatorio il campo custom. |
| `dettagli.custom` | string | `XYZ123456` | optional | Inserire un custom fra quelli indicati in una precedente chiamata "order".<br>`N.B.` Se non inserito, diventa obbligatorio il campo codice_offerta. |

**Response shape:** object: `dettagli` (array<object>), `errorcode` (number), `errormessage` (string), `order` (object), `result` (string), `timestamp` (string), `version` (string)


### `POST https://api.easyparcel.it/listorder/{APIKEY}`

List orders with filters and pagination; the source documents a default page size of 10 and a maximum of 100.

**Request body:** JSON.

**Request fields**

| Field | Type | Example | Required | Description |
|---|---|---|---|---|
| `call` | string | `listorder` | yes | Inserire `listorder` |
| `dettagli.data_inizio` | string | `2024-01-01` | optional | Inserire una data di inizio periodo nel formato AAAA-MM-GG.<br>`N.B.` Se non inserita, viene considerata la data odierna. |
| `dettagli.data_fine` | string | `2024-01-15` | optional | Inserire una data di fine periodo nel formato AAAA-MM-GG.<br>`N.B.` Se non inserita, viene considerata la data odierna. |
| `dettagli.idcustomer` | numeric | `12345` | optional | Inserire l'ID di un utente sotto la propria rete.<br>`N.B.` Se non inserito, viene mostrata la lista di tutti gli ordini. |
| `dettagli.paginazione` | numeric | `25` | optional | Inserire la dimensione della paginazione ossia il numero di ordini che verranno restituiti dalla chiamata.<br>`N.B.` Se non inserito, viene mostrata la lista dei primi 10 ordini; la massima paginazione è di 100 ordini. |
| `dettagli.pagina` | numeric | `2` | optional | Inserire il numero della pagina da visualizzare.<br>`N.B.` Se non inserito, viene mostrata la prima pagina della lista degli ordini. |

**Response shape:** object: `errorcode` (number), `errormessage` (string), `nrordini` (number), `orders` (array<object>), `pagina` (number), `paginazione` (number), `result` (string), `timestamp` (string)


### `POST https://api.easyparcel.it/tracking/{APIKEY}`

Read shipment tracking by order ID or waybill number.

**Request body:** JSON.

**Request fields**

| Field | Type | Example | Required | Description |
|---|---|---|---|---|
| `call` | string | `tracking` | yes | Inserire `tracking` |
| `dettagli.codice_offerta` | string | `5dcc4a53b00fc` | optional | Inserire un codice_offerta fra quelli ricevuti in risposta ad una precedente chiamata "order".<br>`N.B.` E' obbligatorio indicare un codice_offerta, un custom oppure una ldv. |
| `dettagli.custom` | string | `XYZ123456` | optional | Inserire un custom fra quelli indicati in una precedente chiamata "order".<br>`N.B.` E' obbligatorio indicare un codice_offerta, un custom oppure una ldv. |
| `dettagli.ldv` | string | `288016E049120` | optional | Inserire una ldv da ricercare.<br>`N.B.` E' obbligatorio indicare un codice_offerta, un custom oppure una ldv. |

**Response shape:** object: `dettagli` (array<object>), `errorcode` (number), `errormessage` (string), `result` (string), `timestamp` (string), `tracking` (object)
