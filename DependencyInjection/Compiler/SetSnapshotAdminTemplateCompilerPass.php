<?php

namespace Ok99\PrivateZoneCore\PageBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Reference;

class SetSnapshotAdminTemplateCompilerPass implements CompilerPassInterface
{
    /**
     * {@inheritDoc}
     */
    public function process(ContainerBuilder $container)
    {
        if ($container->hasDefinition('sonata.page.admin.snapshot')) {
            $definition = $container->getDefinition('sonata.page.admin.snapshot');
            $definition->addMethodCall('setTemplate', array('list','Ok99PrivateZonePageBundle:SnapshotAdmin:list.html.twig'));
        }
    }
}
