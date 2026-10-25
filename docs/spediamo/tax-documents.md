# Tax documents

Invoices and credit notes.

[API overview and shared conventions](./README.md) · [Original HTML export](../vendor/spediamo-doc.html)

## Tax Document

invoices and credit notes.

### `GET /v1/taxDocuments/search`

Search for tax documents

**Parameters**

| Name | In | Type | Required | Description |
|---|---|---|---|---|
| `searchText` | query | string | no | search by keyword |
| `type` | query | string | no | search by type |
| `startDate` | query | string | no | search by start date |
| `endDate` | query | string | no | search by end date |
| `discarded` | query | string | no | search by discarded |
| `page` | query | ref | no | Results page you want to retrieve (0..N) |
| `size` | query | ref | no | Number of records per page. |
| `sort` | query | Array[string] | no | Sorting criteria in the format: property(,asc\|desc). Default sort order is ascending. Multiple sort criteria are supported. |

**Primary response:** HTTP `200`.

**Swagger example shape:** object; top-level fields only (nested details omitted): `_embedded`: object, `_links`: array<object>, `page`: object

### `GET /v1/taxDocuments/{id}`

Read a tax document

**Parameters**

| Name | In | Type | Required | Description |
|---|---|---|---|---|
| `id` | path | string | yes | The unique id of the tax document |
| `If-None-Match` | header | string | no | Current ETag response header |

**Primary response:** HTTP `200`.

**Swagger example shape:** object; top-level fields only (nested details omitted): `available`: boolean, `billingRecipientCode`: string, `billingTaxPayerCode`: string, `billingVatNumber`: string, `errors`: object, `id`: string, `number`: string, `sdi`: boolean, `year`: string

**Other documented responses:**
- HTTP `401` — Not authorized
- HTTP `404` — Tax document not found

### `GET /v1/taxDocuments/{taxDocumentId}/pdf`

Read a tax document pdf

**Notes:** Read a tax document pdf (either an invoice or a credit note)

**Parameters**

| Name | In | Type | Required | Description |
|---|---|---|---|---|
| `taxDocumentId` | path | string | yes | The id of the tax document |
| `download` | query | string | no | set the response header to download the document |
| `If-None-Match` | header | string | no | Current ETag response header |

**Primary response:** HTTP `200`.

**Swagger example shape:** object; top-level fields only (nested details omitted): `description`: string, `filename`: string, `inputStream`: object, `open`: boolean, `readable`: boolean, `uri`: object, `url`: object
