<?php
/**
 * test json definition element
 */

namespace Graviton\GeneratorBundle\Tests\Definition;

use Graviton\GeneratorBundle\Definition\JsonDefinition;
use Graviton\GeneratorBundle\Definition\JsonDefinitionEmbed;
use Graviton\GeneratorBundle\Definition\JsonDefinitionField;
use Graviton\GeneratorBundle\Definition\JsonDefinitionHash;
use Graviton\GeneratorBundle\Definition\JsonDefinitionArray;
use JMS\Serializer\SerializerBuilder;

/**
 * @author   List of contributors <https://github.com/libgraviton/graviton/graphs/contributors>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://swisscom.ch
 */
class DefinitionElementTest extends \PHPUnit_Framework_TestCase
{

    private $fullDefPath;

    /**
     * setup
     *
     * @return void
     */
    public function setUp()
    {
        $this->fullDefPath = __DIR__.'/resources/test-full.json';
    }

    /**
     * @param string $file Definition file path
     * @return JsonDefinition
     */
    private function loadJsonDefinition($file)
    {
        $serializer = SerializerBuilder::create()
            ->addDefaultHandlers()
            ->addDefaultSerializationVisitors()
            ->addDefaultDeserializationVisitors()
            ->addMetadataDir(__DIR__.'/../../Resources/config/serializer', 'Graviton\\GeneratorBundle')
            ->setCacheDir(sys_get_temp_dir())
            ->setDebug(true)
            ->build();

        return new JsonDefinition(
            $serializer->deserialize(
                file_get_contents($file),
                'Graviton\\GeneratorBundle\\Definition\\Schema\\Definition',
                'json'
            )
        );
    }

    /**
     * basics
     *
     * @return void
     */
    public function testBasics()
    {
        $jsonDef = $this->loadJsonDefinition($this->fullDefPath);
        $this->assertInstanceOf('Graviton\GeneratorBundle\Definition\JsonDefinition', $jsonDef);

        /** @var JsonDefinitionField $field */
        $field = $jsonDef->getField('testField');
        $this->assertInstanceOf('Graviton\GeneratorBundle\Definition\JsonDefinitionField', $field);
        $this->assertInstanceOf('Graviton\GeneratorBundle\Definition\Schema\Field', $field->getDef());

        $this->assertArraySubset(
            [
                'name'              => $field->getName(),
                'exposedName'       => 'testField',
                'type'              => 'varchar',
                'doctrineType'      => 'string',
                'serializerType'    => 'string',
                'isClassType'       => false,

                'description'       => 'A lengthy and detailed description.',
                'length'            => 200,
                'readOnly'          => false,
            ],
            $field->getDefAsArray()
        );
    }

    /**
     * arraydef
     *
     * @return void
     */
    public function testArrayDef()
    {
        $jsonDef = $this->loadJsonDefinition($this->fullDefPath);
        $field = $jsonDef->getField('testField');

        $def = array(
            'name' => 'testField',
            'type' => 'varchar',
            'length' => 200,
            'title' => 'A testing title',
            'description' => 'A lengthy and detailed description.',
            'readOnly' => false,
            'required' => true,
            'translatable' => true,
            'exposedName' => 'testField',
            'doctrineType' => 'string',
            'serializerType' => 'string',
            'isClassType' => false,
            'constraints' => array(),
            'collection' => array()
        );

        $this->assertEquals($def, $field->getDefAsArray());
    }

    /**
     * Embedded fields
     *
     * @return void
     */
    public function testEmbedFields()
    {
        $jsonDef = $this->loadJsonDefinition($this->fullDefPath);

        /** @var JsonDefinitionEmbed $embedField */
        $embedField = $jsonDef->getField('contact');
        $this->assertInstanceOf('Graviton\GeneratorBundle\Definition\JsonDefinitionReference', $embedField);
        $this->assertEquals('Graviton\PersonBundle\Document\PersonContact', $embedField->getType());
        $this->assertEquals('Graviton\PersonBundle\Document\PersonContact', $embedField->getTypeSerializer());
        $this->assertEquals('Graviton\PersonBundle\Document\PersonContact', $embedField->getTypeDoctrine());

        /** @var JsonDefinitionArray $arrayField */
        $arrayField = $jsonDef->getField('contacts');
        $this->assertInstanceOf('Graviton\GeneratorBundle\Definition\JsonDefinitionArray', $arrayField);
        $this->assertInstanceOf('Graviton\GeneratorBundle\Definition\JsonDefinitionReference', $arrayField->getElement());
        $this->assertEquals('Graviton\PersonBundle\Document\PersonContact[]', $arrayField->getType());
        $this->assertEquals('array<Graviton\PersonBundle\Document\PersonContact>', $arrayField->getTypeSerializer());
        $this->assertEquals('Graviton\PersonBundle\Document\PersonContact[]', $arrayField->getTypeDoctrine());
    }

    /**
     * wrong type handling
     *
     * @return void
     */
    public function testWrongType()
    {
        // test fallback to string..
        $jsonDef = $this->loadJsonDefinition($this->fullDefPath);
        $field = $jsonDef->getField('unknownType');
        $this->assertEquals('unknown', $field->getType());
        $this->assertEquals('string', $field->getTypeSerializer());
        $this->assertEquals('string', $field->getTypeDoctrine());
    }

    /**
     * expose as
     *
     * @return void
     */
    public function testExposeAs()
    {
        $jsonDef = $this->loadJsonDefinition($this->fullDefPath);

        /** @var JsonDefinitionField $field */
        $field = $jsonDef->getField('unknownType');
        $this->assertEquals('unknown', $field->getExposedName());
    }

    /**
     * hash
     *
     * @return void
     */
    public function testHash()
    {
        $jsonDef = $this->loadJsonDefinition($this->fullDefPath);

        /** @var JsonDefinitionHash $field */
        $field = $jsonDef->getField('contactCode');
        $this->assertInstanceOf('Graviton\GeneratorBundle\Definition\JsonDefinitionHash', $field);

        $this->assertEquals(
            'datetime',
            $field->getJsonDefinition()->getFields()['someDate']->getType()
        );
        $this->assertEquals(
            'varchar',
            $field->getJsonDefinition()->getFields()['text']->getType()
        );
    }

    /**
     * array def
     *
     * @return void
     */
    public function testHashArrayDef()
    {
        $jsonDef = $this->loadJsonDefinition($this->fullDefPath);

        $field = $jsonDef->getField('contactCode');
        $this->assertInstanceOf('Graviton\GeneratorBundle\Definition\JsonDefinitionHash', $field);

        $def = [
            'type' => 'hash',
            'name' => 'contactCode',
            'exposedName' => 'contactCode',
            'relType' => $field::REL_TYPE_EMBED,
            'doctrineType' => '\Document\ShowcaseContactCode',
            'serializerType' => '\Document\ShowcaseContactCode',
            'isClassType' => true,
            'constraints' => [],
        ];

        $this->assertEquals($def, $field->getDefAsArray());
    }

    /**
     * local def
     *
     * @return void
     */
    public function testHashLocalDef()
    {
        $jsonDef = $this->loadJsonDefinition($this->fullDefPath);

        /** @var JsonDefinitionHash $hashField */
        $hashField = $jsonDef->getField('contactCode');
        $this->assertInstanceOf('Graviton\GeneratorBundle\Definition\JsonDefinitionHash', $hashField);

        /** @var JsonDefinitionArray $arrayField */
        $arrayField = $jsonDef->getField('nestedArray');
        $this->assertInstanceOf('Graviton\GeneratorBundle\Definition\JsonDefinitionArray', $arrayField);

        /** @var JsonDefinitionHash $arrayItem */
        $arrayItem = $arrayField->getElement();
        $this->assertInstanceOf('Graviton\GeneratorBundle\Definition\JsonDefinitionHash', $arrayItem);
    }
}
