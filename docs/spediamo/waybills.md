# Waybills

Shipping document downloads.

[API overview and shared conventions](./README.md) · [Original HTML export](../vendor/spediamo-doc.html)

## Waybill

shipping documents.

### `GET /v1/waybills/{shipmentId}/pdf`

Read a waybill pdf

**Parameters**

| Name | In | Type | Required | Description |
|---|---|---|---|---|
| `shipmentId` | path | string | yes | The id of the shipment |
| `wholeOrder` | query | string | no | generate a pdf including all waybills for the same order of this shipment |
| `includeManifest` | query | string | no | generate a pdf including the manifest |
| `download` | query | string | no | set the response header to download the document |
| `If-None-Match` | header | string | no | Current ETag response header |

**Primary response:** HTTP `200`.

**Swagger example shape:** object; top-level fields only (nested details omitted): `description`: string, `filename`: string, `inputStream`: object, `open`: boolean, `readable`: boolean, `uri`: object, `url`: object
