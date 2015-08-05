<?php
/**
 * test json definition
 */

namespace Graviton\GeneratorBundle\Tests\Definition;

use Graviton\GeneratorBundle\Definition\DefinitionElementInterface;
use Graviton\GeneratorBundle\Definition\JsonDefinition;
use Graviton\GeneratorBundle\Definition\JsonDefinitionEmbed;
use Graviton\GeneratorBundle\Definition\JsonDefinitionField;
use Graviton\GeneratorBundle\Definition\JsonDefinitionArray;
use Graviton\GeneratorBundle\Definition\JsonDefinitionHash;
use Graviton\GeneratorBundle\Definition\JsonDefinitionReference;
use Graviton\GeneratorBundle\Definition\Schema;
use JMS\Serializer\SerializerBuilder;

/**
 * @author   List of contributors <https://github.com/libgraviton/graviton/graphs/contributors>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://swisscom.ch
 */
class DefinitionTest extends \PHPUnit_Framework_TestCase
{
    private $fullDefPath;
    private $minimalPath;
    private $noIdPath;
    private $invalidPath;
    private $wrongUriPath;
    private $subDocumentPath;
    private $relationsPath;
    private $rolesPath;
    private $nestedFieldPath;
    private $nestedRelationsPath;

    /**
     * setup
     *
     * @return void
     */
    public function setUp()
    {
        $this->fullDefPath = __DIR__.'/resources/test-full.json';
        $this->minimalPath = __DIR__.'/resources/test-minimal.json';
        $this->noIdPath = __DIR__.'/resources/test-noid.json';
        $this->invalidPath = __DIR__.'/resources/test-invalid.json';
        $this->wrongUriPath = __DIR__.'/resources/test-minimal-wrong-uri.json';
        $this->subDocumentPath = __DIR__.'/resources/test-minimal-sub.json';
        $this->relationsPath = __DIR__.'/resources/test-minimal-relations.json';
        $this->rolesPath = __DIR__.'/resources/test-roles.json';
        $this->nestedFieldPath = __DIR__.'/resources/test-nested-fields.json';
        $this->nestedRelationsPath = __DIR__.'/resources/test-nested-relations.json';
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
     * invalid handling
     *
     * @expectedException \JMS\Serializer\Exception\RuntimeException
     *
     * @return void
     */
    public function testInvalidHandling()
    {
        $this->loadJsonDefinition($this->invalidPath);
    }

    /**
     * no id
     *
     * @expectedException \RuntimeException
     *
     * @return void
     */
    public function testNoId()
    {
        $this->loadJsonDefinition($this->noIdPath)->getId();
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
        $this->assertEquals('Showcase', $jsonDef->getId());
        $this->assertEquals('A service showcasing all of our generator features', $jsonDef->getDescription());
        $this->assertTrue($jsonDef->hasController());
        $this->assertTrue($jsonDef->hasFixtures());
        $this->assertFalse($jsonDef->isReadOnlyService());
    }

    /**
     * full
     *
     * @return void
     */
    public function testFull()
    {
        $jsonDef = $this->loadJsonDefinition($this->fullDefPath);
        $this->assertInstanceOf('Graviton\GeneratorBundle\Definition\JsonDefinition', $jsonDef);

        // we only assert what we didn't assert in testBasics()
        $this->assertEquals(0, count($jsonDef->getRelations()));
        $this->assertEquals(16, count($jsonDef->getFields()));
        $this->assertEquals('/hans/showcase', $jsonDef->getRouterBase());
        $this->assertEquals(5, $jsonDef->getFixtureOrder());
        $this->assertFalse($jsonDef->isSubDocument());

        $this->assertEquals(
            '\Graviton\CoreBundle\Controller\ShowcaseExtensionController',
            $jsonDef->getBaseController()
        );

        $this->assertInstanceOf(
            'Graviton\GeneratorBundle\Definition\JsonDefinitionField',
            $jsonDef->getField('anotherInt')
        );
    }

    /**
     * minimal
     *
     * @return void
     */
    public function testMinimal()
    {
        $jsonDef = $this->loadJsonDefinition($this->minimalPath);
        $this->assertInstanceOf('Graviton\GeneratorBundle\Definition\JsonDefinition', $jsonDef);

        $this->assertFalse($jsonDef->hasController());
        $this->assertFalse($jsonDef->hasFixtures());
        $this->assertFalse($jsonDef->getRouterBase());
        $this->assertTrue($jsonDef->isReadOnlyService());

        // test default behavior if nothing is specified..
        $this->assertEquals(0, count($jsonDef->getRelations()));
        $this->assertEquals(0, count($jsonDef->getFields()));
        $this->assertEquals(100, $jsonDef->getFixtureOrder());
        $this->assertEquals('RestController', $jsonDef->getBaseController());
        $this->assertNull($jsonDef->getField('test'));
        $this->assertNull($jsonDef->getNamespace());
        $this->assertFalse($jsonDef->isSubDocument());
    }

    /**
     * namespace
     *
     * @return void
     */
    public function testNamespaceSetting()
    {
        $jsonDef = $this->loadJsonDefinition($this->fullDefPath);

        $this->assertNull($jsonDef->getNamespace());
        $jsonDef->setNamespace('Hans\Namespace');
        $this->assertEquals('Hans\Namespace', $jsonDef->getNamespace());

        $jsonDef->setNamespace('Hans\Namespace\\');
        $this->assertEquals('Hans\Namespace', $jsonDef->getNamespace());
    }

    /**
     * sub document
     *
     * @return void
     */
    public function testSubDocument()
    {
        $jsonDef = $this->loadJsonDefinition($this->subDocumentPath);
        $this->assertTrue($jsonDef->isSubDocument());
    }

    /**
     * relations
     *
     * @return void
     */
    public function testRelations()
    {
        $jsonDef = $this->loadJsonDefinition($this->relationsPath);
        $relations = $jsonDef->getRelations();

        $this->assertEquals(4, count($relations));
        $this->assertContainsOnlyInstancesOf('Graviton\GeneratorBundle\Definition\Schema\Relation', $relations);

        $this->assertEquals(JsonDefinitionEmbed::REL_TYPE_EMBED, $relations['embedOne']->getType());
        $this->assertEquals(JsonDefinitionEmbed::REL_TYPE_EMBED, $relations['embedMany']->getType());
        $this->assertEquals(JsonDefinitionEmbed::REL_TYPE_REF, $relations['referenceOne']->getType());
        $this->assertEquals(JsonDefinitionEmbed::REL_TYPE_REF, $relations['referenceMany']->getType());


        /** @var JsonDefinitionEmbed $field */
        $field = $jsonDef->getField('embedOne');
        $this->assertInstanceOf('Graviton\GeneratorBundle\Definition\JsonDefinitionEmbed', $field);

        /** @var JsonDefinitionArray $arrayField */
        $arrayField = $jsonDef->getField('embedMany');
        $this->assertInstanceOf('Graviton\GeneratorBundle\Definition\JsonDefinitionArray', $arrayField);

        /** @var JsonDefinitionEmbed $arrayItem */
        $arrayItem = $arrayField->getElement();
        $this->assertInstanceOf('Graviton\GeneratorBundle\Definition\JsonDefinitionEmbed', $arrayItem);


        /** @var JsonDefinitionReference $field */
        $field = $jsonDef->getField('referenceOne');
        $this->assertInstanceOf('Graviton\GeneratorBundle\Definition\JsonDefinitionReference', $field);

        /** @var JsonDefinitionArray $arrayField */
        $arrayField = $jsonDef->getField('referenceMany');
        $this->assertInstanceOf('Graviton\GeneratorBundle\Definition\JsonDefinitionArray', $arrayField);

        /** @var JsonDefinitionReference $arrayItem */
        $arrayItem = $arrayField->getElement();
        $this->assertInstanceOf('Graviton\GeneratorBundle\Definition\JsonDefinitionReference', $arrayItem);
    }

    /**
     * uri fixing
     *
     * @return void
     */
    public function testUriFixing()
    {
        $jsonDef = $this->loadJsonDefinition($this->wrongUriPath);
        $this->assertInstanceOf('Graviton\GeneratorBundle\Definition\JsonDefinition', $jsonDef);
        $this->assertEquals('/hans/showcase', $jsonDef->getRouterBase());
    }

    /**
     * role set definition
     *
     * @return void
     */
    public function testRoles()
    {
        $jsonDef = $this->loadJsonDefinition($this->rolesPath);
        $this->assertInstanceOf('Graviton\GeneratorBundle\Definition\JsonDefinition', $jsonDef);
        $this->assertEquals(array('GRAVITON_USER'), $jsonDef->getRoles());
    }

    /**
     * @return void
     */
    public function testNestedFields()
    {
        $definition = $this->loadJsonDefinition($this->nestedFieldPath);
        $this->assertEquals(
            [
                'id' => new JsonDefinitionField(
                    'id',
                    (new Schema\Field())
                        ->setName('id')
                        ->setType('varchar')
                ),
                'hash' => new JsonDefinitionHash(
                    'hash',
                    $definition,
                    [
                        'abc' => new JsonDefinitionField(
                            'abc',
                            (new Schema\Field())
                                ->setName('hash.abc')
                                ->setType('integer')
                        ),
                        'def' => new JsonDefinitionField(
                            'def',
                            (new Schema\Field())
                                ->setName('hash.def')
                                ->setType('boolean')
                        ),
                    ]
                ),
                'array' => new JsonDefinitionArray(
                    'array',
                    new JsonDefinitionField(
                        'array',
                        (new Schema\Field())
                            ->setName('array.0')
                            ->setType('string')
                    )
                ),
                'arrayarray' => new JsonDefinitionArray(
                    'arrayarray',
                    new JsonDefinitionArray(
                        'arrayarray',
                        new JsonDefinitionArray(
                            'arrayarray',
                            new JsonDefinitionField(
                                'arrayarray',
                                (new Schema\Field())
                                    ->setName('arrayarray.0.0.0')
                                    ->setType('integer')
                            )
                        )
                    )
                ),
                'arrayhash' => new JsonDefinitionArray(
                    'arrayhash',
                    new JsonDefinitionHash(
                        'arrayhash',
                        $definition,
                        [
                            'mno' => new JsonDefinitionField(
                                'mno',
                                (new Schema\Field())
                                    ->setName('arrayhash.0.mno')
                                    ->setType('string')
                            ),
                            'pqr' => new JsonDefinitionField(
                                'pqr',
                                (new Schema\Field())
                                    ->setName('arrayhash.0.pqr')
                                    ->setType('float')
                            ),
                        ]
                    )
                ),
                'deep' => new JsonDefinitionArray(
                    'deep',
                    new JsonDefinitionHash(
                        'deep',
                        $definition,
                        [
                            'b' => new JsonDefinitionArray(
                                'b',
                                new JsonDefinitionHash(
                                    'b',
                                    $definition,
                                    [
                                        'c' => new JsonDefinitionHash(
                                            'c',
                                            $definition,
                                            [
                                                'd' => new JsonDefinitionHash(
                                                    'd',
                                                    $definition,
                                                    [
                                                        'e' => new JsonDefinitionField(
                                                            'e',
                                                            (new Schema\Field())
                                                                ->setName('deep.0.b.0.c.d.e')
                                                                ->setType('varchar')
                                                        ),
                                                    ]
                                                ),
                                            ]
                                        ),
                                    ]
                                )
                            ),
                            'c' => new JsonDefinitionField(
                                'c',
                                (new Schema\Field())
                                    ->setName('deep.0.c')
                                    ->setType('string')
                            ),
                            'd' => new JsonDefinitionArray(
                                'd',
                                new JsonDefinitionField(
                                    'd',
                                    (new Schema\Field())
                                        ->setName('deep.0.d.0')
                                        ->setType('integer')
                                )
                            ),
                        ]
                    )
                ),
            ],
            $definition->getFields()
        );
    }

    /**
     * @return void
     */
    public function testHashToJsonDefinition()
    {
        $definition = $this->loadJsonDefinition($this->nestedFieldPath);

        /** @var JsonDefinitionHash $field */
        $field = $this->getFieldByPath($definition, 'hash');
        $this->assertInstanceOf('Graviton\GeneratorBundle\Definition\JsonDefinitionHash', $field);

        $this->assertEquals(
            (new JsonDefinition(
                (new Schema\Definition())
                    ->setId('FieldTestHash')
                    ->setIsSubDocument(true)
                    ->setTarget(
                        (new Schema\Target())
                            ->addField(
                                (new Schema\Field())
                                    ->setName('abc')
                                    ->setType('integer')
                            )
                            ->addField(
                                (new Schema\Field())
                                    ->setName('def')
                                    ->setType('boolean')
                            )
                    )
            )),
            $field->getJsonDefinition()
        );

        /** @var JsonDefinitionHash $field */
        $field = $this->getFieldByPath($definition, 'arrayhash.0');
        $this->assertInstanceOf('Graviton\GeneratorBundle\Definition\JsonDefinitionHash', $field);

        $this->assertEquals(
            (new JsonDefinition(
                (new Schema\Definition())
                    ->setId('FieldTestArrayhash')
                    ->setIsSubDocument(true)
                    ->setTarget(
                        (new Schema\Target())
                            ->addField(
                                (new Schema\Field())
                                    ->setName('mno')
                                    ->setType('string')
                            )
                            ->addField(
                                (new Schema\Field())
                                    ->setName('pqr')
                                    ->setType('float')
                            )
                    )
            )),
            $field->getJsonDefinition()
        );

        /** @var JsonDefinitionHash $field */
        $field = $this->getFieldByPath($definition, 'deep.0');
        $this->assertInstanceOf('Graviton\GeneratorBundle\Definition\JsonDefinitionHash', $field);

        $this->assertEquals(
            new JsonDefinition(
                (new Schema\Definition())
                    ->setId('FieldTestDeep')
                    ->setIsSubDocument(true)
                    ->setTarget(
                        (new Schema\Target())
                            ->addField(
                                (new Schema\Field())
                                    ->setName('b.0.c.d.e')
                                    ->setType('varchar')
                            )
                            ->addField(
                                (new Schema\Field())
                                    ->setName('c')
                                    ->setType('string')
                            )
                            ->addField(
                                (new Schema\Field())
                                    ->setName('d.0')
                                    ->setType('integer')
                            )
                    )
            ),
            $field->getJsonDefinition()
        );
    }

    /**
     * @return void
     * @group tmp
     */
    public function testNestedRelations()
    {
        $definition = $this->loadJsonDefinition($this->nestedRelationsPath);

        /** @var JsonDefinitionHash $field */
        $field = $this->getFieldByPath($definition, 'hash');
        $this->assertInstanceOf('Graviton\GeneratorBundle\Definition\JsonDefinitionHash', $field);

        $this->assertEquals(
            (new JsonDefinition(
                (new Schema\Definition())
                    ->setId('RelationTestHash')
                    ->setIsSubDocument(true)
                    ->setTarget(
                        (new Schema\Target())
                            ->addField(
                                (new Schema\Field())
                                    ->setName('referenceOne')
                                    ->setType('class:Entity')
                            )
                            ->addField(
                                (new Schema\Field())
                                    ->setName('referenceMany')
                                    ->setType('class:Entity[]')
                            )
                            ->addField(
                                (new Schema\Field())
                                    ->setName('embedOne')
                                    ->setType('class:Entity')
                            )
                            ->addField(
                                (new Schema\Field())
                                    ->setName('embedMany')
                                    ->setType('class:Entity[]')
                            )
                            ->addRelation(
                                (new Schema\Relation())
                                    ->setType(JsonDefinitionEmbed::REL_TYPE_EMBED)
                                    ->setLocalProperty('embedOne')
                            )
                            ->addRelation(
                                (new Schema\Relation())
                                    ->setType(JsonDefinitionEmbed::REL_TYPE_EMBED)
                                    ->setLocalProperty('embedMany')
                            )
                    )
            )),
            $field->getJsonDefinition()
        );

        /** @var JsonDefinitionEmbed $embedField */
        $embedField = $this->getFieldByPath($definition, 'hash.embedOne');
        $this->assertInstanceOf('Graviton\GeneratorBundle\Definition\JsonDefinitionEmbed', $embedField);
        $this->assertEquals('Entity', $embedField->getClassName());

        /** @var JsonDefinitionArray $embedArray */
        $embedField = $this->getFieldByPath($definition, 'hash.embedMany.0');
        $this->assertInstanceOf('Graviton\GeneratorBundle\Definition\JsonDefinitionEmbed', $embedField);
        $this->assertEquals('Entity', $embedField->getClassName());

        /** @var JsonDefinitionEmbed $referenceField */
        $referenceField = $this->getFieldByPath($definition, 'hash.referenceOne');
        $this->assertInstanceOf('Graviton\GeneratorBundle\Definition\JsonDefinitionEmbed', $referenceField);
        $this->assertEquals('Entity', $referenceField->getClassName());

        /** @var JsonDefinitionEmbed $referenceField */
        $referenceField = $this->getFieldByPath($definition, 'hash.referenceMany.0');
        $this->assertInstanceOf('Graviton\GeneratorBundle\Definition\JsonDefinitionEmbed', $referenceField);
        $this->assertEquals('Entity', $referenceField->getClassName());



        /** @var JsonDefinitionEmbed $embedField */
        $embedField = $this->getFieldByPath($definition, 'deep.0.sub.embedOne');
        $this->assertInstanceOf('Graviton\GeneratorBundle\Definition\JsonDefinitionEmbed', $embedField);
        $this->assertEquals('Entity', $embedField->getClassName());

        /** @var JsonDefinitionArray $embedArray */
        $embedField = $this->getFieldByPath($definition, 'deep.0.sub.subsub.0.embedMany.0');
        $this->assertInstanceOf('Graviton\GeneratorBundle\Definition\JsonDefinitionEmbed', $embedField);
        $this->assertEquals('Entity', $embedField->getClassName());

        /** @var JsonDefinitionEmbed $referenceField */
        $referenceField = $this->getFieldByPath($definition, 'deep.0.sub.referenceOne');
        $this->assertInstanceOf('Graviton\GeneratorBundle\Definition\JsonDefinitionEmbed', $referenceField);
        $this->assertEquals('Entity', $referenceField->getClassName());

        /** @var JsonDefinitionEmbed $referenceField */
        $referenceField = $this->getFieldByPath($definition, 'deep.0.sub.subsub.0.referenceMany.0');
        $this->assertInstanceOf('Graviton\GeneratorBundle\Definition\JsonDefinitionEmbed', $referenceField);
        $this->assertEquals('Entity', $referenceField->getClassName());
    }

    /**
     * @param JsonDefinition $definition
     * @param string         $path
     * @return DefinitionElementInterface
     */
    private function getFieldByPath(JsonDefinition $definition, $path)
    {
        $result = $definition;
        foreach (explode('.', $path) as $item) {
            if ($result instanceof JsonDefinition) {
                $this->assertNotEquals('0', $item);
                $result = $result->getField($item);
            } elseif ($result instanceof JsonDefinitionHash) {
                $this->assertNotEquals('0', $item);
                $result = $result->getJsonDefinition()->getField($item);
            } elseif ($result instanceof JsonDefinitionArray) {
                $this->assertEquals('0', $item);
                $result = $result->getElement();
            } else {
                $this->fail(sprintf('Cannot get subfield "%s" from "%s"', $item, get_class($result)));
            }
        }

        return $result;
    }
}
