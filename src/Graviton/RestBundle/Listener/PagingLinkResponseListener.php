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
class PagingLinkResponseListener implements ContainerAwareInterface
{
    /**
     * @var \Symfony\Component\DependencyInjection\ContainerInterface service_container
     */
    private $container;

    /**
     * @var \Graviton\RestBundle\HttpFoundation\LinkHeader
     */
    private $linkHeader;

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

        // extract various info from route
        $routeName = $request->get('_route');
        $routeParts = explode('.', $routeName);
        $routeType = end($routeParts);

        // only collections have paging
        if ($routeType == 'all' && $request->attributes->get('paging')) {

            $this->linkHeader = $this->createLinkHeader(
                $response->headers->get('Link')
            );
            $this->generateLinks(
                $routeName,
                $request->get('page', 1),
                $request->attributes->get('numPages')
            );
            $response->headers->set(
                'Link',
                (string) $this->linkHeader
            );
        }

        $event->setResponse($response);
    }

    /**
     * load link header
     *
     * @param string[]|string $header headers from response
     *
     * @return Graviton\RestBundle\HttpFoundation\LinkHeader
     */
    private function createLinkHeader($header)
    {
        if (is_array($header)) {
            implode(',', $header);
        }

        return LinkHeader::fromString($header);
    }

    /**
     * generate headers for all paging links
     *
     * @param string  $route    name of route
     * @param integer $page     current page
     * @param integer $numPages number of all pages
     *
     * @return void
     */
    private function generateLinks($route, $page, $numPages)
    {
        if ($page > 2) {
            $this->generateLink($route, 1, 'first');
        }
        if ($page > 1) {
            $this->generateLink($route, $page - 1, 'prev');
        }
        if ($page < $numPages) {
            $this->generateLink($route, $page + 1, 'next');
        }
        if ($page != $numPages) {
            $this->generateLink($route, $numPages, 'last');
        }
    }

    /**
     * generate link header pased on params and type
     *
     * @param string  $routeName use with router to generate urls
     * @param integer $page      page to link to
     * @param string  $type      rel type of link to generate
     *
     * @return string
     */
    private function generateLink($routeName, $page, $type)
    {
        $router = $this->container->get('router');
        $url = $router->generate($routeName, array('page' => $page), true);
        $this->linkHeader->add(new LinkHeaderItem($url, array('rel' => $type)));
    }
}