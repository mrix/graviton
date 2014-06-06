<?php

namespace Graviton\SchemaBundle\Listener;

use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Graviton\RestBundle\HttpFoundation\LinkHeader;
use Graviton\RestBundle\HttpFoundation\LinkHeaderItem;

/**
 * FilterResponseListener for adding a rel=self Link header to a response.
 *
 * @category GravitonRestBundle
 * @package  Graviton
 * @author   Lucas Bickel <lucas.bickel@swisscom.com>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://swisscom.com
 */
class CanonicalSchemaLinkResponseListener implements ContainerAwareInterface
{
    /**
     * @private reference to service_container
     */
    private $container;

    /**
     * {@inheritDoc}
     *
     * @param ContainerInterface $container service_container
     *
     * @return void
     */
    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * add a rel=self Link header to the response
     *
     * @param FilterResponseEvent $event response listener event
     *
     * @return void
     */
    public function onKernelResponse(FilterResponseEvent $event)
    {
        $request = $event->getRequest();
        if ($request->attributes->get('schemaRequest', false)) {

            $response = $event->getResponse();
            $router = $this->container->get('router');
            $header = $response->headers->get('Link');
            if (is_array($header)) {
                implode(',', $header);
            }
            $linkHeader = LinkHeader::fromString($header);
    
            // extract various info from route
            $routeName = $request->get('_route');
            $routeParts = explode('.', $routeName);

            $routeType = array_pop($routeParts);
            $realRouteType = 'canonicalSchema';
            if ($routeType == 'idOptions') {
                $realRouteType = 'canonicalIdSchema';
            }

            $routeName = implode('.', array_merge($routeParts, array($realRouteType)));
    
            $url = $router->generate($routeName, array(), true);
    
            // append rel=self link to link headers
            $linkHeader->add(new LinkHeaderItem($url, array('rel' => 'canonical')));
    
            // overwrite link headers with new headers
            $response->headers->set('Link', (string) $linkHeader);
    
            $event->setResponse($response);
        }
    }
}
