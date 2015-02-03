<?php

namespace Graviton\SecurityBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class AuthenticationPass
 *
 * @category GravitonSecurityBundle
 * @package  Graviton
 * @author   Bastian Feder <bastian.feder@swisscom.com>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://swisscom.com
 */
class AuthenticationPass implements CompilerPassInterface
{
    /**
     * Finds services tagged with "graviton.security.authentication.strategy" or
     * defined in parameters as "graviton-security.authentication.services" and adds them to
     * the "graviton.sercurity.authentication.strategy.collection".
     *
     * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container Parameter vault
     *
     * @api
     * @return void
     */
    public function process(ContainerBuilder $container)
    {
        $strategies = array();

        if ($container->hasParameter('graviton-security.authentication.services')) {
            $strategies = $container->getParameter("graviton-security.authentication.services");
        }

        $strategyDefinition = $container->getDefinition('graviton.sercurity.authentication.strategy.collection');

        foreach ($strategies as $serviceId) {

            $strategyDefinition->addMethodCall('add', array(new Reference($serviceId)));
        }
    }
}