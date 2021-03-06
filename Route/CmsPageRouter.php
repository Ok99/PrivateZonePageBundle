<?php

namespace Ok99\PrivateZoneCore\PageBundle\Route;

use Ok99\PrivateZoneCore\PageBundle\Entity\Page;
use Ok99\PrivateZoneCore\PageBundle\Entity\PageTranslation;
use Sonata\PageBundle\Model\SnapshotPageProxy;
use Sonata\PageBundle\CmsManager\CmsManagerInterface;
use Sonata\PageBundle\Model\SiteInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

use Sonata\PageBundle\Model\PageInterface;
use Sonata\PageBundle\Exception\PageNotFoundException;
use Sonata\PageBundle\Route\CmsPageRouter as BaseCmsPageRouter;

class CmsPageRouter extends BaseCmsPageRouter
{
    /**
     * {@inheritdoc}
     */
    public function match($pathinfo)
    {
        $cms = $this->cmsSelector->retrieve();
        $site = $this->siteSelector->retrieve();

        if (!$cms instanceof CmsManagerInterface) {
            throw new ResourceNotFoundException('No CmsManager defined');
        }

        if (!$site instanceof SiteInterface) {
            throw new ResourceNotFoundException('No site defined');
        }

        try {
            $page = $cms->getPageByUrl($site, $pathinfo);
        } catch (PageNotFoundException $e) {
            throw new ResourceNotFoundException($pathinfo, 0, $e);
        }

        if (!$page || (!$page->isCms() && !$page->isHybrid())) {
            throw new ResourceNotFoundException($pathinfo);
        }

        if (!$page->getEnabled() && !$this->cmsSelector->isEditor()) {
            throw new ResourceNotFoundException($pathinfo);
        }

        $cms->setCurrentPage($page);

        return array_merge(($page instanceof SnapshotPageProxy ? $page->getPage()->parameters : $page->parameters) ?: array(), array(
            '_controller' => $page->isHybrid() ? $this->getControllerForRouteName($page->getRouteName()) : 'sonata.page.page_service_manager:execute',
            '_route'      => $page->isHybrid() ? $page->getRouteName() : PageInterface::PAGE_ROUTE_CMS_NAME,
            'page'        => $page,
            'path'        => $pathinfo,
        ));
    }

    /**
     * @param $routeName
     * @return mixed
     */
    protected function getControllerForRouteName($routeName)
    {
        foreach ($this->router->getRouteCollection() as $name => $route) {
            if ($name === $routeName) {
                return $route->getDefaults()['_controller'];
            }
        }

        throw new ResourceNotFoundException($routeName);
    }

    /**
     * Returns the Url from a Page object
     *
     * @param Page $page
     * @param string|null $locale
     *
     * @return string
     */
    protected function getUrlFromPage(PageInterface $page, $locale = null)
    {
        if ($locale) {
            /** @var PageTranslation $pageTranslation */
            $pageTranslation = $page->getTranslation($locale);
            return $pageTranslation->getCustomUrl() ?: $pageTranslation->getUrl();
        }

        return $page->getCustomUrl() ?: $page->getUrl();
    }

    /**
     * Generates an URL from a Page object
     *
     * @param PageInterface $page          Page object
     * @param array         $parameters    An array of parameters
     * @param bool|string   $referenceType The type of reference to be generated (one of the constants)
     *
     * @return string
     *
     * @throws \RuntimeException
     */
    protected function generateFromPage(PageInterface $page, array $parameters = array(), $referenceType = self::ABSOLUTE_PATH)
    {
        if (isset($parameters['path'])) {
            unset($parameters['path']);
        }

        // hybrid pages use, by definition, the default routing mechanism
        if ($page->isHybrid()) {
            //return $this->router->generate($page->getRouteName(), $parameters, $referenceType);
        }

        $url = $this->getUrlFromPage($page, isset($parameters['_locale']) && $parameters['_locale'] ? $parameters['_locale'] : null);

        if ($url === false) {
            throw new \RuntimeException(sprintf('Page "%d" has no url or customUrl.', $page->getId()));
        }

        $url = $this->decorateUrl($url, $parameters, $referenceType);

        if ($page->getSite() !== $this->siteSelector->retrieve()) {
            $url = str_replace($this->siteSelector->retrieve()->getRelativePath(), $page->getSite()->getRelativePath(), $url);
        }

        return $url;
    }

    /**
     * Decorates an URL with url context and query
     *
     * @param string      $url           Relative URL
     * @param array       $parameters    An array of parameters
     * @param bool|string $referenceType The type of reference to be generated (one of the constants)
     *
     * @return string
     *
     * @throws \RuntimeException
     */
    protected function decorateUrl($url, array $parameters = array(), $referenceType = self::ABSOLUTE_PATH)
    {
        if (!$this->context) {
            throw new \RuntimeException('No context associated to the CmsPageRouter');
        }

        $schemeAuthority = '';
        if ($this->context->getHost() && (self::ABSOLUTE_URL === $referenceType || self::NETWORK_PATH === $referenceType)) {
            $port = '';
            if ('http' === $this->context->getScheme() && 80 != $this->context->getHttpPort()) {
                $port = sprintf(':%s', $this->context->getHttpPort());
            } elseif ('https' === $this->context->getScheme() && 443 != $this->context->getHttpsPort()) {
                $port = sprintf(':%s', $this->context->getHttpsPort());
            }

            $schemeAuthority = self::NETWORK_PATH === $referenceType ? '//' : sprintf('%s://', $this->context->getScheme());
            $schemeAuthority = sprintf('%s%s%s', $schemeAuthority, $this->context->getHost(), $port);
        }

        if (self::RELATIVE_PATH === $referenceType) {
            $url = $this->getRelativePath($this->context->getPathInfo(), $url);
        } else {
            $baseUrl = $this->context->getBaseUrl();
            if (isset($parameters['_locale'])) {
                $baseUrl = rtrim($baseUrl, sprintf('/%s', $parameters["page"]->getSite()->getLocale()));
                $baseUrl = sprintf('%s/%s', $baseUrl, $parameters['_locale']);

                unset($parameters['_locale']);
            }

            $url = sprintf('%s%s%s', $schemeAuthority, $baseUrl, $url);
        }

        if (isset($parameters['page'])) {
            unset($parameters['page']);
        }

        if (count($parameters) > 0) {
            return sprintf('%s?%s', $url, http_build_query($parameters, '', '&'));
        }

        $url = rtrim($url, '/');

        return $url;
    }

    /**
     * Decorates an URL with url context and query
     *
     * @param string      $url           Relative URL
     * @param array       $parameters    An array of parameters
     * @param bool|string $referenceType The type of reference to be generated (one of the constants)
     *
     * @return string
     *
     * @throws \RuntimeException
     */
    protected function customDecorateUrl($site, $url, array $parameters = array(), $referenceType = self::ABSOLUTE_PATH)
    {
        if (!$this->context) {
            throw new \RuntimeException('No context associated to the CmsPageRouter');
        }

        $schemeAuthority = '';
        if ($this->context->getHost() && (self::ABSOLUTE_URL === $referenceType || self::NETWORK_PATH === $referenceType)) {
            $port = '';
            if ('http' === $this->context->getScheme() && 80 != $this->context->getHttpPort()) {
                $port = sprintf(':%s', $this->context->getHttpPort());
            } elseif ('https' === $this->context->getScheme() && 443 != $this->context->getHttpsPort()) {
                $port = sprintf(':%s', $this->context->getHttpsPort());
            }

            $schemeAuthority = self::NETWORK_PATH === $referenceType ? '//' : sprintf('%s://', $this->context->getScheme());
            $schemeAuthority = sprintf('%s%s%s', $schemeAuthority, $this->context->getHost(), $port);
        }

        if (self::RELATIVE_PATH === $referenceType) {
            $url = $this->getRelativePath($this->context->getPathInfo(), $url);
        } else {
            $url = sprintf('%s%s%s', $schemeAuthority, str_replace($this->siteSelector->retrieve()->getRelativePath(), $site->getRelativePath(), $this->context->getBaseUrl()), $url);
        }

        foreach ($parameters as $k => $v) {
            $pattern = '{'.$k.'}';
            if (strpos($url, $pattern) !== false) {
                $url = str_replace($pattern, $v, $url);
                unset($parameters[$k]);
            }
        }

        if (count($parameters) > 0) {
            return sprintf('%s?%s', $url, http_build_query($parameters, '', '&'));
        }

        return $url;
    }

    /**
     * {@inheritdoc}
     */
    public function generate($name, $parameters = array(), $referenceType = self::ABSOLUTE_PATH)
    {
        try {
            $url = false;

            if (is_int($name)) {
                $name = $this->getPageById($name);
            } elseif ($this->isPageAlias($name)) {
                $name = $this->getPageByPageAlias($name);
            }

            if ($name instanceof PageInterface) {
                $url = $this->generateFromPage($name, $parameters, $referenceType);
            }

            if ($this->isPageSlug($name)) {
                $url = $this->generateFromPageSlug($parameters, $referenceType);
            }

            if ($url === false) {
                throw new RouteNotFoundException('The Sonata CmsPageRouter cannot find url');
            }

        } catch (PageNotFoundException $exception) {
            throw new RouteNotFoundException('The Sonata CmsPageRouter cannot find page');
        }

        return $url;
    }

    /**
     * Retrieves a page object from a page id
     *
     * @param int $id
     *
     * @return \Sonata\PageBundle\Model\PageInterface|null
     *
     * @throws PageNotFoundException
     */
    protected function getPageById($id)
    {
        $page = $this->cmsSelector->retrieve()->getPageById($id);

        return $page;
    }
}
