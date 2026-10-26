# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this repo is

BootZen is a boilerplate for monolithic PHP MVPs designed to be built with
generative AI. Full repo guide (layout, commands, release, conventions):
@AGENTS.md

Quick reference:

- `framework/`: framework source (namespace `BootZen\`), copied as-is into
  every generated project. Own autoloader in `framework/bootstrap.php`.
- `skeleton/`: the boilerplate a new project starts from (namespace `App\`).
- `bin/bootzen-new`: creates a project from `skeleton/` + `framework/`.
- `composer check` at the repo root runs format check, PHPStan level 8 and Pest.
- Roadmap: `plan/04_PLAN-MVP-MONOLITO.md`. Progress: `plan/03_SEGUIMIENTO.md`.

## graphify

This project has a knowledge graph at graphify-out/ with god nodes, community structure, and cross-file relationships.

Rules:
- For codebase questions, first run `graphify query "<question>"` when graphify-out/graph.json exists. Use `graphify path "<A>" "<B>"` for relationships and `graphify explain "<concept>"` for focused concepts. These return a scoped subgraph, usually much smaller than GRAPH_REPORT.md or raw grep output.
- If graphify-out/wiki/index.md exists, use it for broad navigation instead of raw source browsing.
- Read graphify-out/GRAPH_REPORT.md only for broad architecture review or when query/path/explain do not surface enough context.
- After modifying code, run `graphify update .` to keep the graph current (AST-only, no API cost).
