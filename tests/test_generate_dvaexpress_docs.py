"""Regression coverage for the DVA Express documentation generator."""

from __future__ import annotations

import importlib.util
import tempfile
import unittest
from pathlib import Path


REPOSITORY = Path(__file__).resolve().parents[1]
GENERATOR = REPOSITORY / "scripts" / "generate_dvaexpress_docs.py"


class GenerateDvaexpressDocsTest(unittest.TestCase):
    def load_generator(self):
        specification = importlib.util.spec_from_file_location(
            "generate_dvaexpress_docs", GENERATOR
        )
        self.assertIsNotNone(specification)
        assert specification and specification.loader
        generator = importlib.util.module_from_spec(specification)
        specification.loader.exec_module(generator)
        return generator

    def test_generator_redacts_example_key_and_emits_compact_reference(self) -> None:
        """A source section becomes a structured Markdown operation reference."""
        self.assertTrue(GENERATOR.exists(), "the documentation generator is missing")
        generator = self.load_generator()

        source_html = """
        <a name="Dashboard"></a>
        <div><h3 class="page-title">Dashboard</h3><h4 class="page-title">Version 1.1.20</h4></div>
        <a name="Quotation"></a>
        <div>
          <h3 class="page-title">Quotation</h3>
          <h4 class="page-title">Calculate a shipment quote.</h4>
          <div class="panel">
            <div class="panel-heading"><h3 class="panel-title">Request JSON</h3></div>
            <div class="panel-body"><pre>{"call":"quotation"}</pre></div>
            <div class="panel-heading"><h3 class="panel-title">Request requirements</h3></div>
            <div class="panel-body"><table><thead><tr><th>Level 1</th><th>Type</th><th>Required</th><th>Description</th></tr></thead><tbody><tr><td>call</td><td>string</td><td>yes</td><td>Use <a href="https://example.test/reference">reference</a> aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa</td></tr></tbody></table></div>
            <div class="panel-heading"><h3 class="panel-title">Response JSON</h3></div>
            <div class="panel-body"><pre>{"result":"OK","quotation":[{}]}</pre></div>
          </div>
        </div>
        <a name="Errori"></a><div><h3 class="page-title">Errori</h3></div>
        <pre>https://api.easyparcel.it/quotation/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa</pre>
        <pre>https://api.easyparcel.it/pudo/LA_TUA_API_KEY</pre>
        """

        with tempfile.TemporaryDirectory() as temporary_directory:
            temporary = Path(temporary_directory)
            source = temporary / "source.html"
            source_snapshot = temporary / "dvaexpress-doc.html"
            output = temporary / "dvaexpress"
            source.write_text(source_html, encoding="utf-8")

            generator.generate(source, source_snapshot, output)

            snapshot = source_snapshot.read_text(encoding="utf-8")
            shipments = (output / "shipments.md").read_text(encoding="utf-8")

        self.assertNotIn("a" * 64, snapshot)
        self.assertNotIn("LA_TUA_API_KEY", snapshot)
        self.assertNotIn("a" * 64, shipments)
        self.assertIn("[reference](https://example.test/reference)", shipments)
        self.assertIn("{APIKEY}", snapshot)
        self.assertIn("### `POST https://api.easyparcel.it/quotation/{APIKEY}`", shipments)
        self.assertIn("| Field | Type | Required | Description |", shipments)
        self.assertIn("**Response shape:** object: `quotation` (array<object>), `result` (string)", shipments)

    def test_generator_keeps_request_variant_columns_and_code_field_names(self) -> None:
        """Carrier-specific request columns remain visible and valid Markdown."""
        generator = self.load_generator()
        source_html = """
        <a name="PUDO"></a>
        <div><h3 class="page-title">PUDO</h3>
          <div class="panel">
            <div class="panel-heading"><h3 class="panel-title">Request parameters</h3></div>
            <div class="panel-body"><table><thead><tr><th>Parametro</th><th>Tipo</th><th>PDB</th><th>INPOST</th><th>Obbligatorio</th><th>Descrizione</th></tr></thead><tbody><tr><td><code>vettore</code></td><td>string</td><td>✓</td><td>✓</td><td>S</td><td>Carrier</td></tr></tbody></table></div>
          </div>
        </div>
        <a name="Errori"></a><div><h3 class="page-title">Errori</h3></div>
        """

        with tempfile.TemporaryDirectory() as temporary_directory:
            temporary = Path(temporary_directory)
            source = temporary / "source.html"
            source_snapshot = temporary / "dvaexpress-doc.html"
            output = temporary / "dvaexpress"
            source.write_text(source_html, encoding="utf-8")

            generator.generate(source, source_snapshot, output)

            locations = (output / "locations-and-customs.md").read_text(encoding="utf-8")

        self.assertIn("| Field | Type | PDB | INPOST | Required | Description |", locations)
        self.assertIn("| `vettore` | string | ✓ | ✓ | yes | Carrier |", locations)

    def test_taric_distinguishes_its_post_body_from_get_query_parameters(self) -> None:
        """The TARIC GET form must not be described as a JSON-body request."""
        generator = self.load_generator()

        rendered = generator.render_operation("TARIC", "")

        self.assertIn("**Request:** the POST variant accepts a JSON body; the GET variant accepts equivalent query parameters.", rendered)
        self.assertNotIn("**Request body:** JSON.", rendered)

    def test_error_page_explains_http_status_and_error_payload(self) -> None:
        """The generated catalogue keeps the source's response-handling guidance."""
        generator = self.load_generator()

        rendered = generator.render_errors("<h4 class=\"page-title\">Errors</h4>")

        self.assertIn("**HTTP status:** HTTP `200` identifies a positive response; HTTP `400` identifies a request error.", rendered)
        self.assertIn("**Error payload:** JSON normally includes `result`, `errorcode`, `errormessage`, and, when supplied, `errordetails` and `timestamp`.", rendered)

    def test_strict_generation_rejects_an_incomplete_source_export(self) -> None:
        """A source-layout change cannot silently omit API reference pages."""
        generator = self.load_generator()
        source_html = '<a name="Quotation"></a><div><h3 class="page-title">Quotation</h3></div>'

        with tempfile.TemporaryDirectory() as temporary_directory:
            temporary = Path(temporary_directory)
            source = temporary / "source.html"
            source.write_text(source_html, encoding="utf-8")

            with self.assertRaisesRegex(ValueError, "missing expected sections: ApiKeyInfo"):
                generator.generate(
                    source,
                    temporary / "dvaexpress-doc.html",
                    temporary / "dvaexpress",
                    require_complete=True,
                )


if __name__ == "__main__":
    unittest.main()
