<?php

namespace Ok99\PrivateZoneCore\PageBundle\Controller;

use Sonata\PageBundle\Controller\BlockAdminController as Controller;
use Sonata\PageBundle\Exception\PageNotFoundException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * SharedBlock Admin Controller
 */
class SharedBlockAdminController extends Controller
{
    /**
     * @param Request|null $request
     * @return Response
     */
    public function createAction(Request $request = null)
    {
        if (!$this->admin->isGranted('CREATE')) {
            throw new AccessDeniedException();
        }

        $sharedBlockAdminClass = $this->container->getParameter('sonata.page.admin.shared_block.class');
        if (!$this->admin->getParent() && get_class($this->admin) !== $sharedBlockAdminClass) {

            throw new PageNotFoundException('You cannot create a block without a page');
        }

        $sitesPool = $this->get('ok99.privatezone.site.pool');
        $sites = $sitesPool->getSites();
        $currentSite = $sitesPool->getCurrentSite($request, $sites);

        $parameters = $this->admin->getPersistentParameters();

        if (!$parameters['type']) {
            return $this->render('SonataPageBundle:BlockAdmin:select_type.html.twig', array(
                'services'      => $this->get('sonata.block.manager')->getServicesByContext('sonata_page_bundle'),
                'base_template' => $this->getBaseTemplate(),
                'admin'         => $this->admin,
                'sites'         => $sites,
                'currentSite'   => $currentSite,
                'action'        => 'create'
            ));
        }

        return parent::createAction();
    }

    /**
     * List action.
     *
     * @param Request|null $request
     * @return null|Response
     * @throws \Twig_Error_Runtime
     */
    public function listAction(Request $request = null)
    {
        if (false === $this->admin->isGranted('LIST')) {
            throw new AccessDeniedException();
        }

        $preResponse = $this->preList($request);
        if ($preResponse !== null) {
            return $preResponse;
        }

        if ($listMode = $request->get('_list_mode')) {
            $this->admin->setListMode($listMode);
        }

        $sitesPool = $this->get('ok99.privatezone.site.pool');
        $sites = $sitesPool->getSites();
        $currentSite = $sitesPool->getCurrentSite($request, $sites);

        $datagrid = $this->admin->getDatagrid();
        $datagrid->setValue('site', null, $currentSite->getId());
        $formView = $datagrid->getForm()->createView();

        // set the theme for the current Admin Form
        $this->get('twig')->getExtension('form')->renderer->setTheme($formView, $this->admin->getFilterTheme());

        return $this->render($this->admin->getTemplate('list'), array(
            'action'      => 'list',
            'form'        => $formView,
            'datagrid'    => $datagrid,
            'sites'       => $sites,
            'currentSite' => $currentSite,
            'csrf_token'  => $this->getCsrfToken('sonata.batch'),
        ), null, $request);
    }

    /**
     * Contextualize the admin class depends on the current request.
     *
     * @throws \RuntimeException
     */
    protected function configure()
    {
        parent::configure();

        $this->admin->setSitePool($this->get('ok99.privatezone.site.pool'));
    }
}
