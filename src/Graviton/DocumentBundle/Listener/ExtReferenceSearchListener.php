<?php
/**
 * generate url from raw db data
 *
 * Here we get the raw structure that has been hydrated for $ref link cases
 * by doctrine and replace it with a route generated by the symfony router.
 * We do this in it's own listener due to the fact that there is no way that
 * we can inject anything useable into the default odm hydrator and it looks
 * rather futile to hack it so we can use our own custom hydration code.
 */

namespace Graviton\DocumentBundle\Listener;

use Doctrine\ODM\MongoDB\Query\Builder;
use Graviton\DocumentBundle\Service\ExtReferenceConverterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Graviton\Rql\Event\VisitNodeEvent;
use Xiag\Rql\Parser\AbstractNode;
use Xiag\Rql\Parser\Node\Query\AbstractArrayOperatorNode;
use Xiag\Rql\Parser\Node\Query\AbstractScalarOperatorNode;

/**
 * @author   List of contributors <https://github.com/libgraviton/graviton/graphs/contributors>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://swisscom.ch
 */
class ExtReferenceSearchListener
{
    /**
     * @var ExtReferenceConverterInterface
     */
    private $converter;

    /**
     * @var array
     */
    private $fields;

    /**
     * @var Request
     */
    private $request;

    /**
     * construct
     *
     * @param ExtReferenceConverterInterface $converter Extref converter
     * @param array                          $fields    map of fields to process
     * @param RequestStack                   $requests  request
     */
    public function __construct(ExtReferenceConverterInterface $converter, array $fields, RequestStack $requests)
    {
        $this->converter = $converter;
        $this->fields = $fields;
        $this->request = $requests->getCurrentRequest();
    }

    /**
     * @param VisitNodeEvent $event node event to visit
     *
     * @return VisitNodeEvent
     */
    public function onVisitNode(VisitNodeEvent $event)
    {
        $node = $event->getNode();
        if (!$this->checkNode($node)) {
            return $event;
        }

        $builder = $event->getBuilder();
        if ($node instanceof AbstractScalarOperatorNode) {
            $this->processScalarNode($node, $builder);
        } elseif ($node instanceof AbstractArrayOperatorNode) {
            $this->processArrayNode($node, $builder);
        } else {
            return $event;
        }

        $event->setNode(null);
        $event->setBuilder($builder);
        return $event;
    }

    /**
     * Is allowed node
     *
     * @param AbstractNode $node Query node
     * @return bool
     */
    private function checkNode(AbstractNode $node)
    {
        $route = $this->request->attributes->get('_route');
        if (!array_key_exists($route, $this->fields)) {
            throw new \LogicException('Missing ' . $route . ' from extref fields map.');
        }


        if (!$node instanceof AbstractScalarOperatorNode &&
            !$node instanceof AbstractArrayOperatorNode) {
            return false;
        }
        if (!in_array(strtr($node->getField(), ['..' => '.0.']), $this->fields[$route])) {
            return false;
        }

        return true;
    }

    /**
     * Process scalar condition
     *
     * @param AbstractScalarOperatorNode $node    Query node
     * @param Builder                    $builder Query builder
     * @return void
     */
    private function processScalarNode(AbstractScalarOperatorNode $node, Builder $builder)
    {
        try {
            $dbRef = $this->converter->getDbRef($node->getValue());
        } catch (\InvalidArgumentException $e) {
            //make up some invalid refs to ensure we find nothing if an invalid url was given
            $dbRef = (object) ['$ref' => false, '$id' => false];
        }

        $operatorMap = [
            'eq' => 'equals',
            'ne' => 'notEqual',
            'lt' => 'lt',
            'gt' => 'gt',
            'le' => 'lte',
            'ge' => 'gte',
        ];
        if (!isset($operatorMap[$node->getNodeName()])) {
            throw new \InvalidArgumentException(
                sprintf('Could not apply operator "%s" to extref', $node->getNodeName())
            );
        }

        $compareOperator = $operatorMap[$node->getNodeName()];
        $builder
            ->field(strtr($node->getField(), ['$' => '', '..' => '.0.']) . '.$ref')
            ->equals($dbRef->{'$ref'})
            ->field(strtr($node->getField(), ['$' => '', '..' => '.0.']) . '.$id')
            ->$compareOperator($dbRef->{'$id'});
    }

    /**
     * Process array condition
     *
     * @param AbstractArrayOperatorNode $node    Query node
     * @param Builder                   $builder Query builder
     * @return void
     */
    private function processArrayNode(AbstractArrayOperatorNode $node, Builder $builder)
    {
        if ($node->getValues() === []) {
            return;
        }

        $operatorMap = [
            'in'  => ['addOr', 'equals'],
            'out' => ['addAnd', 'notEqual'],
        ];
        if (!isset($operatorMap[$node->getNodeName()])) {
            throw new \InvalidArgumentException(
                sprintf('Could not apply operator "%s" to extref', $node->getNodeName())
            );
        }

        $values = [];
        foreach ($node->getValues() as $url) {
            try {
                $values[] = $this->converter->getDbRef($url);
            } catch (\InvalidArgumentException $e) {
                //make up some invalid refs to ensure we find nothing if an invalid url was given
                $values[] = (object) ['$ref' => false, '$id' => false];
            }
        }


        list($groupOperator, $compareOperator) = $operatorMap[$node->getNodeName()];

        $expr = $builder->expr();
        foreach ($values as $dbRef) {
            $expr->$groupOperator(
                $builder->expr()
                    ->field(strtr($node->getField(), ['$' => '', '..' => '.0.']) . '.$ref')
                    ->equals($dbRef->{'$ref'})
                    ->field(strtr($node->getField(), ['$' => '', '..' => '.0.']) . '.$id')
                    ->$compareOperator($dbRef->{'$id'})
            );
        }
        $builder->addAnd($expr);
    }
}
