# Locations and customs

Search TARIC/HS codes and carrier pickup/drop-off points.

[API overview and shared conventions](./README.md) · [Original sanitized HTML export](../dvaexpress-doc.html)


### `POST https://api.easyparcel.it/taric-search/{APIKEY}`

Find customs TARIC/HS codes from a natural-language product description for international shipments.

**Also available:** `GET https://api.easyparcel.it/taric-search/{APIKEY}?q={query}&lang={language}&limit={limit}`.

**Request:** the POST variant accepts a JSON body; the GET variant accepts equivalent query parameters.

**Request fields**

| Field | Type | Example | Required | Description |
|---|---|---|---|---|
| `call` | string | `taric-search` | Si | Tipo di chiamata. Deve essere `taric-search` (necessario solo se si usa il body JSON). |
| `q` | string | `biscotti al cioccolato` | Si | Descrizione del prodotto in linguaggio naturale (italiano o inglese). |
| `lang` | string | `it` | Opzionale | Lingua della ricerca: `it` (default) oppure `en`. |
| `limit` | integer | `5` | Opzionale | Numero massimo di risultati (da 1 a 10, default 5). |

**Response shape:** object: `call` (string), `query` (string), `result` (string), `results` (array<object>), `timestamp` (string)

**Response fields**

| Campo | Type | Example | Description |
|---|---|---|---|
| taric_code | string | `1905310000` | Codice TARIC a 10 cifre. Da usare in `order-fast` come `dettagli.hscodes[].hscode` (vengono validate le prime 6 cifre). |
| description_it | string | `Biscotti dolci` | Descrizione ufficiale in italiano. |
| description_en | string | `Sweet biscuits` | Descrizione ufficiale in inglese. |
| confidence | decimal | `0.92` | Grado di corrispondenza stimato (0.0 - 1.0). I risultati sono ordinati per confidence decrescente. |

**Notes:** The API key may alternatively be supplied through the `X-API-KEY` header.


### `POST https://api.easyparcel.it/pudo/{APIKEY}`

Find PDB (Poste Delivery Business) and INPOST pickup/drop-off points by province, postal code/radius, or coordinates.

**Request body:** JSON.

**Request fields**

| Field | Type | PDB | INPOST | Required | Description |
|---|---|---|---|---|---|
| `vettore` | string | ✓ | ✓ | yes | `PDB` o `INPOST` |
| `cap` | string | ✓ | ✓ | no | CAP. Se specificato con `radius`, viene usato come centro per la ricerca geografica. Altrimenti filtra per CAP esatto. |
| `provincia` | string | ✓ | ✓ | no | Sigla provincia (es. `MI`, `RM`) |
| `citta` | string | ✓ | ✓ | no | Nome città (ricerca parziale) |
| `cerca` | string | ✓ | ✓ | no | Ricerca libera su codice, nome, indirizzo |
| `radius` | number | ✓ | ✓ | no | Raggio di ricerca in km (default: 10). Richiede `cap` oppure `lat`/`lon`. |
| `lat` | number | ✓ | ✓ | no | Latitudine del punto di riferimento (alternativa al CAP per ricerca geografica) |
| `lon` | number | ✓ | ✓ | no | Longitudine del punto di riferimento |
| `tipologia` | string | ✓ | ✓ | no | PDB: `FMP` (Ufficio Postale), `APT` (Punto Poste), `RTZ` (Poste Locker)<br>INPOST: `parcel_locker` |
| `status` | string | — | ✓ | no | Solo INPOST. Default: `Operating`. Filtra per stato del punto. |
| `location_type` | string | — | ✓ | no | Solo INPOST. `Indoor` o `Outdoor`. |
| `locale` | string | ✓ | ✓ | no | `it` (default) o `en`. Traduce i nomi dei campi nella risposta. |
| `limit` | number | ✓ | ✓ | no | Numero massimo di risultati (default: 100, max: 1000) |

**Response shape:** object: `count` (number), `punti` (array<object>), `result` (string), `vettore` (string)
