#!/usr/bin/env python3
"""Checks that agent docs still match the tree. Run from the repo root: python3 scripts/docs_check.py

- Every area doc in .ai/rules/ has `paths:` frontmatter, each glob matches a tracked file, and it stays under the line cap.
- Every repo path a doc mentions (backticked or as a relative Markdown link) exists.
- Every doc path mentioned from code (docs/... or .ai/....md) exists, so moving a doc breaks CI instead of rotting.
"""

from __future__ import annotations

import re
import subprocess
import sys
from pathlib import Path

MAX_AREA_DOC_LINES = 120
TOP_DIRS = r"(?:app|bootstrap|config|database|docker|docs|lang|public|resources|routes|scripts|tests|\.ai|\.github)"
BACKTICK_PATH = re.compile(r"`(" + TOP_DIRS + r"/[^\s`]+)`")
MARKDOWN_LINK = re.compile(r"\]\(([^)\s#]+)(?:#[^)]*)?\)")
DOC_REFERENCE_IN_CODE = re.compile(r"((?:docs|\.ai)/[\w./-]+\.md)")
CODE_DIRS = ("app", "bootstrap", "config", "database", "resources/js", "routes", "tests", "scripts", ".github")


def tracked_files(root: Path) -> list[str]:
    out = subprocess.run(["git", "ls-files"], cwd=root, capture_output=True, text=True, check=True).stdout
    return [line for line in out.splitlines() if (root / line).exists()]


def glob_matches(root: Path, pattern: str) -> bool:
    out = subprocess.run(["git", "ls-files", f":(glob){pattern}"], cwd=root, capture_output=True, text=True, check=True).stdout
    return out.strip() != ""


def frontmatter_paths(text: str) -> list[str] | None:
    """The `paths:` list of a rule file, or None when the file has no frontmatter."""
    match = re.match(r"---\n(.*?)\n---\n", text, re.S)
    if not match:
        return None

    return [m.strip().strip("'\"") for m in re.findall(r"^\s*-\s*(.+)$", match.group(1), re.M)]


def mentioned_paths(text: str) -> list[str]:
    """Repo paths a doc mentions in backticks: globs and <placeholders> are skipped, a :line suffix is dropped."""
    found = []
    for raw in BACKTICK_PATH.findall(text):
        if any(char in raw for char in "*<>{}$"):
            continue
        found.append(re.sub(r":[\d-]+$", "", raw.rstrip(".,;:)")))

    return found


def check(root: Path) -> list[str]:
    errors: list[str] = []
    files = tracked_files(root)
    rules_dir = root / ".ai" / "rules"

    for doc in sorted(rules_dir.glob("*.md")) if rules_dir.is_dir() else []:
        if doc.name == "index.md":
            continue
        name = doc.relative_to(root).as_posix()
        text = doc.read_text()
        globs = frontmatter_paths(text)
        if not globs:
            errors.append(f"{name}: missing `paths:` frontmatter")
        for pattern in globs or []:
            if "{" in pattern:
                errors.append(f"{name}: glob `{pattern}` uses braces, which git pathspecs do not expand")
            elif not glob_matches(root, pattern):
                errors.append(f"{name}: glob `{pattern}` matches no tracked file")
        if text.count("\n") > MAX_AREA_DOC_LINES:
            errors.append(f"{name}: {text.count(chr(10))} lines, over the {MAX_AREA_DOC_LINES}-line cap")

    docs = [f for f in files if f.endswith(".md") and (f.startswith(".ai/") or f.startswith("docs/"))]
    docs = [f for f in docs if not f.startswith(("docs/vendor/", ".ai/rules/boost/")) and f != ".ai/rules/index.md"]
    for name in docs:
        text = (root / name).read_text()
        for path in mentioned_paths(text):
            if not (root / path).exists():
                errors.append(f"{name}: mentions `{path}`, which does not exist")
        for link in MARKDOWN_LINK.findall(text):
            if "://" in link or link.startswith("mailto:"):
                continue
            if not ((root / name).parent / link).exists():
                errors.append(f"{name}: links to {link}, which does not exist")

    for name in files:
        if not name.startswith(CODE_DIRS) or name.endswith(".md") or name.startswith("tests/fixtures"):
            continue
        try:
            text = (root / name).read_text()
        except (UnicodeDecodeError, IsADirectoryError):
            continue
        for path in set(DOC_REFERENCE_IN_CODE.findall(text)):
            if not (root / path).exists():
                errors.append(f"{name}: refers to {path}, which does not exist")

    return errors


def main() -> int:
    root = Path(subprocess.run(["git", "rev-parse", "--show-toplevel"], capture_output=True, text=True, check=True).stdout.strip())
    errors = check(root)
    for error in errors:
        print(error)
    print(f"docs_check: {len(errors)} problem(s)")

    return 1 if errors else 0


if __name__ == "__main__":
    sys.exit(main())
