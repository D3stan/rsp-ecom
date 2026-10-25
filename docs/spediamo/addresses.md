# Addresses

Address book management.

[API overview and shared conventions](./README.md) · [Original HTML export](../vendor/spediamo-doc.html)

## Addresses

address book management.

### `POST /v1/addresses`

Create new address

**Parameters**

| Name | In | Type | Required | Description |
|---|---|---|---|---|
| `type` | query | string | no | — |
| `usage` | query | string | no | — |
| `alias` | query | string | no | — |
| `name` | query | string | no | — |
| `contactName` | query | string | no | — |
| `country` | query | string | no | — |
| `province` | query | string | no | — |
| `town` | query | string | no | — |
| `postalCode` | query | string | no | — |
| `address` | query | string | no | — |
| `phone` | query | string | no | — |
| `mobile` | query | string | no | — |
| `fax` | query | string | no | — |
| `email` | query | string | no | — |

**Primary response:** HTTP `201`.

**Swagger example shape:** object; top-level fields only (nested details omitted): `address`: string, `alias`: string, `contactName`: string, `country`: string, `email`: string, `errors`: object, `fax`: string, `id`: number, `mobile`: string, `name`: string, `phone`: string, `postalCode`: string, `preferred`: boolean, `province`: string, `recipient`: boolean, `sender`: boolean, `town`: string, `type`: string, `usage`: string, `warnings`: object

**Other documented responses:**
- HTTP `400` — Invalid input parameters
- HTTP `401` — Not authorized

### `DELETE /v1/addresses`

Delete addresses

**Parameters**

| Name | In | Type | Required | Description |
|---|---|---|---|---|
| `ids` | path | string | no | The unique ids of the addresses to delete |
| `type` | query | string | no | — |
| `searchText` | query | string | no | — |

**Documented responses:**
- HTTP `200` — Addresses deleted successfully
- HTTP `401` — Not authorized
- HTTP `404` — One or more address were not found

### `GET /v1/addresses/settings`

Read address default settings

**Parameters**

| Name | In | Type | Required | Description |
|---|---|---|---|---|
| `type` | query | string | no | — |

**Primary response:** HTTP `200`.

**Swagger example shape:** object; top-level fields only (nested details omitted): `addressMaxLength`: number, `aliasMaxLength`: number, `contactNameMaxLength`: number, `nameMaxLength`: number, `phoneMaxLength`: number

**Other documented responses:**
- HTTP `401` — Not authorized

### `GET /v1/addresses/stream`

Stream address book

**Parameters**

| Name | In | Type | Required | Description |
|---|---|---|---|---|
| `type` | query | string | no | — |
| `searchText` | query | string | no | — |

**Documented responses:**
- HTTP `200` — Address book streamed successfully
- HTTP `401` — Not authorized

### `DELETE /v1/addresses/{ids}`

Delete addresses

**Parameters**

| Name | In | Type | Required | Description |
|---|---|---|---|---|
| `ids` | path | string | no | The unique ids of the addresses to delete |
| `type` | query | string | no | — |
| `searchText` | query | string | no | — |

**Documented responses:**
- HTTP `200` — Addresses deleted successfully
- HTTP `401` — Not authorized
- HTTP `404` — One or more address were not found

### `GET /v1/addresses/{id}`

Read an address

**Parameters**

| Name | In | Type | Required | Description |
|---|---|---|---|---|
| `id` | path | long | yes | The unique id of the address |
| `If-None-Match` | header | string | no | Current ETag response header |

**Primary response:** HTTP `200`.

**Swagger example shape:** object; top-level fields only (nested details omitted): `address`: string, `alias`: string, `contactName`: string, `country`: string, `email`: string, `errors`: object, `fax`: string, `id`: number, `mobile`: string, `name`: string, `phone`: string, `postalCode`: string, `preferred`: boolean, `province`: string, `recipient`: boolean, `sender`: boolean, `town`: string, `type`: string, `usage`: string, `warnings`: object

**Other documented responses:**
- HTTP `400` — Given address id is not a valid identifier
- HTTP `401` — Not authorized
- HTTP `404` — Address not found

### `PUT /v1/addresses/{id}`

Modify an address

**Parameters**

| Name | In | Type | Required | Description |
|---|---|---|---|---|
| `id` | path | long | yes | The unique id of the address |
| `type` | query | string | no | — |
| `usage` | query | string | no | — |
| `alias` | query | string | no | — |
| `name` | query | string | no | — |
| `contactName` | query | string | no | — |
| `country` | query | string | no | — |
| `province` | query | string | no | — |
| `town` | query | string | no | — |
| `postalCode` | query | string | no | — |
| `address` | query | string | no | — |
| `phone` | query | string | no | — |
| `mobile` | query | string | no | — |
| `fax` | query | string | no | — |
| `email` | query | string | no | — |
| `id` | query | long | no | — |
| `If-Match` | header | string | no | The value must match with current ETag response header |

**Primary response:** HTTP `200`.

**Swagger example shape:** object; top-level fields only (nested details omitted): `address`: string, `alias`: string, `contactName`: string, `country`: string, `email`: string, `errors`: object, `fax`: string, `id`: number, `mobile`: string, `name`: string, `phone`: string, `postalCode`: string, `preferred`: boolean, `province`: string, `recipient`: boolean, `sender`: boolean, `town`: string, `type`: string, `usage`: string, `warnings`: object

**Other documented responses:**
- HTTP `400` — Invalid input parameters
- HTTP `401` — Not authorized
- HTTP `404` — Address not found
- HTTP `412` — Address If-Match header is outdated
- HTTP `428` — Missing required If-Match header

### `PUT /v1/addresses/{id}/preferred`

Mark as preferred

**Notes:** Modify address preferred flag. The address ETag header is added to the response.

**Parameters**

| Name | In | Type | Required | Description |
|---|---|---|---|---|
| `id` | path | long | yes | The unique id of the address |
| `preferred` | query | string | no | flag to tell if the address is the preferred one; values: true, false |

**Primary response:** HTTP `200`.

**Swagger example shape:** object; top-level fields only (nested details omitted): `address`: string, `alias`: string, `contactName`: string, `country`: string, `email`: string, `errors`: object, `fax`: string, `id`: number, `mobile`: string, `name`: string, `phone`: string, `postalCode`: string, `preferred`: boolean, `province`: string, `recipient`: boolean, `sender`: boolean, `town`: string, `type`: string, `usage`: string, `warnings`: object

**Other documented responses:**
- HTTP `400` — Invalid address id
- HTTP `401` — Missing, expired or wrong authorization token
- HTTP `404` — Address not found
