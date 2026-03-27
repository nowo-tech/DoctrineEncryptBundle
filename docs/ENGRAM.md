# Engram — AI persistent memory in this repository

This repository is **prepared to use Engram** with Cursor (and other MCP-compatible editors). The configuration is already present so that once you install the Engram CLI, your AI agent can use persistent memory across sessions.

## What is Engram?

**Engram** is an [MCP (Model Context Protocol)](https://modelcontextprotocol.io/) server that gives AI coding agents (Cursor, Claude Code, etc.) **persistent memory**. It stores context in a local vault (SQLite) so the agent does not need to re-discover project structure and conventions in every session.

Capabilities include:

- **Remember** — Store project notes, decisions, and context.
- **Recall** — Retrieve relevant memories using semantic search.
- **Briefing** — Get a structured context summary at session start.
- **Consolidate** — Distill recent episodes into long-term knowledge.
- **Entities & stats** — Inspect the knowledge graph and vault statistics.

All data stays local; no code or secrets are sent to external services except optional embeddings (see [Engram documentation](https://www.engram.fyi/docs)).

## Repository setup

In the **root of this repository** you will find:

- **`.cursor/mcp.json`** — MCP configuration that registers the Engram server with Cursor.

Content:

```json
{
  "mcpServers": {
    "engram": {
      "command": "engram",
      "args": ["mcp"]
    }
  }
}
```

Cursor uses this to start the Engram MCP server when needed (via the `engram mcp` command). No need to edit this file unless you want to change the server name or arguments.

## How to install Engram

You need the **Engram CLI** (`engram`) on your machine so Cursor can run `engram mcp`.

### Option 1: npm (recommended)

```bash
npm install -g engram-sdk
engram init
```

`engram init` will detect your editor, prompt for a Gemini API key (for embeddings and consolidation; free at [Google AI Studio](https://aistudio.google.com/apikey)), and create your vault. You can skip or complete init; the MCP will work as long as the `engram` binary is in your `PATH`.

### Option 2: Homebrew

```bash
brew tap tstockham96/engram
brew install engram-sdk
engram init
```

### Verify

Ensure `engram` is available:

```bash
engram --help
engram mcp   # Starts the MCP server (stdio); Cursor runs this automatically
```

## How to use

1. **Install** the Engram CLI as above and run `engram init` if you want to set up the vault and Gemini key.
2. **Restart Cursor** (or your MCP client) so it loads `.cursor/mcp.json`.
3. **Use the agent** as usual. When the Engram MCP is connected, the agent can use tools such as:
   - `engram_remember` — Store a memory (episodic, semantic, or procedural).
   - `engram_recall` — Retrieve relevant memories for the current context.
   - `engram_briefing` — Get a context summary for the session.
   - `engram_consolidate` — Run consolidation (distill episodes into knowledge).
   - `engram_surface`, `engram_connect`, `engram_forget`, `engram_entities`, `engram_stats`, `engram_ingest` — See [Engram docs](https://www.engram.fyi/docs) for details.

No extra steps are required in this repo: open the project in Cursor, ensure `engram` is installed and on your `PATH`, and the agent can use Engram when the MCP server is enabled.

## Clearing or resetting the vault

- **Remove a single memory:** Use the agent tool `engram_forget` with the memory id (from recall/stats), or from the CLI: `engram forget <id>` (soft delete) or `engram forget <id> --hard` (permanent). You can get ids via `engram recall <context>` or `engram stats` (and the agent can use `engram_entities` / `engram_stats`).
- **Empty the vault completely:** The vault is a local SQLite file. Default location: `~/.engram/default.db` (or the path set in `ENGRAM_DB`). To wipe all memories, delete that file or the whole directory:
  ```bash
  rm -f ~/.engram/default.db
  # or, to remove the whole vault directory:
  rm -rf ~/.engram
  ```
  After that, the next time you use Engram a new vault will be created. You can run `engram init` again if you want to reconfigure the Gemini key or editor.

## References

- [Engram documentation](https://www.engram.fyi/docs) — Full MCP tools, REST API, CLI, and configuration.
- [Model Context Protocol (MCP)](https://modelcontextprotocol.io/) — Protocol used by Cursor and other clients to talk to Engram.
