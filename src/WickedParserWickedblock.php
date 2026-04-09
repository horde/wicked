<?php

namespace Horde\Wicked;

use Horde\Text\Wiki\WikiParserBase;

/**
 * This parser parses Wicked blocks, which add Horde_Blocks to the
 * page.  Basic syntax is [[block block-app/block-name block-args]].
 *
 * Original
 * @author Jan Schneider <jan@horde.org>
 * Refactored to Horde\Text\Wiki v2 by Ralf Lang <ralf.lang@ralf-lang.de>
 *
 * @package Wicked
 */
class WickedParserWickedblock extends WikiParserBase
{
    /**
     * The regular expression used to find blocks.
     *
     * @access public
     *
     * @var string
     */
    public $regex = "/\[\[block (.*)?\/(.*)? (.*)?\]\]/sU";

    public function __construct(WickedEngine $obj)
    {
        parent::__construct($obj);
    }
    /**
     * Generates a token entry for the matched text. Token options are:
     *
     * 'src'  => The image source, typically a relative path name.
     * 'opts' => Any macro options following the source.
     *
     * @access public
     *
     * @param array $matches  The array of matches from parse().
     *
     * @return  A delimited token number to be used as a placeholder in
     *          the source text.
     */
    public function process($matches)
    {
        $args = [];
        foreach (explode(' ', $matches[3], 2) as $pair) {
            @[$arg, $value] = explode('=', $pair);
            $args[$arg] = $value;
        }
        return $this->wiki->addToken(
            $this->rule,
            ['app' => $matches[1],
                'block' => $matches[2],
                'args' => $args]
        );
    }
}
