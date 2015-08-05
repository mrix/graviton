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
            ->setTarget(new Schema\Target());

        return new JsonDefinition($definition);
    }
}
