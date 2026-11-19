# Changelog

## 2.8 — 2026-11-16

- Add unit tests
- Cope with methods without any instructions (abstract way)

## 2.7 — 2026-11-09

- var_export already escape string value, so don't do it before
- Fix date format in _define.php and dcstore.xml

## 2.6 — 2026-11-07

- Version de maintenance, sans changement fonctionnel notable relevé dans l’historique.

## 2.5 — 2026-09-27

- Cope with `// @phpcode-ignore` as `// @phpcode-remove`
- Enforces some verification before registering module

## 2.4 — 2026-08-03

- Version de maintenance, sans changement fonctionnel notable relevé dans l’historique.

## 2.3 — 2026-04-15

- Version de maintenance, sans changement fonctionnel notable relevé dans l’historique.

## 2.2 — 2026-03-25

- DA -&gt; DC

## 2.1 — 2025-09-12

- Replace static call to Ctx by context() — thanks JcDenis for advice

## 2.0 — 2025-09-07

- DC 2.36 compliance

## 1.8 — 2025-04-16

- Adds the _CODE suffix for replacement variables to be replaced directly (as for the _HTML suffix)

## 1.7 — 2025-04-16

- Add a specific management for unwanted lines (ended with // @phpcode-ignore)
- Fix documentation for filter's params and current template tag, if used
- Code and doc review

## 1.6 — 2025-03-18

- Adjust plugin permissions

## 1.5 — 2025-03-17

- Cope with template code including HTML parts (using replacement variables ending with _HTML) Add two specific methods for template value (getPHPTemplateValueCode()) and template block (getPHPTemplateBlockCode()) The returned code is now encapsulated by default with start/end PHP tags (&lt;?php and ?&gt;)

## 1.4 — 2025-03-17

- Add a template specific helper (value/block) — experimental

## 1.3 — 2025-03-16

- Cope with malformed $method argument

## 1.2 — 2025-03-16

- No need to cope with variables replacements if no values given

## 1.1 — 2025-03-16

- Be more specific on error message (exception or not): add the name of the given method
- Be more specific about looking for variables to replace (avoid looking for variable beginning with given name, ex: $myvar and $myvartwo)
- Add some documentation

## 1.0 — 2025-03-15

- Rename CoreHelper.php to Code.php
- Add documentation in README.md
- Add getPHPCode method
- Add minimal src files
- 1st commit
