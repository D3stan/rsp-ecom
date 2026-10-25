# Shipments

Create, estimate, search, read, update, stream, and track shipments.

[API overview and shared conventions](./README.md) · [Original HTML export](../vendor/spediamo-doc.html)

## Shipments

shipments management.

### `POST /v1/shipments`

Create new shipment

**Parameters**

| Name | In | Type | Required | Description |
|---|---|---|---|---|
| `tags` | query | Array[string] | no | — |
| `type` | query | string | no | — |
| `requestedPickupDate` | query | string | no | — |
| `requestedPickupTime` | query | string | no | — |
| `deliveryNotes` | query | string | no | — |
| `insurance` | query | string | no | — |
| `insuranceAmount` | query | string | no | — |
| `cashOnDelivery` | query | string | no | — |
| `cashOnDeliveryAmount` | query | string | no | — |
| `iban` | query | string | no | — |
| `bankAccountHolder` | query | string | no | — |
| `senderId` | query | string | no | — |
| `updateSender` | query | string | no | — |
| `senderName` | query | string | no | — |
| `senderContactName` | query | string | no | — |
| `senderCountry` | query | string | no | — |
| `senderProvince` | query | string | no | — |
| `senderTown` | query | string | no | — |
| `senderPostalCode` | query | string | no | — |
| `senderAddress` | query | string | no | — |
| `senderPhone` | query | string | no | — |
| `senderMobile` | query | string | no | — |
| `senderFax` | query | string | no | — |
| `senderEmail` | query | string | no | — |
| `senderHidden` | query | string | no | — |
| `senderServicePointId` | query | string | no | — |
| `recipientId` | query | string | no | — |
| `updateRecipient` | query | string | no | — |
| `recipientName` | query | string | no | — |
| `recipientContactName` | query | string | no | — |
| `recipientCountry` | query | string | no | — |
| `recipientProvince` | query | string | no | — |
| `recipientTown` | query | string | no | — |
| `recipientPostalCode` | query | string | no | — |
| `recipientAddress` | query | string | no | — |
| `recipientPhone` | query | string | no | — |
| `recipientMobile` | query | string | no | — |
| `recipientFax` | query | string | no | — |
| `recipientEmail` | query | string | no | — |
| `recipientVatNumber` | query | string | no | — |
| `recipientServicePointId` | query | string | no | — |
| `floorDelivery` | query | string | no | — |
| `floorDeliveryLevel` | query | string | no | — |
| `floorDeliveryLift` | query | string | no | — |
| `agreedDelivery` | query | string | no | — |
| `agreedDeliveryPhone` | query | string | no | — |
| `eveningDelivery` | query | string | no | — |
| `saturdayDelivery` | query | string | no | — |
| `referenceNumber` | query | string | no | — |
| `billingName` | query | string | no | — |
| `billingType` | query | string | no | — |
| `billingVatNumber` | query | string | no | — |
| `billingTaxPayerCode` | query | string | no | — |
| `billingRecipientCode` | query | string | no | — |
| `billingProvince` | query | string | no | — |
| `billingTown` | query | string | no | — |
| `billingPostalCode` | query | string | no | — |
| `billingAddress` | query | string | no | — |
| `billingPhone` | query | string | no | — |
| `billingMobile` | query | string | no | — |
| `billingEmail` | query | string | no | — |
| `billingEmailCheck` | query | string | no | — |
| `allMandatory` | query | string | no | — |
| `coupon` | query | string | no | — |
| `letters` | query | string | no | — |
| `packages` | query | string | no | — |
| `suitcases` | query | string | no | — |
| `pallets` | query | string | no | — |

**Primary response:** HTTP `201`.

**Swagger example shape:** object; top-level fields only (nested details omitted): `agreedDelivery`: boolean, `agreedDeliveryCommission`: number, `agreedDeliveryPhone`: string, `archived`: boolean, `bankAccountHolder`: string, `basePrice`: number, `billingAddress`: string, `billingEmail`: string, `billingMobile`: string, `billingName`: string, `billingPhone`: string, `billingPostalCode`: string, `billingProvince`: string, `billingRecipientCode`: string, `billingTaxPayerCode`: string, `billingTown`: string, `billingType`: string, `billingVatNumber`: string, `cashOnDelivery`: boolean, `cashOnDeliveryAmount`: number, `cashOnDeliveryCommission`: number, `code`: string, `coupon`: string, `couponDiscount`: number, `couponTaxableAmount`: number, `couponTotalAmount`: number, `courier`: string, `currency`: string, `deliveryDate`: string, `deliveryNotes`: string, `deliveryTime`: object, `destinationSurcharge`: number, `difference`: object, `dimensionalWeightApplied`: boolean, `discount`: number, `errors`: object, `eveningDelivery`: boolean, `eveningDeliveryCommission`: number, `expectedDeliveryDate`: string, `floorDelivery`: boolean, `floorDeliveryCommission`: number, `floorDeliveryLevel`: string, `fuelSurcharge`: number, `iban`: string, `id`: string, `insurance`: boolean, `insuranceAmount`: number, `insuranceCommission`: number, `letters`: array<object>, `netPrice`: number, `packages`: array<object>, `pallets`: array<object>, `paymentSystems`: array<string>, `pickupCode`: string, `pickupDate`: string, `recipientAddress`: string, `recipientAlias`: string, `recipientContactName`: string, `recipientCountry`: string, `recipientEmail`: string, `recipientFax`: string, `recipientId`: number, `recipientMobile`: string, `recipientName`: string, `recipientPhone`: string, `recipientPostalCode`: string, `recipientProvince`: string, `recipientTown`: string, `recipientVatNumber`: string, `referenceNumber`: string, `requestedPickupDate`: string, `requestedPickupTime`: string, `saturdayDelivery`: boolean, `saturdayDeliveryCommission`: number, `scheduledPickupDate`: string, `senderAddress`: string, `senderAlias`: string, `senderContactName`: string, `senderCountry`: string, `senderEmail`: string, `senderFax`: string, `senderHidden`: boolean, `senderId`: number, `senderMobile`: string, `senderName`: string, `senderPhone`: string, `senderPostalCode`: string, `senderProvince`: string, `senderTown`: string, `status`: string, `stocks`: array<object>, `suitcases`: array<object>, `taxableWeight`: number, `tmst`: string, `totalPrice`: number, `tracking`: array<object>, `trackingNumber`: string, `transaction`: object, `type`: string, `underpaid`: boolean, `userId`: number, `valid`: boolean, `vat`: number, `waitingForPickupCode`: boolean, `warnings`: object, `waybillAvailable`: boolean, `weight`: number, `weightClass`: string

**Other documented responses:**
- HTTP `400` — Invalid input parameters
- HTTP `401` — Not authorized

### `DELETE /v1/shipments`

Delete shipments not bought

**Parameters**

| Name | In | Type | Required | Description |
|---|---|---|---|---|
| `ids` | path | string | no | The unique ids of the shipments |
| `type` | query | Array[string] | no | — |
| `searchText` | query | string | no | — |
| `sort` | query | Array[string] | no | Sorting criteria in the format: property(,asc\|desc). Default sort order is ascending. Multiple sort criteria are supported. |

**Documented responses:**
- HTTP `200` — Shipments deleted successfully
- HTTP `401` — Not authorized
- HTTP `403` — One or more shipments have invalid status (must be DRAFT)
- HTTP `404` — One or more shipments were not found

### `POST /v1/shipments/estimate`

Estimate shipments

**Notes:** Estimate shipments to checkout

**Parameters**

| Name | In | Type | Required | Description |
|---|---|---|---|---|
| `ids` | path | string | no | The unique ids of the shipments |
| `coupon` | query | string | no | — |

**Primary response:** HTTP `200`.

**Swagger example shape:** object; top-level fields only (nested details omitted): `couponTaxableAmount`: number, `couponTotalAmount`: number, `currency`: string, `discountAmounts`: number, `items`: number, `netAmount`: number, `paymentSystems`: array<string>, `taxableAmount`: number, `taxes`: number, `totalAmount`: number, `vatRate`: number

**Other documented responses:**
- HTTP `401` — Not authorized
- HTTP `404` — One or more shipments were not found

### `GET /v1/shipments/search/archived`

Search for archived shipments

**Parameters**

| Name | In | Type | Required | Description |
|---|---|---|---|---|
| `type` | query | Array[string] | no | — |
| `searchText` | query | string | no | — |
| `fromDate` | query | string | no | — |
| `toDate` | query | string | no | — |
| `page` | query | ref | no | Results page you want to retrieve (0..N) |
| `size` | query | ref | no | Number of records per page. |
| `sort` | query | Array[string] | no | Sorting criteria in the format: property(,asc\|desc). Default sort order is ascending. Multiple sort criteria are supported. |

**Primary response:** HTTP `200`.

**Swagger example shape:** object; top-level fields only (nested details omitted): `_embedded`: object, `_links`: array<object>, `page`: object

**Other documented responses:**
- HTTP `400` — Invalid input parameters
- HTTP `401` — Not authorized

### `GET /v1/shipments/search/{status}`

Search for shipments

**Notes:** List shipments by status and search criteria. Statuses meaning: draft = not yet bought, booked = bought but not scheduled for pickup, ready = scheduled for pickup, current = picked up and not yet delivered, on_hold = that need stock cleareance, underpaid = not paid enough, failed = unsuccessful

**Parameters**

| Name | In | Type | Required | Description |
|---|---|---|---|---|
| `status` | path | string | no | The status of the shipments; values: draft, booked, ready, current, delivered, on_hold, underpaid, failed |
| `type` | query | Array[string] | no | — |
| `searchText` | query | string | no | — |
| `sort` | query | Array[string] | no | Sorting criteria in the format: property(,asc\|desc). Default sort order is ascending. Multiple sort criteria are supported. |

**Primary response:** HTTP `200`.

**Swagger example shape:** array<object>

**Other documented responses:**
- HTTP `401` — Not authorized

### `GET /v1/shipments/settings`

Read shipment default settings

**Parameters**

| Name | In | Type | Required | Description |
|---|---|---|---|---|
| `type` | query | string | no | — |

**Primary response:** HTTP `200`.

**Swagger example shape:** object; top-level fields only (nested details omitted): `agreedDeliveryEnabled`: boolean, `bankAccountHolder`: string, `basePrice`: number, `billingMobile`: string, `cashOnDeliveryEnabled`: boolean, `cashOnDeliveryMaxAmount`: number, `couponForShipmentsEnabled`: boolean, `currency`: string, `deliveryNotesMaxLength`: number, `enabled`: boolean, `eveningDeliveryEnabled`: boolean, `floorDeliveryEnabled`: boolean, `fuelSurcharge`: number, `holidays`: array<string>, `iban`: string, `insuranceEnabled`: boolean, `insuranceMaxAmount`: number, `message`: object, `netPrice`: number, `palletMaxHeight`: number, `palletMaxLength`: number, `palletMaxWeight`: number, `palletMaxWidth`: number, `palletsEnabled`: boolean, `parcelMaxHeight`: number, `parcelMaxLength`: number, `parcelMaxWeight`: number, `parcelMaxWidth`: number, `phoneMaxLength`: number, `pickupDate`: string, `pickupTime`: string, `pickupTimes`: array<string>, `preferredSenderId`: number, `recipientAddressMaxLength`: number, `recipientContactNameMaxLength`: number, `recipientExtraUECountries`: array<object>, `recipientNameMaxLength`: number, `recipientPostalCodeMaxLength`: number, `recipientTownMaxLength`: number, `recipientUECountries`: array<object>, `saturdayDeliveryEnabled`: boolean, `senderAddressMaxLength`: number, `senderContactNameMaxLength`: number, `senderExtraUECountries`: array<object>, `senderHiddenEnabled`: boolean, `senderNameMaxLength`: number, `senderPostalCodeMaxLength`: number, `senderTownMaxLength`: number, `senderUECountries`: array<object>, `suitcaseMaxHeight`: number, `suitcaseMaxLength`: number, `suitcaseMaxWeight`: number, `suitcaseMaxWidth`: number, `totalPrice`: number, `vat`: number

**Other documented responses:**
- HTTP `401` — Not authorized

### `GET /v1/shipments/stream/{status}`

Stream shipments

**Notes:** Stream shipments by status and search criteria. Statuses meaning: draft = not yet bought, booked = bought but not scheduled for pickup, ready = scheduled for pickup, current = picked up and not yet delivered, on_hold = that need stock cleareance, underpaid = not paid enough, failed = unsuccessful

**Parameters**

| Name | In | Type | Required | Description |
|---|---|---|---|---|
| `status` | path | string | no | The status of the shipments; values: draft, booked, ready, current, delivered, on_hold, underpaid, failed |
| `type` | query | Array[string] | no | — |
| `searchText` | query | string | no | — |
| `sort` | query | Array[string] | no | Sorting criteria in the format: property(,asc\|desc). Default sort order is ascending. Multiple sort criteria are supported. |

**Documented responses:**
- HTTP `200` — Shipments streamed successfully
- HTTP `401` — Not authorized

### `GET /v1/shipments/tracking`

Read shipment tracking

**Parameters**

| Name | In | Type | Required | Description |
|---|---|---|---|---|
| `code` | query | string | no | The tracking code of the shipment |
| `If-None-Match` | header | string | no | Current ETag response header |

**Primary response:** HTTP `200`.

**Swagger example shape:** object; top-level fields only (nested details omitted): `code`: string, `courier`: string, `deliveryDate`: string, `deliveryTime`: object, `expectedDeliveryDate`: string, `pickupCode`: string, `pickupDate`: string, `scheduledPickupDate`: string, `status`: string, `tracking`: array<object>, `trackingNumber`: string, `type`: string, `waitingForPickupCode`: boolean

**Other documented responses:**
- HTTP `404` — Shipment not found

### `DELETE /v1/shipments/{ids}`

Delete shipments not bought

**Parameters**

| Name | In | Type | Required | Description |
|---|---|---|---|---|
| `ids` | path | string | no | The unique ids of the shipments |
| `type` | query | Array[string] | no | — |
| `searchText` | query | string | no | — |
| `sort` | query | Array[string] | no | Sorting criteria in the format: property(,asc\|desc). Default sort order is ascending. Multiple sort criteria are supported. |

**Documented responses:**
- HTTP `200` — Shipments deleted successfully
- HTTP `401` — Not authorized
- HTTP `403` — One or more shipments have invalid status (must be DRAFT)
- HTTP `404` — One or more shipments were not found

### `POST /v1/shipments/{ids}/estimate`

Estimate shipments

**Notes:** Estimate shipments to checkout

**Parameters**

| Name | In | Type | Required | Description |
|---|---|---|---|---|
| `ids` | path | string | no | The unique ids of the shipments |
| `coupon` | query | string | no | — |

**Primary response:** HTTP `200`.

**Swagger example shape:** object; top-level fields only (nested details omitted): `couponTaxableAmount`: number, `couponTotalAmount`: number, `currency`: string, `discountAmounts`: number, `items`: number, `netAmount`: number, `paymentSystems`: array<string>, `taxableAmount`: number, `taxes`: number, `totalAmount`: number, `vatRate`: number

**Other documented responses:**
- HTTP `401` — Not authorized
- HTTP `404` — One or more shipments were not found

### `GET /v1/shipments/{id}`

Read a shipment

**Parameters**

| Name | In | Type | Required | Description |
|---|---|---|---|---|
| `id` | path | string | yes | The unique id of the shipment |
| `includeErrors` | query | string | no | Include validation errors in response |
| `If-None-Match` | header | string | no | Current ETag response header |

**Primary response:** HTTP `200`.

**Swagger example shape:** object; top-level fields only (nested details omitted): `agreedDelivery`: boolean, `agreedDeliveryCommission`: number, `agreedDeliveryPhone`: string, `archived`: boolean, `bankAccountHolder`: string, `basePrice`: number, `billingAddress`: string, `billingEmail`: string, `billingMobile`: string, `billingName`: string, `billingPhone`: string, `billingPostalCode`: string, `billingProvince`: string, `billingRecipientCode`: string, `billingTaxPayerCode`: string, `billingTown`: string, `billingType`: string, `billingVatNumber`: string, `cashOnDelivery`: boolean, `cashOnDeliveryAmount`: number, `cashOnDeliveryCommission`: number, `code`: string, `coupon`: string, `couponDiscount`: number, `couponTaxableAmount`: number, `couponTotalAmount`: number, `courier`: string, `currency`: string, `deliveryDate`: string, `deliveryNotes`: string, `deliveryTime`: object, `destinationSurcharge`: number, `difference`: object, `dimensionalWeightApplied`: boolean, `discount`: number, `errors`: object, `eveningDelivery`: boolean, `eveningDeliveryCommission`: number, `expectedDeliveryDate`: string, `floorDelivery`: boolean, `floorDeliveryCommission`: number, `floorDeliveryLevel`: string, `fuelSurcharge`: number, `iban`: string, `id`: string, `insurance`: boolean, `insuranceAmount`: number, `insuranceCommission`: number, `letters`: array<object>, `netPrice`: number, `packages`: array<object>, `pallets`: array<object>, `paymentSystems`: array<string>, `pickupCode`: string, `pickupDate`: string, `recipientAddress`: string, `recipientAlias`: string, `recipientContactName`: string, `recipientCountry`: string, `recipientEmail`: string, `recipientFax`: string, `recipientId`: number, `recipientMobile`: string, `recipientName`: string, `recipientPhone`: string, `recipientPostalCode`: string, `recipientProvince`: string, `recipientTown`: string, `recipientVatNumber`: string, `referenceNumber`: string, `requestedPickupDate`: string, `requestedPickupTime`: string, `saturdayDelivery`: boolean, `saturdayDeliveryCommission`: number, `scheduledPickupDate`: string, `senderAddress`: string, `senderAlias`: string, `senderContactName`: string, `senderCountry`: string, `senderEmail`: string, `senderFax`: string, `senderHidden`: boolean, `senderId`: number, `senderMobile`: string, `senderName`: string, `senderPhone`: string, `senderPostalCode`: string, `senderProvince`: string, `senderTown`: string, `status`: string, `stocks`: array<object>, `suitcases`: array<object>, `taxableWeight`: number, `tmst`: string, `totalPrice`: number, `tracking`: array<object>, `trackingNumber`: string, `transaction`: object, `type`: string, `underpaid`: boolean, `userId`: number, `valid`: boolean, `vat`: number, `waitingForPickupCode`: boolean, `warnings`: object, `waybillAvailable`: boolean, `weight`: number, `weightClass`: string

**Other documented responses:**
- HTTP `400` — Given shipment id is not a valid identifier
- HTTP `401` — Not authorized
- HTTP `404` — Shipment not found

### `PUT /v1/shipments/{id}`

Modify a shipment

**Parameters**

| Name | In | Type | Required | Description |
|---|---|---|---|---|
| `id` | path | string | yes | The unique id of the shipment |
| `tags` | query | Array[string] | no | — |
| `type` | query | string | no | — |
| `requestedPickupDate` | query | string | no | — |
| `requestedPickupTime` | query | string | no | — |
| `deliveryNotes` | query | string | no | — |
| `insurance` | query | string | no | — |
| `insuranceAmount` | query | string | no | — |
| `cashOnDelivery` | query | string | no | — |
| `cashOnDeliveryAmount` | query | string | no | — |
| `iban` | query | string | no | — |
| `bankAccountHolder` | query | string | no | — |
| `senderId` | query | string | no | — |
| `updateSender` | query | string | no | — |
| `senderName` | query | string | no | — |
| `senderContactName` | query | string | no | — |
| `senderCountry` | query | string | no | — |
| `senderProvince` | query | string | no | — |
| `senderTown` | query | string | no | — |
| `senderPostalCode` | query | string | no | — |
| `senderAddress` | query | string | no | — |
| `senderPhone` | query | string | no | — |
| `senderMobile` | query | string | no | — |
| `senderFax` | query | string | no | — |
| `senderEmail` | query | string | no | — |
| `senderHidden` | query | string | no | — |
| `senderServicePointId` | query | string | no | — |
| `recipientId` | query | string | no | — |
| `updateRecipient` | query | string | no | — |
| `recipientName` | query | string | no | — |
| `recipientContactName` | query | string | no | — |
| `recipientCountry` | query | string | no | — |
| `recipientProvince` | query | string | no | — |
| `recipientTown` | query | string | no | — |
| `recipientPostalCode` | query | string | no | — |
| `recipientAddress` | query | string | no | — |
| `recipientPhone` | query | string | no | — |
| `recipientMobile` | query | string | no | — |
| `recipientFax` | query | string | no | — |
| `recipientEmail` | query | string | no | — |
| `recipientVatNumber` | query | string | no | — |
| `recipientServicePointId` | query | string | no | — |
| `floorDelivery` | query | string | no | — |
| `floorDeliveryLevel` | query | string | no | — |
| `floorDeliveryLift` | query | string | no | — |
| `agreedDelivery` | query | string | no | — |
| `agreedDeliveryPhone` | query | string | no | — |
| `eveningDelivery` | query | string | no | — |
| `saturdayDelivery` | query | string | no | — |
| `referenceNumber` | query | string | no | — |
| `billingName` | query | string | no | — |
| `billingType` | query | string | no | — |
| `billingVatNumber` | query | string | no | — |
| `billingTaxPayerCode` | query | string | no | — |
| `billingRecipientCode` | query | string | no | — |
| `billingProvince` | query | string | no | — |
| `billingTown` | query | string | no | — |
| `billingPostalCode` | query | string | no | — |
| `billingAddress` | query | string | no | — |
| `billingPhone` | query | string | no | — |
| `billingMobile` | query | string | no | — |
| `billingEmail` | query | string | no | — |
| `billingEmailCheck` | query | string | no | — |
| `allMandatory` | query | string | no | — |
| `coupon` | query | string | no | — |
| `id` | query | string | no | — |
| `patch` | query | boolean | no | — |
| `letters` | query | string | no | — |
| `packages` | query | string | no | — |
| `suitcases` | query | string | no | — |
| `pallets` | query | string | no | — |
| `If-Match` | header | string | no | The value must match with current ETag response header |

**Primary response:** HTTP `200`.

**Swagger example shape:** object; top-level fields only (nested details omitted): `agreedDelivery`: boolean, `agreedDeliveryCommission`: number, `agreedDeliveryPhone`: string, `archived`: boolean, `bankAccountHolder`: string, `basePrice`: number, `billingAddress`: string, `billingEmail`: string, `billingMobile`: string, `billingName`: string, `billingPhone`: string, `billingPostalCode`: string, `billingProvince`: string, `billingRecipientCode`: string, `billingTaxPayerCode`: string, `billingTown`: string, `billingType`: string, `billingVatNumber`: string, `cashOnDelivery`: boolean, `cashOnDeliveryAmount`: number, `cashOnDeliveryCommission`: number, `code`: string, `coupon`: string, `couponDiscount`: number, `couponTaxableAmount`: number, `couponTotalAmount`: number, `courier`: string, `currency`: string, `deliveryDate`: string, `deliveryNotes`: string, `deliveryTime`: object, `destinationSurcharge`: number, `difference`: object, `dimensionalWeightApplied`: boolean, `discount`: number, `errors`: object, `eveningDelivery`: boolean, `eveningDeliveryCommission`: number, `expectedDeliveryDate`: string, `floorDelivery`: boolean, `floorDeliveryCommission`: number, `floorDeliveryLevel`: string, `fuelSurcharge`: number, `iban`: string, `id`: string, `insurance`: boolean, `insuranceAmount`: number, `insuranceCommission`: number, `letters`: array<object>, `netPrice`: number, `packages`: array<object>, `pallets`: array<object>, `paymentSystems`: array<string>, `pickupCode`: string, `pickupDate`: string, `recipientAddress`: string, `recipientAlias`: string, `recipientContactName`: string, `recipientCountry`: string, `recipientEmail`: string, `recipientFax`: string, `recipientId`: number, `recipientMobile`: string, `recipientName`: string, `recipientPhone`: string, `recipientPostalCode`: string, `recipientProvince`: string, `recipientTown`: string, `recipientVatNumber`: string, `referenceNumber`: string, `requestedPickupDate`: string, `requestedPickupTime`: string, `saturdayDelivery`: boolean, `saturdayDeliveryCommission`: number, `scheduledPickupDate`: string, `senderAddress`: string, `senderAlias`: string, `senderContactName`: string, `senderCountry`: string, `senderEmail`: string, `senderFax`: string, `senderHidden`: boolean, `senderId`: number, `senderMobile`: string, `senderName`: string, `senderPhone`: string, `senderPostalCode`: string, `senderProvince`: string, `senderTown`: string, `status`: string, `stocks`: array<object>, `suitcases`: array<object>, `taxableWeight`: number, `tmst`: string, `totalPrice`: number, `tracking`: array<object>, `trackingNumber`: string, `transaction`: object, `type`: string, `underpaid`: boolean, `userId`: number, `valid`: boolean, `vat`: number, `waitingForPickupCode`: boolean, `warnings`: object, `waybillAvailable`: boolean, `weight`: number, `weightClass`: string

**Other documented responses:**
- HTTP `400` — Invalid input parameters
- HTTP `401` — Not authorized
- HTTP `403` — Invalid status of the shipment. Must be EDIT
- HTTP `404` — Shipment not found
- HTTP `412` — Shipment If-Match header is outdated
- HTTP `428` — Missing required If-Match header

### `PATCH /v1/shipments/{id}`

Patch a shipment

**Parameters**

| Name | In | Type | Required | Description |
|---|---|---|---|---|
| `id` | path | string | yes | The unique id of the shipment |
| `tags` | query | Array[string] | no | — |
| `type` | query | string | no | — |
| `requestedPickupDate` | query | string | no | — |
| `requestedPickupTime` | query | string | no | — |
| `deliveryNotes` | query | string | no | — |
| `insurance` | query | string | no | — |
| `insuranceAmount` | query | string | no | — |
| `cashOnDelivery` | query | string | no | — |
| `cashOnDeliveryAmount` | query | string | no | — |
| `iban` | query | string | no | — |
| `bankAccountHolder` | query | string | no | — |
| `senderId` | query | string | no | — |
| `updateSender` | query | string | no | — |
| `senderName` | query | string | no | — |
| `senderContactName` | query | string | no | — |
| `senderCountry` | query | string | no | — |
| `senderProvince` | query | string | no | — |
| `senderTown` | query | string | no | — |
| `senderPostalCode` | query | string | no | — |
| `senderAddress` | query | string | no | — |
| `senderPhone` | query | string | no | — |
| `senderMobile` | query | string | no | — |
| `senderFax` | query | string | no | — |
| `senderEmail` | query | string | no | — |
| `senderHidden` | query | string | no | — |
| `senderServicePointId` | query | string | no | — |
| `recipientId` | query | string | no | — |
| `updateRecipient` | query | string | no | — |
| `recipientName` | query | string | no | — |
| `recipientContactName` | query | string | no | — |
| `recipientCountry` | query | string | no | — |
| `recipientProvince` | query | string | no | — |
| `recipientTown` | query | string | no | — |
| `recipientPostalCode` | query | string | no | — |
| `recipientAddress` | query | string | no | — |
| `recipientPhone` | query | string | no | — |
| `recipientMobile` | query | string | no | — |
| `recipientFax` | query | string | no | — |
| `recipientEmail` | query | string | no | — |
| `recipientVatNumber` | query | string | no | — |
| `recipientServicePointId` | query | string | no | — |
| `floorDelivery` | query | string | no | — |
| `floorDeliveryLevel` | query | string | no | — |
| `floorDeliveryLift` | query | string | no | — |
| `agreedDelivery` | query | string | no | — |
| `agreedDeliveryPhone` | query | string | no | — |
| `eveningDelivery` | query | string | no | — |
| `saturdayDelivery` | query | string | no | — |
| `referenceNumber` | query | string | no | — |
| `billingName` | query | string | no | — |
| `billingType` | query | string | no | — |
| `billingVatNumber` | query | string | no | — |
| `billingTaxPayerCode` | query | string | no | — |
| `billingRecipientCode` | query | string | no | — |
| `billingProvince` | query | string | no | — |
| `billingTown` | query | string | no | — |
| `billingPostalCode` | query | string | no | — |
| `billingAddress` | query | string | no | — |
| `billingPhone` | query | string | no | — |
| `billingMobile` | query | string | no | — |
| `billingEmail` | query | string | no | — |
| `billingEmailCheck` | query | string | no | — |
| `allMandatory` | query | string | no | — |
| `coupon` | query | string | no | — |
| `id` | query | string | no | — |
| `patch` | query | boolean | no | — |
| `letters` | query | string | no | — |
| `packages` | query | string | no | — |
| `suitcases` | query | string | no | — |
| `pallets` | query | string | no | — |
| `If-Match` | header | string | no | The value must match with current ETag response header |

**Primary response:** HTTP `200`.

**Swagger example shape:** object; top-level fields only (nested details omitted): `agreedDelivery`: boolean, `agreedDeliveryCommission`: number, `agreedDeliveryPhone`: string, `archived`: boolean, `bankAccountHolder`: string, `basePrice`: number, `billingAddress`: string, `billingEmail`: string, `billingMobile`: string, `billingName`: string, `billingPhone`: string, `billingPostalCode`: string, `billingProvince`: string, `billingRecipientCode`: string, `billingTaxPayerCode`: string, `billingTown`: string, `billingType`: string, `billingVatNumber`: string, `cashOnDelivery`: boolean, `cashOnDeliveryAmount`: number, `cashOnDeliveryCommission`: number, `code`: string, `coupon`: string, `couponDiscount`: number, `couponTaxableAmount`: number, `couponTotalAmount`: number, `courier`: string, `currency`: string, `deliveryDate`: string, `deliveryNotes`: string, `deliveryTime`: object, `destinationSurcharge`: number, `difference`: object, `dimensionalWeightApplied`: boolean, `discount`: number, `errors`: object, `eveningDelivery`: boolean, `eveningDeliveryCommission`: number, `expectedDeliveryDate`: string, `floorDelivery`: boolean, `floorDeliveryCommission`: number, `floorDeliveryLevel`: string, `fuelSurcharge`: number, `iban`: string, `id`: string, `insurance`: boolean, `insuranceAmount`: number, `insuranceCommission`: number, `letters`: array<object>, `netPrice`: number, `packages`: array<object>, `pallets`: array<object>, `paymentSystems`: array<string>, `pickupCode`: string, `pickupDate`: string, `recipientAddress`: string, `recipientAlias`: string, `recipientContactName`: string, `recipientCountry`: string, `recipientEmail`: string, `recipientFax`: string, `recipientId`: number, `recipientMobile`: string, `recipientName`: string, `recipientPhone`: string, `recipientPostalCode`: string, `recipientProvince`: string, `recipientTown`: string, `recipientVatNumber`: string, `referenceNumber`: string, `requestedPickupDate`: string, `requestedPickupTime`: string, `saturdayDelivery`: boolean, `saturdayDeliveryCommission`: number, `scheduledPickupDate`: string, `senderAddress`: string, `senderAlias`: string, `senderContactName`: string, `senderCountry`: string, `senderEmail`: string, `senderFax`: string, `senderHidden`: boolean, `senderId`: number, `senderMobile`: string, `senderName`: string, `senderPhone`: string, `senderPostalCode`: string, `senderProvince`: string, `senderTown`: string, `status`: string, `stocks`: array<object>, `suitcases`: array<object>, `taxableWeight`: number, `tmst`: string, `totalPrice`: number, `tracking`: array<object>, `trackingNumber`: string, `transaction`: object, `type`: string, `underpaid`: boolean, `userId`: number, `valid`: boolean, `vat`: number, `waitingForPickupCode`: boolean, `warnings`: object, `waybillAvailable`: boolean, `weight`: number, `weightClass`: string

**Other documented responses:**
- HTTP `400` — Invalid input parameters
- HTTP `401` — Not authorized
- HTTP `403` — Invalid status of the shipment. Must be EDIT
- HTTP `404` — Shipment not found
- HTTP `412` — Shipment If-Match header is outdated
- HTTP `428` — Missing required If-Match header
