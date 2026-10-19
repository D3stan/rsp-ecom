#!/usr/bin/env python3
"""Generate the compact DVA Express API reference from EasyParcel's HTML export.

The upstream documentation is a single static HTML page.  This script keeps a
sanitized local snapshot and emits the concise, resource-oriented Markdown
reference used by this repository.  It deliberately has no third-party
dependencies so a documentation refresh is reproducible in a clean checkout.
"""

from __future__ import annotations

import argparse
import html
import json
import re
from html.parser import HTMLParser
from pathlib import Path
from typing import Iterable


SOURCE_URL = "https://docs.easyparcel.it/"
API_BASE_URL = "https://api.easyparcel.it"
API_KEY_PATTERN = re.compile(r"(?<![A-Za-z0-9_])[A-Fa-f0-9]{64}(?![A-Za-z0-9_])")
ANCHOR_PATTERN = re.compile(r'<a\s+name="(?P<name>[^"]+)"[^>]*>\s*</a>', re.IGNORECASE)
VOID_ELEMENTS = {"area", "base", "br", "col", "embed", "hr", "img", "input", "link", "meta", "source", "wbr"}


OPERATIONS = {
    "ApiKeyInfo": {
        "title": "API key information",
        "summary": "Read the API-key contract, configuration, operating mode, and available prepaid credit.",
        "endpoints": (("POST", "/apikeyinfo/{APIKEY}"),),
    },
    "Quotation": {
        "title": "Quotation",
        "summary": "Price a shipment. Pass the returned `codice_offerta` to `order` to purchase the selected quote.",
        "endpoints": (("POST", "/quotation/{APIKEY}"),),
    },
    "Order": {
        "title": "Order",
        "summary": "Purchase a shipment from a previous quotation and provide sender, recipient, and optional-service details.",
        "endpoints": (("POST", "/order/{APIKEY}"),),
    },
    "OrderFast": {
        "title": "Fast order",
        "summary": "Create a quote, purchase it, and obtain the waybill in one request.",
        "endpoints": (("POST", "/order-fast/{APIKEY}"),),
    },
    "GetWaybill": {
        "title": "Waybill",
        "summary": "Retrieve a purchased order's waybill, optionally as Base64 and split into one document per package.",
        "endpoints": (("POST", "/getwaybill/{APIKEY}"),),
    },
    "TARIC": {
        "title": "TARIC / HS lookup",
        "summary": "Find customs TARIC/HS codes from a natural-language product description for international shipments.",
        "endpoints": (
            ("POST", "/taric-search/{APIKEY}"),
            ("GET", "/taric-search/{APIKEY}?q={query}&lang={language}&limit={limit}"),
        ),
        "request_note": "**Request:** the POST variant accepts a JSON body; the GET variant accepts equivalent query parameters.",
        "notes": "The API key may alternatively be supplied through the `X-API-KEY` header.",
    },
    "GetOrder": {
        "title": "Order details",
        "summary": "Read the status, carrier, shipment, and document details for one purchased order.",
        "endpoints": (("POST", "/getorder/{APIKEY}"),),
    },
    "ListOrder": {
        "title": "Order list",
        "summary": "List orders with filters and pagination; the source documents a default page size of 10 and a maximum of 100.",
        "endpoints": (("POST", "/listorder/{APIKEY}"),),
    },
    "Tracking": {
        "title": "Tracking",
        "summary": "Read shipment tracking by order ID or waybill number.",
        "endpoints": (("POST", "/tracking/{APIKEY}"),),
    },
    "NewCustomer": {
        "title": "Customer",
        "summary": "Create a customer under the API-key holder's network.",
        "endpoints": (("POST", "/newcustomer/{APIKEY}"),),
    },
    "AddFund": {
        "title": "Add funds",
        "summary": "Transfer prepaid credit from the master account to a network customer.",
        "endpoints": (("POST", "/addfund/{APIKEY}"),),
    },
    "Balance": {
        "title": "Balance",
        "summary": "Read the master balance and, when requested, a network customer's balance.",
        "endpoints": (("POST", "/balance/{APIKEY}"),),
    },
    "PUDO": {
        "title": "Pickup and drop-off points",
        "summary": "Find PDB (Poste Delivery Business) and INPOST pickup/drop-off points by province, postal code/radius, or coordinates.",
        "endpoints": (("POST", "/pudo/{APIKEY}"),),
    },
    "CheckCoupon": {
        "title": "Coupon validation",
        "summary": "Check whether a discount coupon can be applied to a shipment.",
        "endpoints": (("POST", "/checkcoupon/{APIKEY}"),),
    },
    "Richiesta Storno": {
        "title": "Cancellation request",
        "summary": "Request cancellation for up to 500 purchased orders whose shipments have not departed; `solo_verifica` supports a dry run.",
        "endpoints": (("POST", "/richiesta-storno/{APIKEY}"),),
        "notes": "The API key may alternatively be supplied through the `X-API-KEY` header.",
    },
}


DOCUMENTS = (
    {
        "filename": "shipments.md",
        "title": "Shipments",
        "summary": "Quote shipments, create standard orders, or use the combined fast-order flow.",
        "anchors": ("Quotation", "Order", "OrderFast"),
    },
    {
        "filename": "waybills.md",
        "title": "Waybills",
        "summary": "Retrieve shipping documents for purchased orders.",
        "anchors": ("GetWaybill",),
    },
    {
        "filename": "orders.md",
        "title": "Orders and tracking",
        "summary": "Read individual orders, list orders, and obtain tracking information.",
        "anchors": ("GetOrder", "ListOrder", "Tracking"),
    },
    {
        "filename": "account.md",
        "title": "Account and billing",
        "summary": "Inspect API-key details, manage network customers, and work with prepaid balances.",
        "anchors": ("ApiKeyInfo", "NewCustomer", "AddFund", "Balance"),
    },
    {
        "filename": "locations-and-customs.md",
        "title": "Locations and customs",
        "summary": "Search TARIC/HS codes and carrier pickup/drop-off points.",
        "anchors": ("TARIC", "PUDO"),
    },
    {
        "filename": "coupons-and-cancellations.md",
        "title": "Coupons and cancellations",
        "summary": "Validate shipment coupons and request eligible order cancellations.",
        "anchors": ("CheckCoupon", "Richiesta Storno"),
    },
    {
        "filename": "errors.md",
        "title": "Errors",
        "summary": "The error catalogue published with the EasyParcel API reference.",
        "anchors": ("Errori",),
    },
)

EXPECTED_ANCHORS = tuple(OPERATIONS) + ("Errori",)


HEADER_TRANSLATIONS = {
    "livello 1": "Level 1",
    "livello 2": "Level 2",
    "livello 3": "Level 3",
    "livello 4": "Level 4",
    "tipo": "Type",
    "valore": "Value",
    "esempio": "Example",
    "obbligatorio": "Required",
    "descrizione": "Description",
    "codice errore": "Error code",
    "codice": "Code",
}


class Element:
    """A small DOM node sufficient for the predictable upstream HTML."""

    def __init__(self, tag: str, attributes: dict[str, str]) -> None:
        self.tag = tag
        self.attributes = attributes
        self.children: list[Element | str] = []
        self.parent: Element | None = None


class DocumentParser(HTMLParser):
    """Build a minimal element tree without an external HTML dependency."""

    def __init__(self) -> None:
        super().__init__(convert_charrefs=True)
        self.root = Element("document", {})
        self.stack = [self.root]

    def handle_starttag(self, tag: str, attributes: list[tuple[str, str | None]]) -> None:
        tag = tag.lower()
        element = Element(tag, {name: value or "" for name, value in attributes})
        element.parent = self.stack[-1]
        self.stack[-1].children.append(element)
        if tag not in VOID_ELEMENTS:
            self.stack.append(element)

    def handle_startendtag(self, tag: str, attributes: list[tuple[str, str | None]]) -> None:
        self.handle_starttag(tag, attributes)
        if tag.lower() not in VOID_ELEMENTS:
            self.handle_endtag(tag)

    def handle_endtag(self, tag: str) -> None:
        tag = tag.lower()
        for index in range(len(self.stack) - 1, 0, -1):
            if self.stack[index].tag == tag:
                del self.stack[index:]
                return

    def handle_data(self, data: str) -> None:
        self.stack[-1].children.append(data)


def parse_fragment(fragment: str) -> Element:
    parser = DocumentParser()
    parser.feed(fragment)
    parser.close()
    return parser.root


def walk(element: Element) -> Iterable[Element]:
    for child in element.children:
        if isinstance(child, Element):
            yield child
            yield from walk(child)


def has_class(element: Element, class_name: str) -> bool:
    return class_name in element.attributes.get("class", "").split()


def collapse(value: str) -> str:
    value = re.sub(r"[ \t\r\f\v]+", " ", value)
    value = re.sub(r"\s*\n\s*", " ", value)
    return value.strip()


def plain_text(node: Element | str) -> str:
    if isinstance(node, str):
        return node
    if node.tag in {"br", "hr"}:
        return "\n"
    return "".join(plain_text(child) for child in node.children)


def markdown_text(node: Element | str) -> str:
    if isinstance(node, str):
        return node
    if node.tag == "br":
        return "<br>"
    if node.tag == "hr":
        return "<br><br>"
    content = "".join(markdown_text(child) for child in node.children)
    if node.tag == "code":
        escaped = collapse(content).replace("`", "\\`")
        return f"`{escaped}`"
    if node.tag == "a":
        href = node.attributes.get("href", "").strip()
        label = collapse(content)
        return f"[{label}]({href})" if href and label else content
    return content


def element_label(element: Element) -> str:
    return collapse(plain_text(element))


def cell_markdown(element: Element) -> str:
    value = markdown_text(element)
    value = re.sub(r"[ \t\r\f\v]+", " ", value)
    value = re.sub(r"\s*<br>\s*", "<br>", value)
    value = collapse(value)
    return value.replace("|", "\\|")


def direct_children(element: Element, *tags: str) -> list[Element]:
    return [child for child in element.children if isinstance(child, Element) and child.tag in tags]


def first_descendant(element: Element, predicate) -> Element | None:
    return next((item for item in walk(element) if predicate(item)), None)


def panel_pairs(root: Element) -> list[tuple[str, Element]]:
    """Return each panel heading and its immediately following panel body."""

    pairs: list[tuple[str, Element]] = []
    for heading in walk(root):
        if heading.tag != "div" or not has_class(heading, "panel-heading") or not heading.parent:
            continue
        title_node = first_descendant(heading, lambda item: item.tag.startswith("h"))
        if not title_node:
            continue
        siblings = [child for child in heading.parent.children if isinstance(child, Element)]
        try:
            position = siblings.index(heading)
        except ValueError:
            continue
        body = next((item for item in siblings[position + 1 :] if has_class(item, "panel-body")), None)
        if body:
            pairs.append((element_label(title_node), body))
    return pairs


def table_rows(table: Element) -> list[list[Element]]:
    rows: list[list[Element]] = []
    for row in walk(table):
        if row.tag != "tr":
            continue
        cells = direct_children(row, "th", "td")
        if cells:
            rows.append(cells)
    return rows


def normalized(value: str) -> str:
    return re.sub(r"\s+", " ", value.replace("à", "a").replace("è", "e").lower()).strip()


def translate_header(value: str) -> str:
    return HEADER_TRANSLATIONS.get(normalized(value), value)


def compact_request_table(table: Element) -> str:
    """Collapse hierarchy columns into one dotted field name, like Spediamo docs."""

    rows = table_rows(table)
    if not rows:
        return ""
    headers = [cell_markdown(cell) for cell in rows[0]]
    normalized_headers = [normalized(header) for header in headers]
    type_index = next((index for index, value in enumerate(normalized_headers) if value in {"tipo", "type"}), None)
    if type_index is None:
        return generic_table(table)

    required_index = next(
        (index for index, value in enumerate(normalized_headers) if value in {"obbligatorio", "required"}),
        None,
    )
    description_index = next(
        (index for index, value in enumerate(normalized_headers) if value in {"descrizione", "description"}),
        None,
    )
    extra_indexes = [
        index
        for index in range(type_index + 1, len(headers))
        if index not in {required_index, description_index}
    ]
    path_parts = [""] * type_index
    output_rows: list[list[str]] = []

    for row in rows[1:]:
        values = [cell_markdown(cell) for cell in row]
        values.extend([""] * (len(headers) - len(values)))
        for index in range(type_index):
            value = values[index].strip()
            if value and not re.fullmatch(r"[▶▷→]+", value):
                path_parts[index] = value
                for nested_index in range(index + 1, type_index):
                    path_parts[nested_index] = ""
        field = ".".join(part for part in path_parts if part)
        if not field:
            continue
        required = values[required_index] if required_index is not None else "—"
        if normalized(required) == "s":
            required = "yes"
        elif normalized(required) == "n":
            required = "no"
        description = values[description_index] if description_index is not None else "—"
        field = re.sub(r"`([^`]+)`", r"\1", field)
        output_rows.append(
            [f"`{field}`", values[type_index] or "—"]
            + [values[index] or "—" for index in extra_indexes]
            + [required or "—", description or "—"]
        )

    if not output_rows:
        return generic_table(table)
    return markdown_table(
        ["Field", "Type"] + [translate_header(headers[index]) for index in extra_indexes] + ["Required", "Description"],
        output_rows,
    )


def generic_table(table: Element) -> str:
    rows = table_rows(table)
    if not rows:
        return ""
    headers = [translate_header(cell_markdown(cell)) for cell in rows[0]]
    values = []
    for row in rows[1:]:
        cells = [cell_markdown(cell) for cell in row]
        cells.extend([""] * (len(headers) - len(cells)))
        values.append(cells[: len(headers)])
    return markdown_table(headers, values)


def markdown_table(headers: list[str], rows: list[list[str]]) -> str:
    if not headers:
        return ""
    output = [f"| {' | '.join(headers)} |", f"|{'|'.join('---' for _ in headers)}|"]
    output.extend(f"| {' | '.join(row)} |" for row in rows)
    return "\n".join(output)


def first_preformatted(element: Element) -> str | None:
    pre = first_descendant(element, lambda item: item.tag == "pre")
    return plain_text(pre).strip() if pre else None


def response_shape(body: Element) -> str | None:
    content = first_preformatted(body)
    if not content:
        return None
    try:
        decoded = json.loads(content)
    except json.JSONDecodeError:
        return None
    if isinstance(decoded, dict):
        fields = [f"`{name}` ({json_type(decoded[name])})" for name in sorted(decoded)]
        return "object: " + ", ".join(fields)
    return json_type(decoded)


def json_type(value: object) -> str:
    if isinstance(value, bool):
        return "boolean"
    if isinstance(value, str):
        return "string"
    if isinstance(value, (int, float)):
        return "number"
    if value is None:
        return "null"
    if isinstance(value, dict):
        return "object"
    if isinstance(value, list):
        return "array" if not value else f"array<{json_type(value[0])}>"
    return "unknown"


def section_fragments(source: str) -> dict[str, str]:
    matches = list(ANCHOR_PATTERN.finditer(source))
    fragments: dict[str, str] = {}
    for index, match in enumerate(matches):
        name = html.unescape(match.group("name"))
        end = matches[index + 1].start() if index + 1 < len(matches) else len(source)
        fragments[name] = source[match.end() : end]
    return fragments


def request_tables(pairs: list[tuple[str, Element]]) -> list[tuple[str, Element]]:
    selected = []
    for title, body in pairs:
        lowered = normalized(title)
        if any(marker in lowered for marker in ("requisiti", "requirements", "parametri", "parameters")):
            table = first_descendant(body, lambda item: item.tag == "table")
            if table:
                selected.append((title, table))
    return selected


def response_tables(pairs: list[tuple[str, Element]]) -> list[tuple[str, Element]]:
    selected = []
    for title, body in pairs:
        lowered = normalized(title)
        if "campi della risposta" in lowered or "response fields" in lowered:
            table = first_descendant(body, lambda item: item.tag == "table")
            if table:
                selected.append((title, table))
    return selected


def render_operation(anchor: str, fragment: str) -> str:
    operation = OPERATIONS[anchor]
    endpoint_lines = [f"`{method} {API_BASE_URL}{path}`" for method, path in operation["endpoints"]]
    lines = [f"### {endpoint_lines[0]}", "", operation["summary"]]
    if len(endpoint_lines) > 1:
        lines.extend(["", f"**Also available:** {', '.join(endpoint_lines[1:])}."])
    lines.extend(["", operation.get("request_note", "**Request body:** JSON.")])

    pairs = panel_pairs(parse_fragment(fragment))
    fields = request_tables(pairs)
    for index, (title, table) in enumerate(fields):
        label = "Request fields" if len(fields) == 1 else f"Request fields — {title}"
        lines.extend(["", f"**{label}**", "", compact_request_table(table)])

    shape = next(
        (
            response_shape(body)
            for title, body in pairs
            if "risposta json" in normalized(title) or "response json" in normalized(title)
        ),
        None,
    )
    if shape:
        lines.extend(["", f"**Response shape:** {shape}"])

    for title, table in response_tables(pairs):
        lines.extend(["", "**Response fields**", "", generic_table(table)])

    if operation.get("notes"):
        lines.extend(["", f"**Notes:** {operation['notes']}"])
    return "\n".join(line for line in lines if line is not None).rstrip()


def render_errors(fragment: str) -> str:
    root = parse_fragment(fragment)
    description = next(
        (
            collapse(plain_text(item))
            for item in walk(root)
            if item.tag == "h4" and has_class(item, "page-title")
        ),
        "",
    )
    lines = ["## Error catalogue"]
    if description:
        lines.extend(["", description])
    lines.extend(
        [
            "",
            "**HTTP status:** HTTP `200` identifies a positive response; HTTP `400` identifies a request error.",
            "",
            "**Error payload:** JSON normally includes `result`, `errorcode`, `errormessage`, and, when supplied, `errordetails` and `timestamp`.",
        ]
    )
    tables = [item for item in walk(root) if item.tag == "table"]
    for table in tables:
        rendered = generic_table(table)
        if rendered:
            lines.extend(["", rendered])
    return "\n".join(lines).rstrip()


def render_document(document: dict[str, object], fragments: dict[str, str]) -> str:
    title = str(document["title"])
    summary = str(document["summary"])
    anchors = tuple(document["anchors"])
    lines = [f"# {title}", "", summary, "", "[API overview and shared conventions](./README.md) · [Original sanitized HTML export](../dvaexpress-doc.html)"]
    for anchor in anchors:
        fragment = fragments.get(anchor)
        if not fragment:
            continue
        lines.extend(["", ""])
        lines.append(render_errors(fragment) if anchor == "Errori" else render_operation(anchor, fragment))
    return "\n".join(lines).rstrip() + "\n"


def source_version(source: str) -> str:
    match = re.search(r"(?:Ultima|Latest)\s+versione\s*:\s*([0-9.]+)", source, re.IGNORECASE)
    return match.group(1) if match else "not stated"


def render_readme(version: str) -> str:
    return f"""# DVA Express API reference

- **Provider API:** EasyParcel
- **Base URL:** `{API_BASE_URL}`
- **Documented API version:** `{version}`
- **Source:** [sanitized EasyParcel HTML export](../dvaexpress-doc.html), captured from [{SOURCE_URL}]({SOURCE_URL})
- **Authentication:** the normal endpoint form is `POST /{{operation}}/{{APIKEY}}` with `Content-Type: application/json`. TARIC and cancellation requests may alternatively send the key in `X-API-KEY`.
- **Request bodies:** JSON. Most requests declare the operation with a `call` field; the endpoint pages list the exact fields and requiredness.
- **Response convention:** successful and error payloads commonly expose `result`, `errorcode`, `errormessage`, `timestamp`, and `version`; see [Errors](./errors.md) for the provider's error catalogue.
- **Snapshot hygiene:** the public example API key is replaced with `{{APIKEY}}` before this source snapshot is stored.

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
python3 scripts/generate_dvaexpress_docs.py \\
  --source /path/to/easyparcel.html \\
  --snapshot docs/dvaexpress-doc.html \\
  --output docs/dvaexpress
```

The generator uses only Python's standard library, preserves request-field tables, summarizes response shapes, and redacts public example API keys.
"""


def sanitize_snapshot(source: str) -> str:
    source = API_KEY_PATTERN.sub("{APIKEY}", source)
    return source.replace("LA_TUA_API_KEY", "{APIKEY}")


def generate(
    source_path: Path,
    snapshot_path: Path,
    output_directory: Path,
    require_complete: bool = False,
) -> None:
    """Sanitize one downloaded source page and create its compact Markdown views."""

    source = sanitize_snapshot(source_path.read_text(encoding="utf-8"))
    snapshot_path.parent.mkdir(parents=True, exist_ok=True)
    snapshot_path.write_text(source, encoding="utf-8")

    fragments = section_fragments(source)
    if require_complete:
        missing = [anchor for anchor in EXPECTED_ANCHORS if anchor not in fragments]
        if missing:
            raise ValueError(f"EasyParcel source is missing expected sections: {', '.join(missing)}")
    output_directory.mkdir(parents=True, exist_ok=True)
    (output_directory / "README.md").write_text(render_readme(source_version(source)), encoding="utf-8")
    for document in DOCUMENTS:
        path = output_directory / str(document["filename"])
        path.write_text(render_document(document, fragments), encoding="utf-8")


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--source", type=Path, required=True, help="Downloaded EasyParcel HTML source")
    parser.add_argument("--snapshot", type=Path, default=Path("docs/dvaexpress-doc.html"), help="Sanitized HTML snapshot destination")
    parser.add_argument("--output", type=Path, default=Path("docs/dvaexpress"), help="Markdown output directory")
    arguments = parser.parse_args()
    generate(arguments.source, arguments.snapshot, arguments.output, require_complete=True)


if __name__ == "__main__":
    main()
