---
name: create-ticket
description: Guides writing well-structured tickets and issues for Folio. Covers title format, acceptance criteria, and technical notes. Use when creating issues, tasks, or feature requests.
disable-model-invocation: true
---

## Template

```
Title: [Imperative verb] [concise description] (under 80 chars)

## Description
What needs to happen and why. One or two sentences.

## Acceptance Criteria
- [ ] [Specific, testable criterion]
- [ ] Audit log entries created for state changes
- [ ] At least one test covers the feature
- [ ] docker compose up still works from fresh clone

## Technical Notes
- Files to modify: [list]
- Schema changes needed: [yes/no, describe]
- New migration file: [filename]

## Estimate
S (< 1 hour) / M (1-3 hours) / L (3+ hours)
```

## Good vs Bad

**Good title:** "Add scheduled publishing to document creation"
**Bad title:** "Implement the scheduling feature for documents so users can set publish dates"

**Good criteria:** "Documents table has a `publish_at` column (nullable TEXT)"
**Bad criteria:** "Feature works correctly"
