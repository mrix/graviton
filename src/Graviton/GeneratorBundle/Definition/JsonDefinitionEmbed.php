<?php
namespace Graviton\GeneratorBundle\Definition;

/**
 * Embedded field as specified in the json definition
 *
 * @author   List of contributors <https://github.com/libgraviton/graviton/graphs/contributors>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://swisscom.ch
 */
class JsonDefinitionEmbed extends JsonDefinitionHash
{
    /**
     * @var Schema\Field
     */
    private $defintion;

    /**
     * Constructor
     *
     * @param string         $name       Name of this hash
     * @param JsonDefinition $parent     Parent definiton
     * @param Schema\Field   $definition Field definition
     */
    public function __construct($name, JsonDefinition $parent, Schema\Field $definition)
    {
        $this->defintion = $definition;
        parent::__construct($name, $parent, []);
    }

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
                'type'              => $this->getType(),
                'doctrineType'      => $this->getTypeDoctrine(),
                'serializerType'    => $this->getTypeSerializer(),
                'relType'           => self::REL_TYPE_EMBED,
                'isClassType'       => true,
            ]
        );
    }

    /**
     * Returns the field definition of this hash from "local perspective",
     * meaning that we only include fields inside this hash BUT with all
     * the stuff from the json file. this is needed to generate a Document/Model
     * from this hash (generate a json file again)
     *
     * @return JsonDefinition the definition of this hash in a standalone array ready to be json_encoded()
     */
    public function getJsonDefinition()
    {
        $definition = (new Schema\Definition())
            ->setId($this->getClassName())
            ->setIsSubDocument(true)
            ->setParentClass($this->getParentClass())
            ->setTarget(
                (new Schema\Target())
                    ->addField(
                        (new Schema\Field())
                            ->setName('__ununsed__')
                            ->setType('string')
                            ->setReadOnly(true)
                    )
            );

        return new JsonDefinition($definition);
    }

    /**
     * Returns the parent class name
     *
     * @return string class name
     */
    private function getParentClass()
    {
        return strtr($this->defintion->getType(), ['class:' => '', '[]' => '']);
    }
}
