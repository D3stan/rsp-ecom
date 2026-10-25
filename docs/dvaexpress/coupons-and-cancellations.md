# Coupons and cancellations

Validate shipment coupons and request eligible order cancellations.

[API overview and shared conventions](./README.md) · [Original sanitized HTML export](../vendor/dvaexpress-doc.html)


### `POST https://api.easyparcel.it/checkcoupon/{APIKEY}`

Check whether a discount coupon can be applied to a shipment.

**Request body:** JSON.

**Request fields**

| Field | Type | Example | Required | Description |
|---|---|---|---|---|
| `call` | string | `checkcoupon` | yes | Inserire `checkcoupon` |
| `dettagli.idcustomer` | numeric | `3723` | yes | Inserire l'ID del cliente proprietario del coupon. |
| `dettagli.vettore` | string | `UPS21S` | yes | Inserire il codice del vettore. |
| `dettagli.peso_totale` | string | `8.00` | yes | Inserire il peso complessivo della spedizione. |
| `dettagli.tipo_spedizione` | string | `N` | yes | `N` = nazionale<br>`E` = estero<br>`I` = import |
| `dettagli.cosa_spedire` | string | `M` | yes | `D` = documenti<br>`M` = merce/pacchi<br>`P` = pallets |
| `dettagli.provincia` | string | `RM` | yes | Inserire la sigla della provincia italiana di partenza della spedizione.<br>`N.B.` In caso di import indicare la provincia italiana di destinazione. |
| `dettagli.nazione` | string | `US` | yes | Inserire la sigla della nazione di destinazione della spedizione.<br>`N.B.` In caso di import indicare la nazione di partenza. |
| `dettagli.coupon` | string | `ILOVEUSA21` | yes | Inserire il codice del coupon da applicare alla spedizione. |

**Response shape:** object: `errorcode` (number), `errordetails` (string), `errormessage` (string), `result` (string), `timestamp` (string), `version` (string)


### `POST https://api.easyparcel.it/richiesta-storno/{APIKEY}`

Request cancellation for up to 500 purchased orders whose shipments have not departed; `solo_verifica` supports a dry run.

**Request body:** JSON.

**Request fields**

| Field | Type | Example | Required | Description |
|---|---|---|---|---|
| `ordini` | array di integer | `[1234567, 1234568]` | Si | ID degli ordini di cui richiedere lo storno (il campo `id_ordine` restituito dalla chiamata `order`). Massimo 500 ordini per chiamata; gli ID ripetuti sono considerati una volta sola. |
| `solo_verifica` | boolean | `false` | Opzionale | Con `true` la chiamata verifica soltanto se lo storno è richiedibile e restituisce l'esito di ogni ordine, senza modificare nulla. Con `false` (default) richiede lo storno degli ordini ammessi. |
| `idcustomer` | integer | `15` | Opzionale | Cliente su cui operare (chiavi canale/reseller): vengono considerati solo i suoi ordini. Ammesso solo se il cliente fa parte della rete della APIKEY, altrimenti la chiamata è rifiutata con errore 162. |

**Response shape:** object: `ammessi` (number), `errorcode` (string), `errormessage` (string), `esiti` (array<object>), `result` (string), `richiesti` (number), `rifiutati` (number), `solo_verifica` (boolean), `timestamp` (string), `version` (string)

**Response fields**

| Livello1 | Livello2 | Type | Description |
|---|---|---|---|
| solo_verifica |  | boolean | Riporta la modalità della chiamata. |
| esiti |  | array | Un elemento per ogni ordine richiesto, nello stesso ordine della richiesta. |
|  | id_ordine | integer | ID dell'ordine. |
|  | ammesso | boolean | `true` se l'ordine rispetta le condizioni per lo storno (vedi Note). |
|  | richiesto | boolean | `true` se in questa chiamata lo storno è stato effettivamente richiesto (sempre `false` con `solo_verifica`). |
|  | codice | string | Esito dell'ordine: `ammesso` oppure il motivo del rifiuto (vedi tabella Codici di esito). |
|  | messaggio | string | Descrizione leggibile dell'esito. |
|  | stato | integer | Stato dell'ordine: dopo una richiesta riuscita è `13` (storno richiesto). `null` se l'ordine non è stato trovato. |
|  | ldv | string | Lettera di vettura dell'ordine, se presente. |
|  | tracking | string | Ultimo evento di tracciamento, solo con codice `tracking_presente`. |
| ammessi |  | integer | Numero di ordini ammessi allo storno. |
| rifiutati |  | integer | Numero di ordini rifiutati. |
| richiesti |  | integer | Numero di ordini per cui lo storno è stato richiesto in questa chiamata. |

**Notes:** The API key may alternatively be supplied through the `X-API-KEY` header.
