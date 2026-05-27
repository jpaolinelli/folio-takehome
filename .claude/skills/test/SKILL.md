---
name: test
description: Runs the Folio test suite inside the Docker container and reports results. Use when the user asks to run tests, check if tests pass, or verify changes.
disable-model-invocation: true
allowed-tools: Bash(docker compose exec *)
---

Run the Folio test suite:

```bash
docker compose exec app php tests/test.php
```

If tests fail, read the failing test in `tests/test.php`, identify the assertion that failed, and suggest a fix. Do not modify the test without asking first.
