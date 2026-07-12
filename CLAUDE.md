# CLAUDE.md — `laravel-modules/module-generator`

Primary technical reference for anyone (human or AI) working on this package. It reflects the
**actual implementation** in `src/`, not aspirational design. Where the README and the code
disagree, this document follows the code and calls out the discrepancy.

> **Read this first — what this package is _not_.** Despite the name and the "Laravel" branding,
> this package ships **no service provider, no Artisan commands, no config file, no facade, no
> contracts/traits, and no bundled stub files.** It is a small, framework-agnostic PHP **library**
> of fluent helper objects that you instantiate directly (`new Generator`). It depends only on
> `illuminate/support` (for `Str`/`str()`), and optionally touches full-Laravel APIs in exactly
> one method. Do not go looking for `ServiceProvider`, `config/`, `stubs/`, or `Commands/` — they
> do not exist here. The stubs and the Artisan command in the README live in the **consuming
> application**, not in this package.

---

## Package Overview

**Purpose.** Programmatically scaffold parts of an application ("modules") by copying a tree of
template files ("stubs") into a target project, while rewriting file/directory names and file
contents via simple search→replace maps. It also provides focused helpers for the common
side-tasks that scaffolding requires: editing `composer.json`, editing `.env`/`.env.example`,
editing arbitrary text files, and generating a full CRUD from a naming convention.

**Problem it solves.** Starter-kit / boilerplate authors repeatedly need to: copy template files
into a new project, replace placeholders (e.g. `Crud` → `Category`), append packages to
`composer.json`, set env values, register a service provider, and splice snippets into existing
files (routes, sidebars, seeders). Doing this by hand with `str_replace`/`file_put_contents` is
error-prone. This package packages those operations behind a chainable, intention-revealing API.

**Intended users.** Authors of Laravel starter kits, project scaffolders, and internal
"generate a new feature" tooling. Typically invoked from a custom Artisan command that the
*consumer* writes (see the README's `MakeCrudCommand` example), or from a post-install script.

**Overall workflow.**
1. The consumer keeps a directory of stub files in their own project.
2. They instantiate `new Generator`.
3. They call `publish()` (raw copy+replace) or `crud()` (convention-driven copy+replace), and/or
   the `composer()` / `environment()` / `file()` helpers to adjust project files.
4. Each helper **buffers changes in memory** and only writes to disk when `publish()` is called
   (except `Generator::publish()`, which copies eagerly — see the discrepancy note below).

**Key concepts.**
- **Stub**: a template file (any content, any extension) that lives in the consumer's project.
- **Replacement map**: an ordered `['search' => 'replacement']` array applied to file names and/or
  file contents via `str_replace`.
- **Placeholder**: a search token inside stubs. Convention for CRUD stubs is
  `__CRUD_<CASE>_<NUMBER>__` (e.g. `__CRUD_STUDLY_SINGULAR__`).
- **Publish**: the act of writing buffered/copied changes to disk.

---

## High-Level Architecture

Five plain classes in a single namespace `LaravelModules\ModuleGenerator`. There is no container
wiring, no auto-discovery, no inheritance hierarchy beyond `Environment extends File`.

```
Generator ─────────────── entry point / façade / factory
  ├─ publish()             recursive copy + name/content replacement
  ├─ getReplacementsFor()  builds the CRUD casing table from a name
  ├─ registerServiceProvider()  (the only full-Laravel-dependent method)
  ├─ composer()  ──────►  new Composer  (array editor for composer.json)
  ├─ environment() ────►  new Environment ──extends──► File
  ├─ file()      ──────►  new File       (buffered text editor)
  └─ crud()      ──────►  new CRUD        (builder that composes the above)

CRUD ──holds──► Generator          (calls back into Generator::publish + Generator::file)
Environment ──is-a──► File         (adds .env-aware set())
```

**Components & responsibilities.**

| Class | Responsibility |
|-------|----------------|
| `Generator` | Public entry point. Recursive stub copying, CRUD casing table, service-provider registration, and factory for the other helpers. |
| `File` | Buffered, chainable editing of a single text file (`append`/`prepend`/`appendAfter`/`prependBefore`/`replace`), written on `publish()`. |
| `Environment` | `File` subclass adding `.env`/`.env.example`-aware `set(key, value)` upsert semantics. |
| `Composer` | Array-backed editor for `composer.json` (merge/remove require, require-dev, scripts, autoload files, dont-discover), written on `publish()`. |
| `CRUD` | Fluent builder that derives a full replacement map from a name and delegates the actual copy to `Generator::publish()`. |

**Relationships.** `Generator` is the only class a consumer normally constructs. It creates and
returns the others. `CRUD` holds a back-reference to its `Generator` (set via `setGenerator()`)
and calls `Generator::publish()` and `Generator::file()`. `Environment` extends `File` and reuses
its buffering and the shared `replaceFirst()` helper.

**Execution flow (typical CRUD run).**
`crud(name)` → builds replacement map via `getReplacementsFor()` → consumer sets `fromPath`/`toPath`
→ optional `appendToFile()` → `publish()` → prepends `.stub`→`''` and a migration-timestamp
replacement → `Generator::publish()` recurses the stub tree, renaming and rewriting each file.

---

## Directory Structure

```
module-generator/
├── src/                  # The entire library (PSR-4: LaravelModules\ModuleGenerator\ → src/)
│   ├── Generator.php     # Entry point / façade / factory
│   ├── File.php          # Buffered text-file editor
│   ├── Environment.php   # File subclass with .env set()
│   ├── Composer.php      # composer.json array editor
│   └── CRUD.php          # CRUD replacement-map builder
├── tests/                # PHPUnit suite (PSR-4: ...\Tests\ → tests/)
│   ├── TestCase.php      # Base case: per-test temp dir + fs helpers
│   ├── FileTest.php
│   ├── EnvironmentTest.php
│   ├── ComposerTest.php
│   ├── GeneratorTest.php
│   └── CrudTest.php
├── composer.json         # Package metadata + dev tooling
├── phpunit.xml           # PHPUnit config (failOnDeprecation/failOnWarning on)
├── README.md             # User-facing usage guide
├── PACKAGE_REVIEW.md     # Audit report from the last hardening pass
├── LICENSE               # MIT
├── .gitattributes        # export-ignore dev files from dist archives
└── .gitignore
```

There is intentionally **no** `config/`, `stubs/`, `src/Commands/`, `src/Providers/`, or
`resources/` directory. Everything the package does is driven by arguments the caller supplies.

---

## Internal Workflow

There is no framework bootstrap, config loading, or command dispatch inside this package. The
"workflow" is whatever sequence of method calls the consumer makes. The two file-writing engines
are `Generator::publish()` (copy engine) and the `File`/`Composer` `publish()` methods (buffer
engine).

### `Generator::publish()` — the copy engine
Signature: `publish(string $from, ?string $to = null, array $filesNameReplacement = [], array $filesContentReplacement = []): self`

1. If `$to` is null/empty, default to `getBasePath()`.
2. If `$from` is not a directory, **return early silently** (no error).
3. Create `$to` if missing (`mkdir(..., 0755, true)`).
4. Pre-compute the name/content search+replace arrays once (outside the loop).
5. `scandir($from)`, skipping `.`/`..`. For each entry:
   - Compute the destination path and apply the **name** replacement map to it via `str_replace`.
   - If it is a directory → recurse (name replacements apply to directory names too).
   - Otherwise → `copy()` the file, then if a **content** replacement map was given, read the copied
     file, `str_replace` its contents, and write it back.

Key properties: replacements are plain `str_replace` (not regex), applied in map order; the same
map is typically passed for both names and contents; missing sources are ignored, not errored.

### `File` / `Environment` — the buffer engine
1. `setPath($path)` calls `setContent()`, which reads the file if it exists, else sets content to
   `''`. **It does not create anything on disk at this point.**
2. Mutators (`append`, `prepend`, `appendAfter`, `prependBefore`, `replace`, and `Environment::set`)
   mutate the in-memory `$content` string and return `$this`.
3. `publish()` creates the parent directory if needed (`0755`) and writes `$content` to disk.

### `Composer` — the array engine
1. `setPath($path)` → `setContent()` reads and `json_decode`s the file; **throws `\Exception`** if
   the path is missing or the JSON is invalid. It snapshots both `$originalComposer` and `$composer`.
2. Mutators edit the `$composer` array and return `$this`.
3. `publish()` re-encodes with `JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`
   and writes it back with a trailing newline.

### Extension points (as they exist today)
- Supply arbitrary replacement maps to `publish()`.
- Pass custom `$replacements` to `crud()` / `CRUD::appendReplacements()` (they take precedence — see
  the ordering note under Public API).
- Subclass `File` the way `Environment` does to add file-type-specific operations.
- Subclass `Generator` to override `getReplacementsFor()`, `getBasePath()`, or the publish loop.

---

## Package Components

### `Generator` (`src/Generator.php`)
- **Purpose:** Entry point, copy engine, factory, CRUD casing table.
- **Collaborators:** creates `Composer`, `Environment`, `File`, `CRUD`.
- **Lifecycle:** stateless; construct once with `new Generator`, reuse freely.
- **Important methods:**
  - `publish(from, to, filesNameReplacement, filesContentReplacement): self` — the copy engine (above).
  - `composer(): Composer` — returns a `Composer` bound to `<base>/composer.json`. `@throws \Exception`
    if that file is missing/invalid.
  - `environment(envFile = '.env.example'): Environment` — bound to `<base>/<envFile>`.
  - `file(path): File` — bound to the given absolute path.
  - `crud(name, replacements = []): CRUD` — builds a `CRUD` pre-loaded with
    `replacements + getReplacementsFor(name)`.
  - `getReplacementsFor(name): array` — see Stub System; the 18-entry CRUD casing table.
  - `registerServiceProvider(provider): self` — the only method needing a booted Laravel app.
  - `getBasePath(): string` (protected) — `base_path()` if the helper exists, else
    `explode('/vendor', __FILE__)[0]` (fragile fallback for non-Laravel/non-vendor layouts).
  - `isLaravelTenOrLower(): bool` (protected) — `version_compare(Application::VERSION, '11.0.0', '<')`.

### `File` (`src/File.php`)
- **Purpose:** buffered editing of one text file.
- **Lifecycle:** `new File` → `setPath()` (reads content) → mutate → `publish()` (writes).
- **State:** `protected string $path`, `protected string $content = ''`.
- **Important methods:**
  - `setPath(path): self` — sets path and reads current content (missing = empty, no disk write).
  - `append(content): self` / `prepend(content): self` — add on a new line; **idempotent** (skips if
    the content already appears anywhere in the file, via `exists()`).
  - `appendAfter(search, content): self` — insert after the **first** line starting with `search`
    (anchored `^`, `preg_quote`d); falls back to `append()` if not found. Idempotent.
  - `prependBefore(search, content): self` — mirror of the above; falls back to `prepend()`.
  - `replace(search, replace): self` — plain `str_replace` over the buffer (not idempotent).
  - `getContent(): string`, `publish(): void`.
  - `exists(content): bool` (protected) — `str_contains($this->content, $content)`.
  - `replaceFirst(search, replace, subject): string` (protected) — single-occurrence replace via
    `strpos`/`substr_replace`; shared with `Environment`.
- **Note:** `append`/`prepend`/`appendAfter`/`prependBefore` short-circuit if the content already
  exists — designed for repeatable scaffolding. `replace()` does not, by design.

### `Environment` (`src/Environment.php`) — `extends File`
- **Purpose:** `.env`/`.env.example` upsert.
- **Only method:** `set(key, value = ''): self` where `value` is `string|float|null`.
  - Casts value to string, wraps it in double quotes if it contains a space.
  - If a line matching `^KEY=` exists, replaces that first line; otherwise appends `KEY=value`.
  - Key is `preg_quote`d and the `=` is required, so `APP_NAME` will **not** clobber `APP_NAME_SUFFIX`.

### `Composer` (`src/Composer.php`)
- **Purpose:** structured editing of `composer.json`.
- **Lifecycle:** `new Composer` → `setPath()` (reads+decodes, throws on error) → mutate → `publish()`.
- **State:** `$path`, `$composer` (working copy), `$originalComposer` (snapshot).
- **Important methods:** `mergeRequire`, `mergeRequireDev`, `removePackages` (from both require and
  require-dev), `dontDiscover` (dedup), `mergeAutoloadFiles` (dedup), `mergeScripts`, `removeScripts`,
  `getContent`, `getOriginalContent`, `publish`.

### `CRUD` (`src/CRUD.php`)
- **Purpose:** builder that turns a name + stub directory into a generated CRUD.
- **Collaborators:** holds a `Generator` (via `setGenerator()`); delegates to `Generator::publish()`
  and `Generator::file()`.
- **State:** `$name`, `$fromPath`, `$toPath`, `$replacements`, `$generator`.
- **Important methods:**
  - `fromPath(path): self` / `toPath(path): self` — source stub dir / destination root.
  - `setReplacement(array): self` — replace the whole map (used by `Generator::crud`).
  - `appendReplacements(array): self` — merge extra replacements.
  - `appendToFile(file, content, before = ''): self` — apply the CRUD replacements to `content`, then
    `prependBefore($before, ...)` it into `file` and publish immediately.
  - `publish(): void` — prepends two special replacements (`.stub`→`''` and the migration-timestamp
    rewrite) ahead of the CRUD map, then calls `Generator::publish()` for both name and content.

---

## Stub System

**Where stubs live.** In the **consuming project**, not in this package. There are no bundled stubs.
The consumer points `Generator::publish(from: ...)` or `CRUD::fromPath(...)` at their own directory.

**How stubs are resolved.** By plain filesystem recursion. `Generator::publish()` walks the `from`
directory with `scandir` and mirrors the tree into `to`. There is no manifest, registry, or
namespacing — the directory layout *is* the manifest.

**Publish process.** For each file: destination path = `str_replace(names, to/...)`; directories
recurse; files are `copy()`d then content-rewritten if a content map was supplied.

**Placeholder replacement.** Two independent maps: `filesNameReplacement` (applied to paths) and
`filesContentReplacement` (applied to contents). Both are ordered `str_replace` — **order matters**
and there is no escaping/word-boundary logic. For CRUD, the same map is used for both.

**CRUD placeholders (the casing table).** `getReplacementsFor($name)` first normalizes the name to a
lower-case space-separated phrase (`StudlyCase` → snake with spaces → lower), derives a `singular`
and `plural` `Stringable`, then builds 18 tokens. For input `UserCategory` (or `UserCategories`):

| Placeholder | Result |
|-------------|--------|
| `__CRUD_STUDLY_SINGULAR__` | `UserCategory` |
| `__CRUD_CAMEL_SINGULAR__` | `userCategory` |
| `__CRUD_TITLE_SINGULAR__` | `User Category` |
| `__CRUD_UCFIRST_SINGULAR__` | `User category` |
| `__CRUD_LOWER_SINGULAR__` | `user category` |
| `__CRUD_KEBAB_SINGULAR__` | `user-category` |
| `__CRUD_SNAKE_SINGULAR__` | `user_category` |
| `__CRUD_SNAKE_UPPER_SINGULAR__` | `USER_CATEGORY` |
| `__CRUD_PLAIN_SINGULAR__` | `usercategory` |
| `__CRUD_STUDLY_PLURAL__` | `UserCategories` |
| `__CRUD_CAMEL_PLURAL__` | `userCategories` |
| `__CRUD_TITLE_PLURAL__` | `User Categories` |
| `__CRUD_UCFIRST_PLURAL__` | `User categories` |
| `__CRUD_LOWER_PLURAL__` | `user categories` |
| `__CRUD_KEBAB_PLURAL__` | `user-categories` |
| `__CRUD_SNAKE_PLURAL__` | `user_categories` |
| `__CRUD_SNAKE_UPPER_PLURAL__` | `USER_CATEGORIES` |
| `__CRUD_PLAIN_PLURAL__` | `usercategories` |

Two implicit CRUD-only replacements are injected by `CRUD::publish()` **before** the casing table:
- `.stub` → `''` (so `Foo.php.stub` becomes `Foo.php`).
- `create___CRUD_SNAKE_PLURAL___table` → `<Y_m_d_His>_create___CRUD_SNAKE_PLURAL___table`
  (prefixes migration file names with a timestamp; the trailing `__CRUD_SNAKE_PLURAL__` is then
  resolved by the casing table that follows).

**Adding new stubs.** Just add files/directories under your stub dir, using the placeholders above in
both names and contents. Name a migration
`.../migrations/create___CRUD_SNAKE_PLURAL___table.php.stub` to get automatic timestamping.

**Best practices.**
- Suffix templates with `.stub` so `CRUD::publish()` strips it.
- Prefer more-specific placeholders before less-specific ones is unnecessary here (tokens are unique),
  but be careful when writing **custom** raw `publish()` maps where one search is a substring of another
  — `str_replace` runs in array order.
- Keep the stub directory tree identical to the desired output tree.

---

## Module Generation

There are two generation paths.

**1. Raw module (`Generator::publish`).**
- Pipeline: default `to` → guard missing `from` → ensure `to` → recurse copy with name/content maps.
- **Validation:** essentially none. Missing `from` is a silent no-op; there is no name validation,
  no collision detection, and existing destination files are **overwritten**.
- **Naming rules:** whatever your `filesNameReplacement` map dictates.
- **Folder creation:** destination and intermediate dirs are created at `0755`.
- **Generated files:** a 1:1 mirror of the stub tree with names/contents rewritten.

**2. CRUD (`Generator::crud` + `CRUD`).**
- Pipeline: `crud(name)` builds the casing map → `fromPath`/`toPath` → optional `appendToFile` →
  `publish()` injects `.stub`/migration replacements → `Generator::publish()` mirrors the tree.
- **Naming rules:** driven entirely by `getReplacementsFor()`; the input name is normalized so both
  `UserCategory` and `UserCategories` yield the same singular/plural set.
- **Configuration usage:** none — behavior is fully determined by constructor args and the stub tree.

---

## Configuration

**There is no configuration file and no publishable config.** All behavior is controlled by method
arguments:
- `Generator::publish` — `from`, `to`, `filesNameReplacement`, `filesContentReplacement`.
- `Generator::environment` — `envFile` (default `.env.example`).
- `Generator::crud` — `name`, `replacements`.
- `CRUD` — `fromPath`, `toPath`, replacement maps.

The effective "base path" for `composer()`/`environment()` comes from `getBasePath()`:
`base_path()` when running inside Laravel, otherwise a `__FILE__`-derived guess.

---

## Commands

**This package ships no Artisan commands and registers no service provider.** The `MakeCrudCommand`
in the README is an **example the consumer writes** in their own app; it is not part of the package
and is not autoloaded here. If you are extending this package, do not expect a `Commands/` directory —
adding one would be a new feature (see Future Improvements).

---

## Public API

All classes live in `LaravelModules\ModuleGenerator`. Everything below is intended for consumers.
All mutators are chainable (`return $this`) except the terminal `publish()` calls.

### `Generator`
| Method | Use when | Inputs | Output | Side effects |
|--------|----------|--------|--------|--------------|
| `publish($from, $to = null, $namesMap = [], $contentMap = [])` | copying a stub tree | dirs + `str_replace` maps | `self` | **copies/overwrites files on disk immediately** |
| `composer()` | editing composer.json | – | `Composer` | reads `<base>/composer.json`; throws if missing/invalid |
| `environment($envFile = '.env.example')` | editing env | filename | `Environment` | reads `<base>/<envFile>` (no write until `publish`) |
| `file($path)` | editing any text file | absolute path | `File` | reads file (no write until `publish`) |
| `crud($name, $replacements = [])` | convention-driven CRUD | name + extra map | `CRUD` | none until `CRUD::publish` |
| `getReplacementsFor($name)` | inspect/reuse casing table | name | `array` | none |
| `registerServiceProvider($provider)` | wiring a generated provider | FQCN (with/without `::class`) | `self` | **edits `config/app.php` (≤L10) or `bootstrap/providers.php` (≥L11)**; needs a booted app |

### `File`
`append`, `prepend`, `appendAfter($search,$content)`, `prependBefore($search,$content)`,
`replace($search,$replace)` → all `self`; `getContent(): string`; `publish(): void` (writes to disk,
creating dirs). Insertion methods are idempotent and anchor `$search` to line start.

### `Environment` (extends `File`)
`set($key, $value = '')` where value is `string|float|null`; upserts a `KEY=value` line, quoting
values that contain spaces. Then `publish()` to write.

### `Composer`
`mergeRequire`, `mergeRequireDev`, `removePackages`, `dontDiscover`, `mergeAutoloadFiles`,
`mergeScripts`, `removeScripts` → all `self`; `getContent(): array`, `getOriginalContent(): array`;
`publish(): void`. `setPath()`/construction throws `\Exception` on a missing or invalid file.

### `CRUD`
`fromPath`, `toPath`, `setReplacement`, `appendReplacements`, `appendToFile($file,$content,$before='')`
→ all `self`; `publish(): void`.

> **Replacement precedence caveat.** `Generator::crud($name, $replacements)` merges
> `[...$replacements, ...$this->getReplacementsFor($name)]`. Because PHP array-spread keeps the
> **last** value for duplicate string keys, the built-in casing tokens **win** over caller-supplied
> keys of the same name. Use custom keys that don't collide with `__CRUD_*__` if you need overrides.

---

## Extension Guide

The package has no formal extension interfaces; extend via composition and subclassing.

- **Add file-type helpers:** subclass `File` (as `Environment` does) and add operations that mutate
  `$this->content`; reuse the protected `replaceFirst()`. Wire it up with your own factory or extend
  `Generator` to add a `yourType()` factory method.
- **Add new stubs:** no code change needed — drop files into your stub tree using the placeholders.
- **Custom placeholders:** pass a map to `crud($name, $custom)` or `CRUD::appendReplacements()`
  (mind the precedence caveat), or pass arbitrary maps to `Generator::publish()` directly.
- **Override generation behavior:** subclass `Generator` and override `publish()`,
  `getReplacementsFor()`, or `getBasePath()`.
- **Adding a generator "type":** compose `Generator` inside your own class (like `CRUD` does) rather
  than bolting logic onto `Generator`.

Recommended extension points, in order of safety: (1) replacement maps, (2) new stub files,
(3) `File` subclasses, (4) `Generator` subclasses.

---

## Testing

**Framework:** PHPUnit 11 (`require-dev`), configured in `phpunit.xml` with `failOnWarning` and
`failOnDeprecation` **enabled** — deprecations fail the build, so keep the code deprecation-clean.

**Organization:** one test file per source class under `tests/`, PSR-4 `...\Tests\` → `tests/`.

**Base case (`tests/TestCase.php`):** each test gets a unique temp directory
(`sys_get_temp_dir()/module-generator-tests/<uniqid>`), auto-created in `setUp` and recursively
deleted in `tearDown`. Helpers: `path($rel)` (build a temp path), `makeFile($rel, $content)`
(create file + parents), `deleteDirectory()`. **Tests never touch the real project tree.**

**Conventions:**
- `snake_case` test method names describing behavior (`test_it_...`).
- Use `#[DataProvider]` **attributes**, not `@dataProvider` doc-comments (the latter is deprecated and
  would fail the suite).
- Assert on real filesystem results in the temp dir; assert exact string output where practical.

**Coverage today (58 tests / 73 assertions):** `File` semantics incl. first-match-only insertion and
no-disk-write-on-read; `Environment` upsert incl. null/numeric/spaced values and shared-prefix safety;
`Composer` merges/removes/dedup/throwing + JSON output shape; `Generator` recursive copy, name/content/
nested-dir replacement, factory types, and the full casing table; `CRUD` end-to-end generation,
migration timestamping, `.stub` stripping, and `appendToFile`.

**Adding tests:** create `tests/<Thing>Test.php extends TestCase`, build inputs with `makeFile`,
exercise the class, assert on temp-dir output. Cover new edge cases explicitly.

**Running:**
```shell
composer install
composer test          # alias for phpunit
vendor/bin/phpunit
```

`registerServiceProvider()` is **not** covered — it needs a booted Laravel app (see Known Limitations).

---

## Development Workflow

1. **Make the change** in `src/`, keeping to the existing style (the repo is auto-formatted in the
   Laravel/Pint idiom: `new Class` without parens, brace-on-same-line, short-array).
2. **Preserve the invariants:** buffer-then-`publish` for `File`/`Composer`; idempotent insertion
   methods; ordered `str_replace` semantics; silent no-op on missing `publish` source.
3. **Add/adjust tests** in `tests/` (temp-dir pattern). New behavior must have a test; bug fixes get
   a regression test.
4. **Run `composer test`** — it must be green with **zero** deprecations/warnings.
5. **Update docs:** README (user-facing) and this `CLAUDE.md` (internal). If you change behavior that
   the README documents, update both and note discrepancies here.
6. **Lint** the source with `php -l` at minimum; keep formatting consistent.

---

## Coding Conventions

- **Naming:** classes `StudlyCase` (note the outlier `CRUD` — all-caps acronym, inconsistent with PSR
  but kept for backward compatibility); methods `camelCase`; fluent mutators return `self`; terminal
  writers are named `publish()`.
- **Folder organization:** flat `src/` — one class per file, no sub-namespaces. Tests mirror sources.
- **Architecture decisions:** composition over inheritance (only `Environment extends File`); the
  `Generator` façade owns cross-cutting concerns and factories.
- **Error handling:** deliberately minimal. `Composer` throws `\Exception` on bad input; everything
  else favors silent no-ops (missing publish source, absent search line → fallback append/prepend).
  Return values are not checked for `copy`/`mkdir`/`file_put_contents`.
- **Filesystem usage:** native functions (`scandir`, `copy`, `mkdir`, `file_get_contents`,
  `file_put_contents`, `is_dir`, `is_file`) — no `Illuminate\Filesystem`. Directories created `0755`.
- **Dependency injection / container:** none. Objects are `new`-ed directly; `CRUD` receives its
  `Generator` via `setGenerator()`. Do not introduce container coupling — this library must run
  outside a booted Laravel app.
- **String manipulation:** `illuminate/support` `str()`/`Str` for casing; native `str_replace`/
  `preg_*` for content edits.

---

## Design Decisions

- **Library, not a Laravel package.** No service provider/commands/config keeps it usable from
  post-install scripts and non-Laravel contexts. Only `registerServiceProvider()` and the
  `getBasePath()` happy-path touch Laravel. **Do not** add mandatory framework coupling.
- **Stubs live in the consumer's project.** Maximum flexibility; the package stays generic. Changing
  this (bundling stubs) would be a major scope shift.
- **`str_replace`-based placeholders.** Simple and predictable; the cost is order-sensitivity and no
  word boundaries. Keep it unless a concrete need justifies regex/templating.
- **Buffer-then-publish for `File`/`Composer`.** Reads have no side effects; nothing hits disk until
  `publish()`. `Generator::publish()` is the deliberate exception — it copies eagerly because it is the
  bulk-copy engine. Preserve this split.
- **Idempotent insertion methods.** Scaffolding is expected to be re-runnable, so `append`/`prepend`/
  `appendAfter`/`prependBefore` skip content that already exists. `replace()` is intentionally not
  idempotent.
- **`Environment extends File`.** An env file *is* a buffered text file plus `set()`. Justified reuse.

---

## Maintenance Guide

- **Upgrading dependencies:** the only runtime dep is `illuminate/support` (`^10|^11|^12`). When a new
  Laravel major lands, verify `Str` casing helpers (`studly`/`snake`/`kebab`/`singular`/`plural`/
  `camel`/`title`/`ucfirst`/`upper`) still produce identical output — the casing table is asserted
  exactly in `GeneratorTest`, so a behavior change there will fail loudly. Bump `isLaravelTenOrLower`'s
  threshold logic only if the provider-registration APIs change.
- **Refactoring safely:** keep public signatures and the invariants in Design Decisions. Run the suite;
  it exercises the tricky edges (first-match insertion, shared-prefix env keys, dedup, JSON shape).
- **Backward compatibility:** treat every public method signature and the `__CRUD_*__` token set as
  public contract. Renaming `CRUD` or changing a token's output is a breaking change.
- **Adding features:** prefer composition (new class using `Generator`) or a `File` subclass over
  editing `Generator`'s core loop. Add tests first.
- **Avoiding regressions:** never let deprecations creep in (the suite fails on them); always add a
  regression test for a fixed bug; keep README and this file in sync with behavior.

---

## Known Limitations

Discovered in the implementation — not invented:

1. **No input validation.** `crud`/`publish` accept any name/path. Bad names produce bad output.
2. **Destination overwrites silently.** `Generator::publish()` overwrites existing files with no
   confirmation or backup.
3. **Missing source is a silent no-op.** `publish()` with a non-existent `from` returns without error,
   which can mask misconfiguration.
4. **Unchecked filesystem calls.** `copy`/`mkdir`/`file_put_contents` return values are ignored; a
   permission or disk error surfaces only as a PHP warning, not an exception.
5. **`str_replace` ordering/substring hazards.** Custom raw maps where one search is a substring of
   another can mis-replace; there are no word boundaries.
6. **Migration timestamp collisions.** `CRUD::publish()` uses `date('Y_m_d_His')`; two CRUDs generated
   within the same second get identical migration prefixes.
7. **`getBasePath()` fallback is fragile.** Outside Laravel and outside a `vendor/` layout,
   `explode('/vendor', __FILE__)[0]` returns the source path, not a project root.
8. **`registerServiceProvider()` requires a booted Laravel app** and is therefore untested here.
9. **`CRUD` class name** breaks PSR/Laravel casing convention (kept for BC).
10. **`appendToFile` publishes immediately**, unlike other `CRUD` builder methods that defer to
    `publish()` — a minor inconsistency in the builder's laziness.

---

## Future Improvements

Suggested only after reviewing the code. Prioritized.

**High priority**
- Cover `registerServiceProvider()` with an integration test (add `orchestra/testbench`), testing both
  the ≤L10 (`config/app.php`) and ≥L11 (`bootstrap/providers.php`) branches.
- Check filesystem return values and throw meaningful exceptions (or return a result) on failure, so
  silent no-ops (#3, #4) don't mask problems.
- Add a CI matrix (PHP 8.0–8.4 × Laravel 10/11/12) running `composer test`.

**Medium priority**
- Optional overwrite protection / dry-run mode for `Generator::publish()` (#2).
- Make CRUD migration timestamps collision-proof (microseconds or a counter) (#6).
- Require an explicit base path for non-Laravel consumers instead of the `__FILE__` guess (#7).
- Add static analysis (PHPStan/Larastan) and Pint config to lock in style and catch issues like the
  previously-undeclared property.

**Nice to have**
- Optional word-boundary / regex replacement mode to avoid substring hazards (#5).
- A thin, opt-in `ServiceProvider` + a generic `make:module`/`make:crud` command for Laravel users who
  want batteries included (keeping the core library framework-agnostic).
- Rename `CRUD` → `Crud` behind a class alias in a future major (#9).
- Make `CRUD::appendToFile` defer to `publish()` for builder consistency (#10).

---

## Preferences
- Give short and direct answers
- Focus on practical solutions with code
- Follow Laravel best practices
- Prefer clean and modular architecture

## Code Style Rules
- Do NOT write comments in Arabic inside code
- Always write code comments in English, even if the conversation is in Arabic

## Git Commits

- Do not create Git commits automatically — only create commits when explicitly requested by the user.
- Create one commit per logical change.
- Keep each commit message to a single short line, in the imperative mood (e.g. `Fix...`, `Add...`, `Update...`).
- Do not include a commit body.
- Do not add trailers such as `Co-Authored-By`, or any AI attribution / generated-by footer.
- Commit only the files related to the current task.

## Pull Requests

- Do not create a Pull Request unless explicitly requested by the user.
- When requested, create exactly one Pull Request for the completed task.
- Use the user's Git identity and account only.
- Never mention Claude, Anthropic, AI, generated content, co-authors, or any AI attribution.
- Follow the repository's existing Pull Request style and conventions.
- Write a short, clear title consistent with the project's commit/PR naming.
- Keep the description brief and professional.
- Include only:
    - A short summary of what changed.
    - A brief note if there is anything important for reviewers to know.
- Do not include emojis, icons, marketing language, template placeholders, or unnecessary sections.
- Do not add testing sections unless explicitly requested or required by the repository.
- Only include files related to the requested task in the Pull Request.
