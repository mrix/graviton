<?php

namespace Graviton\RestBundle\Listener;

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
 * @author   Manuel Kipfer <manuel.kipfer@swisscom.com>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://swisscom.com
 */
class SelfLinkResponseListener implements ContainerAwareInterface
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
        $response = $event->getResponse();
        $request = $event->getRequest();
        $router = $this->container->get('router');
        $header = $response->headers->get('Link');
        if (is_array($header)) {
            implode(',', $header);
        }
        $linkHeader = LinkHeader::fromString($header);

        // extract various info from route
        $routeName = $request->get('_route');
        $routeParts = explode('.', $routeName);
        $routeType = end($routeParts);

        // for now we assume that everything except collections has an id
        // this is also flawed since it does not handle search actions
        $parameters = array();
        if ($routeType == 'post') {
            // handle post request by rewriting self link to newly created resource
            $parameters = array('id' => $request->get('id'));
            $routeName = substr($routeName, 0, -4).'get';
        } elseif ($routeType != 'all') {
            $parameters = array('id' => $request->get('id'));
        } elseif ($request->attributes->get('paging')) {
            $parameters = array('page' => $request->get('page', 1));
        }

        $url = $router->generate($routeName, $parameters, true);

        // append rel=self link to link headers
        $linkHeader->add(new LinkHeaderItem($url, array('rel' => 'self')));

        // overwrite link headers with new headers
        $response->headers->set('Link', (string) $linkHeader);

        $event->setResponse($response);
    }
}