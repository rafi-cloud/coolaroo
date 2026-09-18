# CLAUDE.md

Project rules are in **[AGENTS.md](AGENTS.md)**. Read that first, then `docs/PROGRESS.md`.

Claude Code specifics:
- Use **Plan mode** (mode indicator at the bottom of the prompt box). I read the plan and type the code myself; do not apply edits.
- Use **Sonnet** for routine tasks; switch to a stronger model only for hard debugging.
- I run `/clear` between tasks; do not carry earlier task context forward.
- Use `/compact` if a single task session gets long.
