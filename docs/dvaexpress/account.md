# Account and billing

Inspect API-key details, manage network customers, and work with prepaid balances.

[API overview and shared conventions](./README.md) · [Original sanitized HTML export](../vendor/dvaexpress-doc.html)


### `POST https://api.easyparcel.it/apikeyinfo/{APIKEY}`

Read the API-key contract, configuration, operating mode, and available prepaid credit.

**Request body:** JSON.

**Request fields**

| Field | Type | Value | Required | Description |
|---|---|---|---|---|
| `call` | string | `apikeyinfo` | yes | Inserire "apikeyinfo" |

**Response shape:** object: `response` (object)


### `POST https://api.easyparcel.it/newcustomer/{APIKEY}`

Create a customer under the API-key holder's network.

**Request body:** JSON.

**Request fields**

| Field | Type | Example | Required | Description |
|---|---|---|---|---|
| `call` | string | `newcustomer` | yes | Inserire `newcustomer` |
| `dettagli.cognome` | string | `ROSSI` | yes | Inserire il cognome del cliente. |
| `dettagli.nome` | string | `MARIO` | yes | Inserire il nome del cliente. |
| `dettagli.indirizzo` | string | `PIAZZA DUOMO, 1` | yes | Inserire l'indirizzo del cliente. |
| `dettagli.cap` | string | `20121` | yes | Inserire il cap del cliente. |
| `dettagli.localita` | string | `MILANO` | yes | Inserire la località del cliente. |
| `dettagli.provincia` | string | `MI` | yes | Inserire la sigla della provincia italiana del cliente.<br>`N.B.` In caso di cliente estero, compilare questo campo come segue: `--` |
| `dettagli.nazione` | string | `IT` | yes | Inserire la sigla della nazione del cliente, usando il codice ISO 3166-1 Alpha-2 (link). |
| `dettagli.email` | string | `mariorossi@gmail.com` | yes | Inserire l'email del cliente. |
| `dettagli.telefono` | string | `0212345678` | optional | Inserire il numero di telefono del cliente. |
| `dettagli.cellulare` | string | `3470123456` | optional | Inserire il numero di cellulare del cliente. |
| `dettagli.ragionesociale` | string | `ROSSI MARIO SLRS` | optional | Inserire l'eventuale ragione sociale del cliente. |
| `dettagli.codicefiscale` | string | `RSSMRA11A11A111A` | yes | Inserire il codice fiscale del cliente. |
| `dettagli.partitaiva` | string | `01234567890` | optional | Inserire l'eventuale partita Iva del cliente.<br>`N.B.` Campo obbligatorio se compilato il campo `ragionesociale`. |
| `dettagli.fe_pec` | string | `marirossi@pec.it` | optional | Inserire l'eventuale PEC del cliente.<br>`N.B.` Campo obbligatorio se compilato il campo `ragionesociale` (non obbligatorio per cliente estero). |
| `dettagli.fe_sdi` | string | `0000000` | yes | Inserire l'eventuale SDI del cliente (inserire 0000000 per privato). |
| `dettagli.banca` | string | `CREDITO EMILIANO SPA` | optional | Inserire il nome della banca del cliente. |
| `dettagli.iban` | string | `IT1A12341234123412341234123` | optional | Inserire l'eventuale IBAN del cliente.<br>`N.B.` Campo necessario per rimessa contrassegni. |
| `dettagli.intestazioneconto` | string | `ROSSI MARIO` | optional | Inserire il nome esatto dell'intestazione del conto bancario del cliente. |
| `dettagli.username` | string | `mariorossi` | yes | Inserire la username di accesso del cliente. |
| `dettagli.password` | string | `12345678` | yes | Inserire la password di accesso del cliente. |


### `POST https://api.easyparcel.it/addfund/{APIKEY}`

Transfer prepaid credit from the master account to a network customer.

**Request body:** JSON.

**Request fields**

| Field | Type | Example | Required | Description |
|---|---|---|---|---|
| `call` | string | `addfund` | yes | Inserire `addfund` |
| `dettagli.idcustomer` | numeric | `3723` | yes | Inserire l'ID del cliente al quale girare i fondi. |
| `dettagli.amount` | decimal | `100.00` | yes | Inserire l'importo da girare al cliente. Il credito master del capofila deve essere maggiore o uguale all'importo da girare al cliente. In caso di importo decimale, utilizzare il PUNTO e non la VIRGOLA come separatore decimale. |


### `POST https://api.easyparcel.it/balance/{APIKEY}`

Read the master balance and, when requested, a network customer's balance.

**Request body:** JSON.

**Request fields**

| Field | Type | Example | Required | Description |
|---|---|---|---|---|
| `call` | string | `balance` | yes | Inserire `balance` |
| `dettagli.idcustomer` | numeric | `3723` | optional | Inserire eventualmente l'ID del cliente del quale si vuole conoscere il credito residuo. |
