Final phase: ship, generation 1, call 2, used 2 of fixed maximum 3. One full test pass (265 tests, 1676 assertions) and one build for the frozen candidate. All acceptance met; no blockers. Parent verified immutable source, context, tombstones, delivery and exact reviewer runtime before closure.

Proposed improvements (advisory only):
- Keep obsolete refresh routes covered by explicit 404 regression tests.
- Preserve rendered-script mutation checks for notice lifecycle changes.
- Keep login-only code hashes in cleanup review packets.

No workflow rules changed.
