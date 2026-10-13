# Geography

Countries, provinces, towns, holidays, and courier surcharges.

[API overview and shared conventions](./README.md) · [Original HTML export](../spediamo-doc.html)

## Geo

countries, provinces. towns.

### `GET /v1/countries`

Read countries

**Notes:** Read countries list

**Parameters**

| Name | In | Type | Required | Description |
|---|---|---|---|---|
| `If-None-Match` | header | string | no | Current ETag response header |

**Primary response:** HTTP `200`.

**Swagger example shape:** array<object>

### `GET /v1/countries/{country}/holidays`

Read holidays

**Notes:** Read holidays list for the given country.Currently, only supported country is Italy (IT).Returns all knows holidays starting from today, for the next 365 days.

**Parameters**

| Name | In | Type | Required | Description |
|---|---|---|---|---|
| `country` | path | string | yes | the ISO 3166-1 alpha-2 code of the country.; values: IT |
| `If-None-Match` | header | string | no | Current ETag response header |

**Primary response:** HTTP `200`.

**Swagger example shape:** array<string>

### `GET /v1/countries/{country}/provinces`

Read provinces

**Notes:** Read provinces list for the given country.Currently, only supported country is Italy (IT).

**Parameters**

| Name | In | Type | Required | Description |
|---|---|---|---|---|
| `country` | path | string | yes | the ISO 3166-1 alpha-2 code of the country.; values: IT |
| `If-None-Match` | header | string | no | Current ETag response header |

**Primary response:** HTTP `200`.

**Swagger example shape:** array<object>

## Geo for Couriers

towns with surcharges.

### `GET /v1/countries/{country}/provinces/{province}/municipalities`

Read municipalities with additional surcharges information

> Deprecated in source export.

**Notes:** Read municipalities list for the given province in the given country, providing additional information about surcharges asked by the couriers.Currently, only supported country is Italy (IT).

**Parameters**

| Name | In | Type | Required | Description |
|---|---|---|---|---|
| `country` | path | string | yes | the ISO 3166-1 alpha-2 code of the country.; values: IT |
| `province` | path | string | yes | the province code |
| `surcharges` | query | string | yes | values: on (default) |
| `If-None-Match` | header | string | no | Current ETag response header |

**Primary response:** HTTP `200`.

**Swagger example shape:** array<object>

### `GET /v1/countries/{country}/provinces/{province}/towns`

Read towns with additional surcharges information

**Notes:** Read towns list for the given province in the given country, providing additional information about surcharges asked by the couriers.Currently, only supported country is Italy (IT).

**Parameters**

| Name | In | Type | Required | Description |
|---|---|---|---|---|
| `country` | path | string | yes | the ISO 3166-1 alpha-2 code of the country.; values: IT |
| `province` | path | string | yes | the province code |
| `surcharges` | query | string | yes | values: on (default) |
| `If-None-Match` | header | string | no | Current ETag response header |

**Primary response:** HTTP `200`.

**Swagger example shape:** array<object>

### `GET /v1/countries/{country}/surchargedTowns`

Read all surcharges towns

**Notes:** Read surcharges towns list for the given country, providing additional information about surcharges asked by the couriers.Currently, only supported country is Italy (IT).

**Parameters**

| Name | In | Type | Required | Description |
|---|---|---|---|---|
| `country` | path | string | yes | the ISO 3166-1 alpha-2 code of the country.; values: IT |
| `If-None-Match` | header | string | no | Current ETag response header |

**Primary response:** HTTP `200`.

**Swagger example shape:** array<object>
