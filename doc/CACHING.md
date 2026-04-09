# Rendered Page Caching

Wicked caches the rendered HTML output of wiki pages to avoid re-parsing
and re-rendering on every view. The cache uses the PSR-16
(`Psr\SimpleCache\CacheInterface`) implementation from `Horde\Cache\Cache`.

## How it works

When a wiki page is displayed, `WickedEngine::transform()` converts the
raw markup (Markdown, Yawiki, etc.) into HTML. This transformation
involves parsing the source into an AST and rendering it — a process
that takes 5–15ms per page.

With caching enabled, the rendered HTML is stored after the first
transform and returned directly on subsequent requests, reducing render
time to < 0.1ms.

### Cache keys

Keys follow the format:

    wicked.render.{page_id}.{page_version}

Because `page_version` is part of the key, editing a page automatically
creates a new key. The old cache entry is never read again and expires
via garbage collection after the configured TTL.

**No explicit cache invalidation is needed on edit.** The version
increment in `Wicked_Driver_Sql::updateText()` is sufficient.

### What gets cached

Only the `WickedEngine::transform()` output — the pure text-to-HTML
conversion. The surrounding page chrome (edit/lock buttons, navigation
toolbar, notifications) is NOT cached because it depends on the current
user's permissions.

### What does NOT get cached

- **Pages with dynamic content:** Pages containing `[[block ...]]`
  (Horde block embeds) or `[[link ...]]` (registry links) are detected
  before rendering and excluded from caching. Their output depends on
  runtime state.

- **Preview rendering:** The edit preview controller does not set a page
  context, so `transform()` runs without caching.

- **Exports:** Exports (plain text, LaTeX, RST) use different output
  formats and are not cached.

- **API calls:** The Wicked API (`lib/Api.php`) does not set page
  context either.

## Configuration

The cache TTL is configured in `config/conf.xml` under the `wicked`
section:

```xml
<configsection name="cache">
  <configinteger name="lifetime">86400</configinteger>
</configsection>
```

This maps to `$conf['wicked']['cache']['lifetime']` and defaults to
**86400 seconds (24 hours)**.

### TTL semantics (PSR-16)

The TTL controls how long cache entries remain on disk before garbage
collection removes them. Because cache keys include the page version,
correctness does not depend on the TTL — stale entries are simply never
looked up. The TTL only affects disk space usage.

**Important: PSR-16 TTL = 0 does NOT mean "keep forever".**

In the legacy `Horde_Cache` API, `$lifetime = 0` meant "never garbage
collect." PSR-16 reverses this: a TTL of 0 or any negative value means
"expired immediately" — the entry is deleted on write. This is defined
in the PSR-16 specification (PSR-16 §1.2) and implemented in
`Horde\Cache\Cache::set()`:

```php
// PSR-16: ttl=0 means delete immediately
if ($ttl !== null && $ttl <= 0) {
    return $this->delete($key);
}
```

Setting `$conf['wicked']['cache']['lifetime']` to `0` **disables
caching entirely** because every `set()` call immediately deletes the
entry.

| Value   | Behavior                                      |
|---------|-----------------------------------------------|
| `86400` | Entries kept for 24 hours (default)            |
| `3600`  | Entries kept for 1 hour                        |
| `0`     | **Caching disabled** — entries deleted on write |

If you want entries to live as long as possible, use a large value like
`604800` (7 days) or `2592000` (30 days). There is no "infinite" TTL
in PSR-16.

## Architecture

```
StandardPage::displayContents()
  │
  ├── $processor->setPageContext($pageId, $pageVersion)
  │     Sets identity for cache key. Callers that skip this
  │     (preview, export, API) get no caching.
  │
  └── $processor->transform($text)
        │
        ├── hasDynamicContent($text)?
        │     YES → skip cache, render directly
        │     NO  → build cache key
        │
        ├── cache->get($key)
        │     HIT  → return cached HTML
        │     MISS → continue to render
        │
        ├── preprocessWickedBlocks()
        ├── preprocessRegistryLinks()
        ├── stripAttributes()
        ├── parser->parse() → renderer->render()
        │
        └── cache->set($key, $html)
              Store for next request
```

## Dependencies

- `Horde\Cache\Cache` — PSR-16 cache facade
- `Horde\Cache\FileStorage` — File-based storage backend (uses the
  Horde cache directory or system temp directory)

The cache instance is created in `Wicked_Application::_bootstrap()` and
injected into `WickedEngine` via its constructor.
