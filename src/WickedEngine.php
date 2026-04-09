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
use Horde\Text\Wiki\Node\ElementNode;
use Horde\Text\Wiki\NodeVisitor;
use Horde\Text\Wiki\Parser;
use Horde\Text\Wiki\Renderer;
use Horde\Text\Wiki\SimpleFormatCatalog;
use Horde\Text\Wiki\WikiEngine;
use Horde_Core_Factory_BlockCollection;
use Horde_Mime_Part;
use Horde_Mime_Viewer;
use Horde_Registry;
use Horde_Url;
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
    ) {
        $this->catalog = $catalog ?? SimpleFormatCatalog::withDefaults();
        $format = $this->normalizeFormat($format);
        $this->parser = $this->catalog->getParser($format);
    }

    public function transform(string $text, string $format = 'Xhtml'): string
    {
        $format = $this->normalizeFormat($format);

        // Pre-process Wicked-specific syntax before AST parsing
        $text = $this->preprocessWickedBlocks($text, $format);
        $text = $this->preprocessRegistryLinks($text, $format);
        $text = $this->stripAttributes($text);

        $renderer = $this->catalog->getRenderer($format);
        $this->applyWickedHandlers($renderer);

        return $renderer->render($this->parser->parse($text));
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
     * Normalize format name: lowercase + resolve legacy aliases
     */
    private function normalizeFormat(string $format): string
    {
        $lower = strtolower($format);

        return self::FORMAT_ALIASES[$lower] ?? $lower;
    }

    private function applyWickedHandlers(Renderer $renderer): void
    {
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

            return "<pre><code{$langAttr}>" . htmlspecialchars($text) . "</code></pre>\n";
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
    // Pre-processors for Wicked-specific syntax
    // ------------------------------------------------------------------

    /**
     * Pre-process [[block app/name args]] into rendered HTML
     *
     * Uses the same regex as WickedParserWickedblock. For Xhtml output,
    /**
     * Pre-process {{wicked:block:NAME}} constructs.
     *
     * renders the block content directly. For other formats, strips the
     * construct.
     */
    private function preprocessWickedBlocks(string $text, string $format): string
    {
        if ($this->blockFactory === null) {
            return $text;
        }

        $blockFactory = $this->blockFactory;
        $isXhtml = $format === 'xhtml';

        return (string) preg_replace_callback(
            '/\[\[block (.*)?\/(.*)? (.*)?\]\]/sU',
            function (array $matches) use ($blockFactory, $isXhtml): string {
                if (!$isXhtml) {
                    return '';
                }

                try {
                    $app = $matches[1];
                    $block = $matches[2];
                    $args = [];
                    foreach (explode(' ', $matches[3], 2) as $pair) {
                        [$arg, $value] = array_pad(explode('=', $pair, 2), 2, '');
                        $args[$arg] = $value;
                    }

                    $blockCollection = $blockFactory->create();
                    $blockObj = $blockCollection->getBlock(
                        $app,
                        $app . '_Block_' . $block,
                        $args,
                    );

                    return $blockObj->getContent();
                } catch (Throwable $e) {
                    return htmlspecialchars($e->getMessage());
                }
            },
            $text,
        );
    }

    /**
     * Pre-process [[link title | app/method args]] into rendered HTML
     *
     * Uses the same regex as WickedParserRegistrylink.
     */
    private function preprocessRegistryLinks(string $text, string $format): string
    {
        $registry = $this->registry;
        $isXhtml = $format === 'xhtml';

        return (string) preg_replace_callback(
            '/\[\[link (.*)\]\]/sU',
            function (array $matches) use ($registry, $isXhtml): string {
                if (!$isXhtml) {
                    // For non-Xhtml, just return the title text
                    [$title] = explode('|', $matches[1], 2);

                    return htmlspecialchars(trim($title));
                }

                try {
                    [$title, $call] = array_pad(explode('|', $matches[1], 2), 2, '');
                    $opts = explode(' ', trim($call));
                    $method = trim(array_shift($opts));
                    parse_str(implode('&', $opts), $args);

                    $link = new Horde_Url($registry->link($method, $args));

                    return $link->link() . htmlspecialchars(trim($title)) . '</a>';
                } catch (Throwable $e) {
                    return htmlspecialchars($e->getMessage());
                }
            },
            $text,
        );
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
