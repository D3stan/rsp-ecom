# Waybills

Retrieve shipping documents for purchased orders.

[API overview and shared conventions](./README.md) · [Original sanitized HTML export](../dvaexpress-doc.html)


### `POST https://api.easyparcel.it/getwaybill/{APIKEY}`

Retrieve a purchased order's waybill, optionally as Base64 and split into one document per package.

**Request body:** JSON.

**Request fields**

| Field | Type | Value | Required | Description |
|---|---|---|---|---|
| `call` | string | `getwaybill` | yes | Inserire "getwaybill" |
| `details.order_id` | numeric | `1234567` | yes | In questo campo dev'essere indicato l'ID dell'ordine per il quale richiedere la lettera di vettura (waybill). |
| `details.waybill_base64` | string | `N` | optional | `Y` = restituisce il contenuto del file della waybill codificato in base64<br>`N` = non restituisce il contenuto del file della waybill<br>`N.B.`Di default se non passato, questo valore viene assunto come N. |
| `details.single_waybills` | string | `N` | optional | `Y` = restituisce dentro "single_waybills" il contenuto delle singole waybills codificato in base64<br>`N` = non restituisce le singole waybills<br>`N.B.`Di default se non passato, questo valore viene assunto come N. |

**Response shape:** object: `bordero_url` (string), `custom` (string), `error_code` (number), `error_message` (string), `order_id` (string), `pickup_code` (string), `result` (string), `single_waybills` (array<object>), `timestamp` (string), `version` (string), `waybill_base64` (string), `waybill_number` (string), `waybill_url` (string)
