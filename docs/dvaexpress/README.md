# DVA Express API reference

- **Provider API:** EasyParcel
- **Base URL:** `https://api.easyparcel.it`
- **Documented API version:** `1.1.20`
- **Source:** [sanitized EasyParcel HTML export](../dvaexpress-doc.html), captured from [https://docs.easyparcel.it/](https://docs.easyparcel.it/)
- **Authentication:** the normal endpoint form is `POST /{operation}/{APIKEY}` with `Content-Type: application/json`. TARIC and cancellation requests may alternatively send the key in `X-API-KEY`.
- **Request bodies:** JSON. Most requests declare the operation with a `call` field; the endpoint pages list the exact fields and requiredness.
- **Response convention:** successful and error payloads commonly expose `result`, `errorcode`, `errormessage`, `timestamp`, and `version`; see [Errors](./errors.md) for the provider's error catalogue.
- **Snapshot hygiene:** the public example API key is replaced with `{APIKEY}` before this source snapshot is stored.

## Resources

- [Shipments](./shipments.md) — quote, order, and fast-order flows (3 operations)
- [Waybills](./waybills.md) — waybill retrieval (1 operation)
- [Orders and tracking](./orders.md) — order lookup, listing, and tracking (3 operations)
- [Account and billing](./account.md) — API-key info, network customers, funds, and balances (4 operations)
- [Locations and customs](./locations-and-customs.md) — TARIC/HS and PUDO searches (2 resources; 3 method/path variants)
- [Coupons and cancellations](./coupons-and-cancellations.md) — coupon validation and cancellation requests (2 operations)
- [Errors](./errors.md) — provider error codes and messages

The source documents **15 API resources** and **16 method/path variants** because TARIC exposes both POST and GET forms.

## Refreshing the reference

Download a new HTML snapshot outside the application API, then run:

```sh
python3 scripts/generate_dvaexpress_docs.py \
  --source /path/to/easyparcel.html \
  --snapshot docs/dvaexpress-doc.html \
  --output docs/dvaexpress
```

The generator uses only Python's standard library, preserves request-field tables, summarizes response shapes, and redacts public example API keys.
