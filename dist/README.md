# `dist/` — table templates for a new SionModel project

These four files are the DDL a new project copies to create the tables SionModel
expects. `project_` is a placeholder: the real table names come from
`sionmodel.global.php` (`changes_table`, `visits_table`, `problems_table`) and from
the entity specs in each module's `module.config.php`.

## Charset and collation

All four create tables as:

```
ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci
```

They previously said `CHARSET=utf8`, which is an alias for `utf8mb3` — a charset
MySQL has deprecated and which cannot represent anything outside the Basic
Multilingual Plane. Any project provisioned from these templates would have
inherited that.

`utf8mb4_unicode_520_ci` is chosen because it is the newest Unicode Collation
Algorithm available across both engines this library is expected to run on:

- MySQL 8's default `utf8mb4_0900_ai_ci` (UCA 9.0.0) does not exist on MariaDB.
- MariaDB's `utf8mb4_uca1400_*` family (UCA 14.0.0) does not exist before
  MariaDB 11 — MariaDB 10.11 LTS has none of them.
- `utf8mb4_unicode_520_ci` (UCA 5.2.0) exists on both, and unlike
  `utf8mb4_general_ci` it is a real UCA collation: it correctly equates
  `straße`/`strasse`, `Æ`/`AE`, `Œ`/`OE` and `ﬁ`/`fi`. The older
  `utf8mb4_unicode_ci` (UCA 4.0.0) gets `Æ`/`AE` wrong.

One caveat worth knowing: every pre-`uca1400` collation is **PAD SPACE**, meaning
trailing spaces are ignored in comparison. The modern families on both engines
(`0900_*`, `uca1400_*`) are **NO PAD**. That semantic will change once more
whenever a project moves to MariaDB 11 or MySQL 8 and adopts their native
collation — it is not specific to this choice.

## The connection charset matters too

Storing `utf8mb4` is only half of it. A connection opened with `SET NAMES utf8`
is a `utf8mb3` connection, and the server will silently convert 4-byte characters
down to `?` on the way out. Set the driver's init command to
`SET NAMES utf8mb4` in the consuming application's database config.
