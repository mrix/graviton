<?php

/**
 * Publishes document level messages to the messaging bus.
 */

namespace Graviton\RabbitMqBundle\Service;

use Doctrine\Common\EventSubscriber;
use Doctrine\ODM\MongoDB\Event\LifecycleEventArgs;
use Graviton\RabbitMqBundle\Document\QueueEvent;
use Monolog\Logger;
use OldSound\RabbitMqBundle\RabbitMq\ProducerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Publishes document level messages to the messaging bus and creates a new JobStatus Document.
 * Moreover, this class can be used as a Doctrine Event Subscriber to automatically publish the postPersist,
 * postUpdate and postRemove events.
 *
 * @author   List of contributors <https://github.com/libgraviton/graviton/graphs/contributors>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://swisscom.ch
 */
class DocumentEventPublisher implements EventSubscriber
{

    /**
     * @var array Holds additionalProperties to be sent with the message.
     * @see publishMessage()
     */
    public $additionalProperties = array();

    /**
     * @var ProducerInterface Producer for publishing messages.
     */
    protected $rabbitMqProducer = null;

    /**
     * @var Logger Logger
     */
    protected $logger = null;

    /**
     * @var RouterInterface Router to generate resource URLs
     */
    protected $router = null;

    /**
     * @var array mapping from class shortname ("collection") to controller service
     */
    private $documentMapping = array();

    /**
     * @var QueueEvent queueevent document
     */
    private $queueEventDocument;

    /**
     * @param ProducerInterface $rabbitMqProducer   RabbitMQ dependency
     * @param LoggerInterface   $logger             Logger dependency
     * @param RouterInterface   $router             Router dependency
     * @param QueueEvent        $queueEventDocument queueevent document
     * @param array             $documentMapping    document mapping
     */
    public function __construct(
        ProducerInterface $rabbitMqProducer,
        LoggerInterface $logger,
        RouterInterface $router,
        QueueEvent $queueEventDocument,
        $documentMapping
    ) {
        $this->rabbitMqProducer = $rabbitMqProducer;
        $this->logger = $logger;
        $this->router = $router;
        $this->queueEventDocument = $queueEventDocument;
        $this->documentMapping = $documentMapping;
    }

    /**
     * @return array Defines the doctrine events to subscribe to.
     */
    public function getSubscribedEvents()
    {
        return array(
            'postPersist',
            'postUpdate',
            'postRemove',
        );
    }

    /**
     * Doctrine postPersist event listener
     *
     * @param LifecycleEventArgs $args Event Arguments
     *
     * @return void
     */
    public function postPersist(LifecycleEventArgs $args)
    {
        $this->publishEvent($args->getDocument(), 'create');
    }

    /**
     * Doctrine postUpdate event listener
     *
     * @param LifecycleEventArgs $args Event Arguments
     *
     * @return void
     */
    public function postUpdate(LifecycleEventArgs $args)
    {
        $this->publishEvent($args->getDocument(), 'update');
    }

    /**
     * Doctrine postRemove event listener
     *
     * @param LifecycleEventArgs $args Event Arguments
     *
     * @return void
     */
    public function postRemove(LifecycleEventArgs $args)
    {
        $this->publishEvent($args->getDocument(), 'delete');
    }

    /**
     * Created the structured object that will be sent to the queue
     *
     * @param object $document The document for determining message and routing key
     * @param string $event    What type of event
     *
     * @return \stdClass
     */
    private function createQueueEventObject($document, $event)
    {
        $obj = clone $this->queueEventDocument;
        $obj->setClassname(get_class($document));
        $obj->setRecordid($document->getId());
        $obj->setEvent($event);

        // get the public facing url (if available)
        $documentClass = new \ReflectionClass($document);
        $shortName = $documentClass->getShortName();

        if (isset($this->documentMapping[$shortName])) {
            $obj->setPublicurl(
                $this->router->generate(
                    $this->documentMapping[$shortName] . '.get',
                    ['id' => $document->getId()],
                    true
                )
            );
        }

        // compose routing key
        // here, we're generating something arbitrary that is properly topic based (namespaced)
        $baseKey = str_replace('\\', '.', strtolower($obj->getClassname()));
        list(, $bundle, , $document) = explode('.', $baseKey);

        // will be ie. 'document.core.app.create' for /core/app creation
        $routingKey = 'document.'.
            str_replace('bundle', '', $bundle).
            '.'.
            $document.
            '.'.
            $event;

        $obj->setRoutingKey($routingKey);

        return $obj;
    }

    /**
     * Creates a new JobStatus document. Then publishes it's id with a message onto the message bus.
     * The message and routing key get determined by a given document and an action name.
     *
     * @param object $document The document for determining message and routing key
     * @param string $event    The action name
     *
     * @return bool Whether a message has been successfully sent to the message bus or not
     */
    public function publishEvent($document, $event)
    {
        $queueObject = $this->createQueueEventObject($document, $event);

        $this->rabbitMqProducer->publish(
            json_encode($queueObject),
            $queueObject->getRoutingKey()
        );

        return true;
    }
}
