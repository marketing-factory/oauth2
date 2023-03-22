<?php

declare(strict_types=1);

namespace Mfc\OAuth2\LoginProvider;

use Mfc\OAuth2\ResourceServer\Registry;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Controller\LoginController;
use TYPO3\CMS\Backend\LoginProvider\LoginProviderInterface;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

class OAuth2LoginProvider implements LoginProviderInterface
{
    /**
     * @see LoginProviderInterface::render
     */
    public function render(StandaloneView $view, PageRenderer $pageRenderer, LoginController $loginController)
    {
        $view->getRenderingContext()->setControllerAction('OAuth2Login');
        $this->addLayoutRootPaths($view);
        $this->addTemplateRootPaths($view);
        $view->assign('providers', Registry::getAvailableResourceServers());

        if (!empty($this->getRequest()->getQueryParams()['state'] ?? '')) {
            $view->assign('hasOAuthLoginError', true);
        }
    }

    private function addLayoutRootPaths(StandaloneView $view): void
    {
        $layoutPaths = $view->getLayoutRootPaths();
        array_unshift($layoutPaths, 'EXT:oauth2/Resources/Private/Layouts/');
        $view->setLayoutRootPaths($layoutPaths);
    }

    private function addTemplateRootPaths(StandaloneView $view): void
    {
        $templateRootPaths = $view->getTemplateRootPaths();
        array_unshift($templateRootPaths, 'EXT:oauth2/Resources/Private/Templates/');
        $view->setTemplateRootPaths($templateRootPaths);
    }

    protected function getRequest(): ServerRequestInterface
    {
        return $GLOBALS['TYPO3_REQUEST'];
    }
}
