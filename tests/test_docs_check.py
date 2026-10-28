"""Regression coverage for the agent-docs checker."""

from __future__ import annotations

import importlib.util
import subprocess
import tempfile
import unittest
from pathlib import Path


REPOSITORY = Path(__file__).resolve().parents[1]
CHECKER = REPOSITORY / "scripts" / "docs_check.py"


def load_checker():
    specification = importlib.util.spec_from_file_location("docs_check", CHECKER)
    assert specification and specification.loader
    checker = importlib.util.module_from_spec(specification)
    specification.loader.exec_module(checker)
    return checker


class DocsCheckTest(unittest.TestCase):
    def make_repo(self, files: dict[str, str]) -> Path:
        root = Path(tempfile.mkdtemp())
        for name, content in files.items():
            (root / name).parent.mkdir(parents=True, exist_ok=True)
            (root / name).write_text(content)
        subprocess.run(["git", "init", "-q"], cwd=root, check=True)
        subprocess.run(["git", "add", "-A"], cwd=root, check=True)
        return root

    def test_a_consistent_tree_passes(self) -> None:
        root = self.make_repo({
            "app/Shipping/ShippingService.php": "<?php // see .ai/rules/shipping.md",
            ".ai/rules/shipping.md": "---\npaths:\n  - app/Shipping/**\n---\n# Shipping\n\n- `app/Shipping/ShippingService.php:12`\n- `app/Shipping/Providers/<Name>/`\n",
        })

        self.assertEqual(load_checker().check(root), [])

    def test_dead_globs_braces_missing_paths_and_moved_docs_fail(self) -> None:
        root = self.make_repo({
            "app/Support/Vat.php": "<?php // docs/admin-settings.md",
            ".ai/rules/checkout.md": "---\npaths:\n  - app/Checkout/**\n  - app/Services/{A,B}.php\n---\n# Checkout\n\nSee `app/Services/CheckoutService.php` and [plan](../../docs/plan.md).\n",
            ".ai/rules/nofrontmatter.md": "# Missing paths\n",
        })

        errors = "\n".join(load_checker().check(root))

        self.assertIn("glob `app/Checkout/**` matches no tracked file", errors)
        self.assertIn("uses braces", errors)
        self.assertIn("mentions `app/Services/CheckoutService.php`", errors)
        self.assertIn("links to ../../docs/plan.md", errors)
        self.assertIn("nofrontmatter.md: missing `paths:` frontmatter", errors)
        self.assertIn("app/Support/Vat.php: refers to docs/admin-settings.md", errors)

    def test_area_docs_over_the_line_cap_fail(self) -> None:
        root = self.make_repo({
            "app/x.php": "<?php",
            ".ai/rules/long.md": "---\npaths:\n  - app/**\n---\n" + "line\n" * 130,
        })

        self.assertTrue(any("over the 120-line cap" in e for e in load_checker().check(root)))


if __name__ == "__main__":
    unittest.main()
