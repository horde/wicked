<?php

declare(strict_types=1);

/**
 * Copyright 2013-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 */

namespace Horde\Wicked;

use Closure;
use Horde\Text\Wiki\FormatCatalog;
use Horde\Text\Wiki\Node\DocumentNode;
use Horde\Text\Wiki\Node\ElementNode;
use Horde\Text\Wiki\Node\TextNode;
use Horde\Text\Wiki\NodeVisitor;
use Horde\Text\Wiki\Parser;
use Horde\Text\Wiki\Renderer;
use Horde\Text\Wiki\Renderer\Xhtml;
use Horde\Text\Wiki\SimpleFormatCatalog;
use Horde\Text\Wiki\WikiEngine;
use Horde_Core_Factory_BlockCollection;
use Horde_Mime_Part;
use Horde_Mime_Viewer;
use Horde_Registry;
use Horde_Url;
use Psr\SimpleCache\CacheInterface;
use Throwable;
use Wicked_Driver;

/**
 * Wicked wiki engine — new-style AST-based implementation
 *
 * Uses FormatCatalog for parser/renderer resolution and setElementHandler()
 * for Horde-specific element overrides (page links, syntax highlighting,
 * blocks, registry links).
 *
 * All dependencies are injected — no direct $GLOBALS access.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/gpl GPL
 * @package  Wicked
 */
class WickedEngine implements WikiEngine
{
    private FormatCatalog $catalog;
    private Parser $parser;
    private ?int $currentPageId = null;
    private ?int $currentPageVersion = null;

    /** @var array<string, string> Legacy format name aliases */
    private const FORMAT_ALIASES = [
        'default' => 'yawiki',
    ];

    public function __construct(
        private readonly Wicked_Driver $storageDriver,
        private readonly Horde_Registry $registry,
        private readonly WikilinkUrlResolver $urlResolver,
        ?FormatCatalog $catalog = null,
        string $format = 'yawiki',
        private readonly ?Horde_Core_Factory_BlockCollection $blockFactory = null,
        private readonly ?CacheInterface $cache = null,
    ) {
        $this->catalog = $catalog ?? SimpleFormatCatalog::withDefaults();
        $format = $this->normalizeFormat($format);
        $this->parser = $this->catalog->getParser($format);
    }

    public function transform(string $text, string $format = 'Xhtml'): string
    {
        $format = $this->normalizeFormat($format);

        // Try cache for xhtml display of static pages
        $cacheKey = null;
        if ($this->cache !== null
            && $format === 'xhtml'
            && $this->currentPageId !== null
            && !$this->hasDynamicContent($text)
        ) {
            $cacheKey = 'wicked.render.' . $this->currentPageId
                . '.' . $this->currentPageVersion;
            $cached = $this->cache->get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        // Strip [[WikiWord: value]] attributes before parsing — these
        // are metadata, not content.
        $text = $this->stripAttributes($text);

        // Expand [[block ...]] and [[link ...]] constructs in prose.
        // Uses a character-level scanner that is code-fence-aware, so
        // examples inside ``` or {{{ blocks are never touched.
        $text = $this->expandWickedConstructs($text, $format);

        $renderer = $this->catalog->getRenderer($format);
        $this->applyWickedHandlers($renderer);

        $document = $this->parser->parse($text);
        $this->rewriteWikiTocNodes($document);
        $html = $renderer->render($document);

        if ($cacheKey !== null) {
            $this->cache->set($cacheKey, $html);
        }

        return $html;
    }

    /**
     * Extract wiki page attributes from text
     *
     * Attributes are in the form [[WikiWord: value]] and carry metadata
     * about the page. Call this before transform() if you need them.
     *
     * @param string $text Wiki source text
     *
     * @return array<int, array{name: string, value: string}> Extracted attributes
     */
    public function extractAttributes(string $text): array
    {
        $attributes = [];
        $wikiWordPattern = '[A-Z][a-z]+[A-Z]\w*';
        $blockPattern = '/\[\[(' . $wikiWordPattern . '):\s+(.*?)\]\]/s';

        if (preg_match_all($blockPattern, $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $attributes[] = [
                    'name' => $match[1],
                    'value' => $match[2],
                ];
            }
        }

        return $attributes;
    }

    /**
     * Set page identity for cache key generation.
     *
     * Call before transform() to enable caching for this page.
     * Callers that don't set context (preview, export) get no caching.
     */
    public function setPageContext(int $pageId, int $pageVersion): void
    {
        $this->currentPageId = $pageId;
        $this->currentPageVersion = $pageVersion;
    }

    /**
     * Normalize format name: lowercase + resolve legacy aliases
     */
    private function normalizeFormat(string $format): string
    {
        $lower = strtolower($format);

        return self::FORMAT_ALIASES[$lower] ?? $lower;
    }

    private function applyWickedHandlers(Renderer $renderer): void
    {
        if ($renderer instanceof Xhtml) {
            $renderer->enableHeadingIds();
        }
        $renderer->setElementHandler('wikilink', $this->createWikilinkHandler());
        $renderer->setElementHandler('url', $this->createUrlHandler());
        $renderer->setElementHandler('code', $this->createCodeHandler($renderer));
        $renderer->setElementHandler('table', $this->createTableHandler());
        // Treat single newlines as hard breaks — existing wiki content
        // was authored expecting visible line breaks from newlines.
        $renderer->setElementHandler('softbreak', fn() => "<br />\n");
    }

    /**
     * Wikilink handler: page existence checking + Wicked URL generation
     *
     * Replaces the old XhtmlRendererWikilink2 and XhtmlRendererFreelink2.
     * Handles both 'wikilink' elements (from wiki parsers) — the freelink
     * element was unified to wikilink in the AST duplicate cleanup.
     */
    private function createWikilinkHandler(): Closure
    {
        $storageDriver = $this->storageDriver;
        $urlResolver = $this->urlResolver;

        return function (ElementNode $node, NodeVisitor $visitor) use ($storageDriver, $urlResolver): string {
            $attrs = $node->getAttributes();
            $page = $attrs['page'] ?? strip_tags($visitor->renderChildren($node));
            $anchor = $attrs['anchor'] ?? '';
            $text = $visitor->renderChildren($node);

            if ($anchor !== '' && $anchor[0] !== '#') {
                $anchor = '#' . $anchor;
            }

            $exists = $storageDriver->pageExists($page);
            $href = htmlspecialchars($urlResolver->resolve($page)) . htmlspecialchars($anchor);
            $css = $exists ? 'wikilink' : 'newpage';
            $label = $text !== '' ? $text : htmlspecialchars($page);

            return '<a class="' . $css . '" href="' . $href . '">' . $label . '</a>';
        };
    }

    /**
     * URL handler: rewrite relative links to include the wicked webroot
     *
     * Markdown [text](Doc/Dev/GitTools) produces a url element with a bare
     * relative href. We prepend the webroot so the browser resolves it
     * correctly regardless of the current path. External and absolute
     * URLs are left unchanged.
     */
    private function createUrlHandler(): Closure
    {
        $webroot = rtrim((string) $this->registry->get('webroot', 'wicked'), '/');

        return function (ElementNode $node, NodeVisitor $visitor) use ($webroot): string {
            $attrs = $node->getAttributes();
            $href = $attrs['href'] ?? '';
            $text = $visitor->renderChildren($node);

            if ($href !== '' && !str_contains($href, '://') && $href[0] !== '/' && $href[0] !== '#') {
                $href = $webroot . '/' . $href;
            }

            $href = htmlspecialchars($href, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $html = '<a href="' . $href . '"';
            if (isset($attrs['title']) && $attrs['title'] !== '') {
                $html .= ' title="' . htmlspecialchars($attrs['title'], ENT_QUOTES | ENT_HTML5, 'UTF-8') . '"';
            }
            $html .= '>' . $text . '</a>';

            return $html;
        };
    }

    /**
     * Code handler: syntax highlighting via Horde_Mime_Viewer
     *
     * Replaces XhtmlRendererCode2. Only applies syntax highlighting for
     * Xhtml output when a language attribute is present.
     */
    private function createCodeHandler(Renderer $renderer): Closure
    {
        $registry = $this->registry;
        $isXhtml = strtolower($renderer->getFormat()) === 'xhtml';

        return function (ElementNode $node, NodeVisitor $visitor) use ($registry, $isXhtml): string {
            $attrs = $node->getAttributes();
            $language = $attrs['language'] ?? '';
            $text = $visitor->renderChildren($node);

            if ($isXhtml && $language !== '') {
                try {
                    $part = new Horde_Mime_Part();
                    $part->setContents($text);
                    $part->setType('application/x-extension-' . $language);
                    $viewer = Horde_Mime_Viewer::factory(
                        'Horde_Core_Mime_Viewer_Syntaxhighlighter',
                        $part,
                        ['registry' => $registry],
                    );
                    $data = $viewer->render('inline');
                    $data = reset($data);

                    return $data['data'];
                } catch (Throwable) {
                    // Fall through to default rendering on failure
                }
            }

            $langAttr = $language !== ''
                ? ' class="language-' . htmlspecialchars($language) . '"'
                : '';

            return "<pre><code{$langAttr}>" . $text . "</code></pre>\n";
        };
    }

    /**
     * Table handler: inject horde-table CSS class
     */
    private function createTableHandler(): Closure
    {
        return function (ElementNode $node, NodeVisitor $visitor): string {
            return '<table class="horde-table">' . $visitor->renderChildren($node) . "</table>\n";
        };
    }

    // ------------------------------------------------------------------
    // AST post-processing
    // ------------------------------------------------------------------

    private function rewriteWikiTocNodes(DocumentNode $document): void
    {
        $replacements = [];
        foreach ($document->getChildren() as $child) {
            if (!$child instanceof ElementNode
                || $child->getName() !== 'htmlblock'
            ) {
                continue;
            }
            $text = '';
            foreach ($child->getChildren() as $grandchild) {
                if ($grandchild instanceof TextNode) {
                    $text .= $grandchild->getText();
                }
            }
            if (preg_match('/^\s*<wiki-toc(?:\s+depth="(\d+)")?(?:\s*\/>|>\s*(?:<\/wiki-toc>)?)\s*$/i', $text, $m)) {
                $toc = new ElementNode('toc');
                if (isset($m[1]) && $m[1] !== '') {
                    $toc->setAttribute('depth', (int) $m[1]);
                }
                $replacements[] = ['old' => $child, 'new' => $toc];
            }
        }

        if ($replacements === []) {
            return;
        }

        $children = $document->getChildren();
        $clearChildren = Closure::bind(function () {
            $this->children = [];
        }, $document, DocumentNode::class);
        $clearChildren();

        $replaceMap = new \SplObjectStorage();
        foreach ($replacements as $r) {
            $replaceMap[$r['old']] = $r['new'];
        }

        foreach ($children as $child) {
            if ($replaceMap->contains($child)) {
                $document->addChild($replaceMap[$child]);
            } else {
                $document->addChild($child);
            }
        }
    }

    // ------------------------------------------------------------------
    // Wicked construct expansion (code-fence-aware scanner)
    // ------------------------------------------------------------------

    /**
     * Check whether raw wiki text contains dynamic constructs.
     *
     * Pages with [[block ...]] or [[link ...]] in prose (outside code
     * fences) produce output that depends on runtime state and must not
     * be cached.
     */
    private function hasDynamicContent(string $text): bool
    {
        foreach ($this->scanConstructs($text) as $construct) {
            if ($construct['type'] === 'block' || $construct['type'] === 'link') {
                return true;
            }
        }

        return false;
    }

    /**
     * Expand [[block ...]] and [[link ...]] constructs in prose text.
     *
     * Walks the text line-by-line, tracking fenced code block state
     * (Markdown ``` / ~~~ and legacy {{{ }}}). Constructs found inside
     * code fences are left untouched. Constructs in prose are replaced
     * with their rendered output.
     */
    private function expandWickedConstructs(string $text, string $format): string
    {
        $constructs = $this->scanConstructs($text);
        if ($constructs === []) {
            return $text;
        }

        $isXhtml = $format === 'xhtml';

        // Replace from end to start so offsets stay valid
        for ($i = count($constructs) - 1; $i >= 0; $i--) {
            $c = $constructs[$i];
            $replacement = match ($c['type']) {
                'block' => $this->renderBlock($c['body'], $isXhtml),
                'link' => $this->renderRegistryLink($c['body'], $isXhtml),
                default => null,
            };
            if ($replacement !== null) {
                $text = substr_replace($text, $replacement, $c['offset'], $c['length']);
            }
        }

        return $text;
    }

    /**
     * Scan text for [[block ...]] and [[link ...]] outside code fences.
     *
     * Returns an array of found constructs with their type, body,
     * offset and length — enough to do in-place replacement.
     *
     * @return array<int, array{type: string, body: string, offset: int, length: int}>
     */
    private function scanConstructs(string $text): array
    {
        $constructs = [];
        $len = strlen($text);
        $pos = 0;
        $inFence = false;
        $fenceChar = '';
        $fenceLen = 0;

        while ($pos < $len) {
            // Track line beginnings for fenced code detection
            $lineStart = ($pos === 0 || $text[$pos - 1] === "\n");

            // Check for fenced code block boundaries at line start
            if ($lineStart) {
                $ch = $text[$pos];

                // Markdown fences: ``` or ~~~
                if ($ch === '`' || $ch === '~') {
                    $run = 0;
                    $p = $pos;
                    while ($p < $len && $text[$p] === $ch) {
                        $run++;
                        $p++;
                    }
                    if ($run >= 3) {
                        if (!$inFence) {
                            $inFence = true;
                            $fenceChar = $ch;
                            $fenceLen = $run;
                            // Skip to end of line
                            $nl = strpos($text, "\n", $pos);
                            $pos = $nl === false ? $len : $nl + 1;
                            continue;
                        } elseif ($ch === $fenceChar && $run >= $fenceLen) {
                            $inFence = false;
                            $nl = strpos($text, "\n", $pos);
                            $pos = $nl === false ? $len : $nl + 1;
                            continue;
                        }
                    }
                }

                // Legacy wiki fence: {{{ (opening only — closing }}} can be mid-line)
                if (!$inFence && $pos + 2 < $len
                    && $text[$pos] === '{' && $text[$pos + 1] === '{' && $text[$pos + 2] === '{'
                ) {
                    $inFence = true;
                    $fenceChar = '{';
                    $fenceLen = 3;
                    $pos += 3;
                    continue;
                }
            }

            // Legacy wiki fence close: }}}
            if ($inFence && $fenceChar === '{'
                && $pos + 2 < $len
                && $text[$pos] === '}' && $text[$pos + 1] === '}' && $text[$pos + 2] === '}'
            ) {
                $inFence = false;
                $pos += 3;
                continue;
            }

            // Inside a code fence — skip everything
            if ($inFence) {
                $nl = strpos($text, "\n", $pos);
                $pos = $nl === false ? $len : $nl + 1;
                continue;
            }

            // Look for [[ at current position
            if ($text[$pos] === '[' && $pos + 1 < $len && $text[$pos + 1] === '[') {
                // Find the matching ]]
                $close = strpos($text, ']]', $pos + 2);
                if ($close !== false) {
                    $inner = substr($text, $pos + 2, $close - $pos - 2);
                    $fullLen = $close + 2 - $pos;

                    if (str_starts_with($inner, 'block ')) {
                        $constructs[] = [
                            'type' => 'block',
                            'body' => substr($inner, 6),
                            'offset' => $pos,
                            'length' => $fullLen,
                        ];
                        $pos = $close + 2;
                        continue;
                    } elseif (str_starts_with($inner, 'link ')) {
                        $constructs[] = [
                            'type' => 'link',
                            'body' => substr($inner, 5),
                            'offset' => $pos,
                            'length' => $fullLen,
                        ];
                        $pos = $close + 2;
                        continue;
                    }
                }
            }

            $pos++;
        }

        return $constructs;
    }

    /**
     * Render a [[block app/name args]] construct.
     */
    private function renderBlock(string $body, bool $isXhtml): string
    {
        if (!$isXhtml || $this->blockFactory === null) {
            return '';
        }

        try {
            // Parse "app/block arg1=val1 arg2=val2"
            $parts = explode(' ', $body, 2);
            $appBlock = $parts[0] ?? '';
            $argStr = $parts[1] ?? '';

            $slash = strpos($appBlock, '/');
            if ($slash === false) {
                return '';
            }
            $app = substr($appBlock, 0, $slash);
            $block = substr($appBlock, $slash + 1);

            $args = [];
            if ($argStr !== '') {
                foreach (preg_split('/\s+/', $argStr) as $pair) {
                    [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
                    $args[$key] = $value;
                }
            }

            $blockCollection = $this->blockFactory->create();
            $blockObj = $blockCollection->getBlock(
                $app,
                $app . '_Block_' . $block,
                $args,
            );

            return $blockObj->getContent();
        } catch (Throwable $e) {
            return htmlspecialchars($e->getMessage());
        }
    }

    /**
     * Render a [[link title | app/method args]] construct.
     */
    private function renderRegistryLink(string $body, bool $isXhtml): string
    {
        if (!$isXhtml) {
            [$title] = explode('|', $body, 2);

            return htmlspecialchars(trim($title));
        }

        try {
            [$title, $call] = array_pad(explode('|', $body, 2), 2, '');
            $opts = explode(' ', trim($call));
            $method = trim(array_shift($opts));
            parse_str(implode('&', $opts), $args);

            $link = new Horde_Url($this->registry->link($method, $args));

            return $link->link() . htmlspecialchars(trim($title)) . '</a>';
        } catch (Throwable $e) {
            return htmlspecialchars($e->getMessage());
        }
    }

    /**
     * Strip [[WikiWord: value]] attribute blocks from text
     *
     * These are extracted via extractAttributes() before rendering.
     * The parser shouldn't see them.
     */
    private function stripAttributes(string $text): string
    {
        $wikiWordPattern = '[A-Z][a-z]+[A-Z]\w*';

        return (string) preg_replace(
            '/(?:\[\[' . $wikiWordPattern . ':\s+.*?\]\]\s*)+/',
            '',
            $text,
        );
    }
}
