<?php

namespace Graviton\RestBundle\HttpFoundation;

/**
 * Represents a Link header.
 *
 * @category GravitonRestBundle
 * @package  Graviton
 * @author   Lucas Bickel <lucas.bickel@swisscom.com>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://swisscom.com
 */
class LinkHeader
{
    /**
     * @var LinkHeaderItem[]
     */
    private $items = array();

    /**
     * Constructor
     *
     * @param LinkHeaderItem[] $items link header items
     *
     * @return void
     */
    public function __construct(array $items)
    {
        $this->items = $items;
    }

    /**
     * Builds a LinkHeader instance from a string.
     *
     * @param String $headerValue value of complete header
     *
     * @return LinkHeader
     */
    public static function fromString($headerValue)
    {
        return new self(array_map(function ($itemValue) use (&$index) {
            $item = LinkHeaderItem::fromString(trim($itemValue));

            return $item;
        }, preg_split('/(".+?"|[^,]+)(?:,|$)/', $headerValue, 0, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE)));
    }

    /**
     * get all items
     *
     * @return Array
     */
    public function all()
    {
        return $this->items;
    }
}
