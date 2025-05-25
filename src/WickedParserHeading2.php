<?php
namespace Horde\Wicked;
use Horde\Text\Wiki\WikiParserBase;
use Horde\Text\Wiki\DefaultParserHeading;
use Wicked;


/**
 * Parsers class as a complement to the Header2 renderer.
 *
 * Works around broken parser, adding additional spaces inside headers.
 *
 * @package Wicked
 */
class WickedParserHeading2 extends WikiParserBase
{
    protected $wrappedParserClass;
    protected $wrappedParser;
    /**
     * TODO: This is a lazy implementation. We should rather make this a proxy
     * - Leaving the input untouched and forwarding it to the proper backend parser
     * - Only if the backend parser needs amending like "default" does, we should first manipulate the input.
     */
    public function __construct(WickedEngine $obj)
    {
        parent::__construct($obj);
        $this->wrappedParserClass = $obj->parserPrefix . 'ParserHeading';
        $this->wrappedParser = new $this->wrappedParserClass($obj, 'Heading2');
        if ($this->wrappedParser instanceof DefaultParserHeading) {
//            $this->wrappedParser->regex = '/^(\++ *(.*)/mu';
        }
    }
    public function process($matches)
    {
        return $this->wrappedParser->process($matches);
    }

    public function parse()
    {
        // We need to parse the text with the wrapped parser.
        // This is a workaround for the broken parser.
        return $this->wrappedParser->parse();
    }
}