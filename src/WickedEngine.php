<?php

// vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4:
/**
 * Wicked specific engine for Horde\Text\Wiki allowing layering/overrides of markup
 *
 * PHP versions 8+
 *
 * @author     Ralf Lang <ralf.lang@ralf-lang.de>
 */

namespace Horde\Wicked;
use Horde\Text\Wiki\TextWikiBase;
use Horde\Text\Wiki\TextWikiException;
use Horde\Text\Wiki\GenericTextWikiException;
use Horde\Injector\Injector;
use Horde_Injector;
use Throwable;
use Wicked_Driver;
/**
 * This is the wrapper engine. 
 * 
 * The wrapper loads a parser, a set of override parsing and rendering rules and alias mappings.
 */
class WickedEngine extends TextWikiBase
{
    public function __construct(
        protected Injector|Horde_Injector $injector    
    )
    {
        // Load the backend parser configured in the config.
        // The config should be injected using a wrapper class. This global access is bad!
        $backend = $GLOBALS['conf']['wicked']['format'] ?? 'Default';
        $this->parserPrefix = 'Horde\Text\Wiki\\' . $backend;
        $backendEngineClass = $this->parserPrefix . 'Engine';
        $backendEngine = new $backendEngineClass();
        // We only need the backend engine to extract defaults
        $this->rules = $backendEngine->rules;
    }

    public function loadParseObj($rule)
    {
        // TODO: Make this configurable.
        $candidateFcqn = __NAMESPACE__ . '\WickedParser' . ucfirst($rule);
        if (class_exists($candidateFcqn)) {
            $this->parseObj[$rule] = $this->injector->get($candidateFcqn);
            return;
        }
        try {
            return parent::loadParseObj($rule);
        } catch (Throwable $e) {
            $ruleIdMap = [
                'Code2' => 'Code',
                'Freelink2' => 'Freelink',
                'Heading2' => 'Heading',
                'Image2' => 'Image',
                'Toc2' => 'Toc',
                'Wikilink2' => 'Wikilink',
                'Table2' => 'Table',
            ];
            if (array_key_exists($rule, $ruleIdMap)) {
                parent::loadParseObj($ruleIdMap[$rule]);
                $this->parseObj[$rule] = clone($this->parseObj[$ruleIdMap[$rule]]);
                   // If the rule has an ID, we can map it to a custom rule.
            } elseif($rule == 'Paragraph')  {
                // Wicked's Page class just demands a paragraph parser, even if the backend doesn't have one.
                $this->parseObj[$rule] = new \Horde\Text\Wiki\DefaultParserParagraph($this);
            }
            else {
                // If the rule does not have an ID, we cannot map it.
                throw $e;
            }
        }
    }

    public function loadRenderObj($format, $rule)
    {
        // TODO: Make this configurable.
        $candidateFcqn = __NAMESPACE__ . '\\' . $format . 'Renderer' . ucfirst($rule);
        if (class_exists($candidateFcqn)) {
            $this->renderObj[$rule] = $this->injector->get($candidateFcqn);
            return;
        }
        try {
            parent::loadRenderObj($format, $rule);
        } catch (Throwable $e) {
            // If the rule does not have an ID, we cannot map it.
            $ruleIdMap = [
                'Code2' => 'Code',
                'Freelink2' => 'Freelink',
                'Heading2' => 'Heading',
                'Image2' => 'Image',
                'Toc2' => 'Toc',
                'Wikilink2' => 'Wikilink',
                'Table2' => 'Table',
            ];
            if (array_key_exists($rule, $ruleIdMap)) {
                parent::loadRenderObj($format, $ruleIdMap[$rule]);
                $this->renderObj[$rule] = clone($this->renderObj[$ruleIdMap[$rule]]);
            } else {
                // If the rule does not have an ID, we cannot map it.
                throw $e;
            }
        }
        return;
    }

/*    public function transform($text, $rule = null)
    {
        return parent::transform($text, $rule);
    }*/
}
