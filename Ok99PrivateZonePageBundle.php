<?php

namespace Ok99\PrivateZoneCore\PageBundle;

use Ok99\PrivateZoneCore\PageBundle\DependencyInjection\Compiler\AddSitePoolCompilerPass;
use Ok99\PrivateZoneCore\PageBundle\DependencyInjection\Compiler\CreateSnapshotConsumerCompilerPass;
use Ok99\PrivateZoneCore\PageBundle\DependencyInjection\Compiler\SetSnapshotAdminTemplateCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class Ok99PrivateZonePageBundle extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return 'SonataPageBundle';
    }

    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container)
    {
        $container->addCompilerPass(new AddSitePoolCompilerPass());
        $container->addCompilerPass(new CreateSnapshotConsumerCompilerPass());
        $container->addCompilerPass(new SetSnapshotAdminTemplateCompilerPass());
    }
}