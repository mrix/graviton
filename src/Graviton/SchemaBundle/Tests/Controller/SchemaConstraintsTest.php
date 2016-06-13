<?php
/**
 * integration tests for our supported constraints
 */

namespace Graviton\SchemaBundle\Tests\Controller;

use Graviton\TestBundle\Test\RestTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Functional test for /hans/showcase
 *
 * @author   List of contributors <https://github.com/libgraviton/graviton/graphs/contributors>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://swisscom.ch
 */
class SchemaConstraintsTest extends RestTestCase
{
    /**
     * tests schema based validation constraints
     *
     * @param string $field         field
     * @param string $acceptedValue accepted value
     * @param string $rejectedValue rejected value
     * @param string $errorMessage  expected error message
     *
     * @dataProvider schemaConstraintDataProvider
     *
     * @return void
     */
    public function testSchemaConstraint($field, $acceptedValue, $rejectedValue, $errorMessage)
    {
        // test accepted value
        $object = new \stdClass();
        $object->{$field} = $acceptedValue;

        $client = static::createRestClient();
        $client->post('/testcase/schema-constraints/', $object);
        $this->assertEquals(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
        $this->assertNull($client->getResults());

        // test rejected value
        $object = new \stdClass();
        $object->{$field} = $rejectedValue;

        $client = static::createRestClient();
        $client->post('/testcase/schema-constraints/', $object);
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());
        $results = $client->getResults();
        $this->assertEquals($field, $results[0]->propertyPath);
        $this->assertEquals($errorMessage, $results[0]->message);
    }

    /**
     * Data provider for constraint test
     *
     * @return array data
     */
    public function schemaConstraintDataProvider()
    {
        return [
            'choice-string' => [
                'field' => 'choiceString',
                'acceptedValue' => 'a lo mejor',
                'rejectedValue' => 'no puedo',
                'errorMessage' => 'Does not have a value in the enumeration ["si","no","a lo mejor","mas"]'
            ],
            'choice-integer' => [
                'field' => 'choiceInteger',
                'acceptedValue' => 0,
                'rejectedValue' => 5,
                'errorMessage' => 'Does not have a value in the enumeration [0,1,2]'
            ],
            'email' => [
                'field' => 'email',
                'acceptedValue' => 'hans.hofer@swisscom.com',
                'rejectedValue' => 'invalidemail@sss.',
                'errorMessage' => 'Invalid email'
            ]
        ];
    }
}
