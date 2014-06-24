<?php

namespace Graviton\RestBundle\Controller;

use JMS\Serializer\Exception\Exception;
use JMS\Serializer\Serializer;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Graviton\SchemaBundle\SchemaUtils;
use Graviton\I18nBundle\Document\TranslatableDocumentInterface;

/**
 * This is a basic rest controller. It should fit the most needs but if you need to add some
 * extra functionality you can extend it and overwrite single/all actions.
 * You can also extend the model class to add some extra logic before save
 *
 * @category GravitonRestBundle
 * @package  Graviton
 * @author   Manuel Kipfer <manuel.kipfer@swisscom.com>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://swisscom.com
 */
class RestController implements ContainerAwareInterface
{
    private $model;

    /**
     * @var \Symfony\Component\DependencyInjection\ContainerInterface service_container
     */
    private $container;

    /**
     * {@inheritdoc}
     *
     * @param \Symfony\Component\DependencyInjection\ContainerInterface $container service_container
     *
     * @return void
     */
    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * Returns a single record
     *
     * @param string $id ID of record
     *
     * @return \Symfony\Component\HttpFoundation\Response $response Response with result or error
     */
    public function getAction($id)
    {
        return $this->getResponse(
            $this->getModel()->find($id)
        );
    }

    /**
     * Returns all records
     *
     * @return \Symfony\Component\HttpFoundation\Response $response Response with result or error
     */
    public function allAction()
    {
        return $this->getResponse(
            $this->getModel()->findAll($this->getRequest())
        );
    }

    /**
     * Writes a new Entry to the database
     *
     * @return \Symfony\Component\HttpFoundation\Response $response Result of action with data (if successful)
     */
    public function postAction()
    {
        $record = $this->getSerializer()->deserialize(
            $this->getRequest()->getContent(),
            $this->getModel()->getEntityClass(),
            'json'
        );

        // store id of new record so we dont need to reparse body later when needed
        $this->getRequest()->attributes->set('id', $record->getId());

        $response = $this->validateRecord($record);

        if (!$response) {
            $baseName = basename(strtr($this->model->getEntityClass(), '\\', '/'));
            $serviceName = $this->model->getConnectionName().'.rest.'.strtolower($baseName);
            $record = $this->getModel()->insertRecord($record);
            $response = $this->container->get('graviton.rest.response.201');
            $response = $this->setContent($response, $record);
            $response->headers->set(
                'Location',
                $this->getRouter()->generate($serviceName.'.get', array('id' => $record->getId()))
            );
        }

        return $response;
    }

    /**
     * Update a record
     *
     * @param Number $id ID of record
     *
     * @return \Symfony\Component\HttpFoundation\Response $response Result of action with data (if successful)
     */
    public function putAction($id)
    {
        $record = $this->getSerializer()->deserialize(
            $this->getRequest()->getContent(),
            $this->getModel()->getEntityClass(),
            'json'
        );

        $response = $this->validateRecord($record);

        if (!$response) {
            $existingRecord = $this->getModel()->find($id);
            if (!$existingRecord) {
                $response = $this->container->get('graviton.rest.response.404');
            } else {
                $record = $this->getModel()->updateRecord($id, $record);
                $response = $this->container->get('graviton.rest.response.200');
                $response = $this->setContent($response, $record);
            }
        }

        return $response;
    }

    /**
     * Deletes a record
     *
     * @param Number $id ID of record
     *
     * @return \Symfony\Component\HttpFoundation\Response $response Result of the action
     */
    public function deleteAction($id)
    {
        $response = $this->container->get('graviton.rest.response.404');

        if (is_null($this->getModel()->deleteRecord($id))) {
            $response = $this->container->get('graviton.rest.response.200');
        }

        return $response;
    }

    /**
     * Return OPTIONS results.
     *
     * @param string $id ID of record
     *
     * @return \Symfony\Component\HttpFoundation\Response $response Result of the action
     */
    public function optionsAction($id = null)
    {
        $request = $this->getRequest();
        $request->attributes->set('schemaRequest', true);

        list($app, $module, , $modelName, $schemaType) = explode('.', $request->attributes->get('_route'));
        $model = $this->container->get(implode('.', array($app, $module, 'model', $modelName)));
        $document = $this->container->get(implode('.', array($app, $module, 'document', $modelName)));

        $translatableFields = array();
        $languages = array();
        if ($document instanceof TranslatableDocumentInterface) {
            $translatableFields = $document->getTranslatableFields();
            $languages = array_map(
                function ($language) {
                    return $language->getId();
                },
                $this->container->get('graviton.i18n.repository.language')->findAll()
            );
        }

        $response = $this->container->get('graviton.rest.response.200');
        $schemaMethod = 'getModelSchema';
        if (!$id && $schemaType != 'canonicalIdSchema') {
            $schemaMethod =  'getCollectionSchema';
        }
        $schema = SchemaUtils::$schemaMethod($modelName, $model, $translatableFields, $languages);
        $response->setContent(
            $this->getSerializer()->serialize($schema, 'json')
        );

        // enabled methods for CorsListener
        $corsMethods = 'GET, POST, PUT, DELETE, OPTIONS';
        try {
            $router = $this->getRouter();
            // if post route is available we assume everything is readable
            $router->generate(implode('.', array($app, $module, 'rest', $modelName, 'post')));
        } catch (RouteNotFoundException $exception) {
            // only allow read methods
            $corsMethods = 'GET, OPTIONS';
        }
        $request->attributes->set('corsMethods', $corsMethods);

        return $response;
    }

    /**
     * Get request
     *
     * @return \Symfony\Component\HttpFoundation\Request $request Request object
     */
    public function getRequest()
    {
        return $this->container->get('graviton.rest.request');
    }

    /**
     * Set the model class
     *
     * @param object $model Model class
     *
     * @return self
     */
    public function setModel($model)
    {
        $this->model = $model;

        return $this;
    }

    /**
     * Return the model
     *
     * @return object $model Model
     */
    public function getModel()
    {
        if (!$this->model) {
            throw new \Exception('No model is set for this controller');
        }

        return $this->model;
    }

    /**
     * Get the serializer
     *
     * @return \JMS\Serializer\Serializer\Serializer
     */
    public function getSerializer()
    {
        return $this->container->get('graviton.rest.serializer');
    }

    /**
     * Get the serializer context
     *
     * @return null|\JMS\Serializer\SerializationContext
     */
    public function getSerializerContext()
    {
        return $this->container->get('graviton.rest.serializer.serializercontext');
    }

    /**
     * Get the validator
     *
     * @return \Symfony\Component\Validator\Validator
     */
    public function getValidator()
    {
        return $this->container->get('graviton.rest.validator');
    }

    /**
     * Get the router from the dic
     *
     * @return \Symfony\Bundle\FrameworkBundle\Routing\Router
     */
    public function getRouter()
    {
        return $this->container->get('graviton.rest.router');
    }

    /**
     * validate a record and return an approriate reponse
     *
     * @param Object $record record to validate
     *
     * @return \Symfony\Component\HttpFoundation\Response|null
     */
    private function validateRecord($record)
    {
        $content = $this->getRequest()->getContent();
        if (is_resource($content)) {
            throw new \LogicException('unexpected resource in validation');
        }
        // override values from serializer with real ones from request to get originals validated
        foreach (json_decode($content) as $key => $value) {
            $setterMethod = 'set'.ucfirst($key);
            // this is a very cheap way to skip i18n entries
            // as always with this method, it needs refactoring badly
            if (!is_object($value)) {
                $record->$setterMethod($value);
            }
        }

        $validationErrors = $this->getValidator()->validate($record);

        $response =  null;
        if (count($validationErrors) > 0) {
            $response = $this->container->get('graviton.rest.response.400');
            $response = $this->setContent($response, $validationErrors);
        }

        return $response;
    }

    /**
     * create responses for simple get cases
     *
     * @param Object|Object[] $result result to base response on
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    private function getResponse($result)
    {
        $response = $this->container->get('graviton.rest.response.404');
        if (!is_null($result)) {
            $response = $this->container->get('graviton.rest.response.200');
            $response = $this->setContent($response, $result);
        }

        return $response;
    }

    /**
     * set content on response
     *
     * @param \Symfony\Component\HttpFoundation\Response $response reponse to edit
     * @param Object|Object[]                            $content  object to serialize into content
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    private function setContent(Response $response, $content)
    {
        $response->setContent(
            $this->getSerializer()->serialize(
                $content,
                'json',
                $this->getSerializerContext()
            )
        );

        return $response;
    }
}
