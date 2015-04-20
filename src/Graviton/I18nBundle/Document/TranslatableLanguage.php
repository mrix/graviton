<?php
/**
 * Graviton\I18nBundle\Document\TranslatableLanguage
 */

namespace Graviton\I18nBundle\Document;

/**
 * Graviton\I18nBundle\Document\TranslatableLanguage
 *
 * @author   List of contributors <https://github.com/libgraviton/graviton/graphs/contributors>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://swisscom.ch
 */
class TranslatableLanguage
{
    /**
     * @var MongoId $id
     */
    protected $id;

    /**
     * @var extref $ref
     */
    protected $ref;


    /**
     * Get id
     *
     * @return string $id
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set ref
     *
     * @param extref $ref value of extref
     * @return self
     */
    public function setRef($ref)
    {
        $this->ref = $ref;
        return $this;
    }

    /**
     * Get ref
     *
     * @return extref $ref
     */
    public function getRef()
    {
        return $this->ref;
    }
}
