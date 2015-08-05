<?php
namespace Graviton\GeneratorBundle\Definition;


/**
 * Reference( field as specified in the json definition
 *
 * @author   List of contributors <https://github.com/libgraviton/graviton/graphs/contributors>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://swisscom.ch
 */
class JsonDefinitionReference extends JsonDefinitionField
{
    /**
     * Returns the whole definition in array form
     *
     * @return array Definition
     */
    public function getDefAsArray()
    {
        return array_replace(
            parent::getDefAsArray(),
            [
                'type'              => $this->getClassName(),
                'doctrineType'      => $this->getTypeDoctrine(),
                'serializerType'    => $this->getTypeSerializer(),
                'relType'           => self::REL_TYPE_REF,
                'isClassType'       => true,
            ]
        );
    }

    /**
     * Returns the field type in a doctrine-understandable way..
     *
     * @return string Type
     */
    public function getTypeDoctrine()
    {
        return $this->getClassName();
    }

    /**
     * Returns the field type
     *
     * @return string Type
     */
    public function getType()
    {
        return $this->getClassName();
    }

    /**
     * Returns the field type in a serializer-understandable way..
     *
     * @return string Type
     */
    public function getTypeSerializer()
    {
        return $this->getClassName();
    }

    /**
     * If this is a classType, return the defined class name
     *
     * @return string class name
     */
    public function getClassName()
    {
        return strtr($this->getDef()->getType(), ['class:' => '', '[]' => '']);
    }
}
