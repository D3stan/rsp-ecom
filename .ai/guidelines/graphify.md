# graphify

This project has a knowledge graph at `graphify-out/` (classes, call paths, communities, cross-file relationships).

- For a codebase question, run `graphify query "<question>"` before your first grep, search or source-file read, and put this rule in every subagent prompt that explores code. Read raw files only for lines the graph or an earlier read already located.
- Use `graphify path "<A>" "<B>"` for relationships and `graphify explain "<concept>"` for focused concepts. Read `graphify-out/GRAPH_REPORT.md` only for broad architecture reviews.
- After modifying code, run `graphify update .` (AST-only, no API cost). If it refuses because the graph shrank (files were deleted), run `graphify update . --force`.
- `graphify-out/graph.json` is rewritten whole on every build, so a large diff to it is expected. Do not flag it as churn.
