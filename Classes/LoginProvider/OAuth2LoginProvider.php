<?php

declare(strict_types=1);

namespace Mfc\OAuth2\LoginProvider;

use Mfc\OAuth2\ResourceServer\Registry;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Backend\LoginProvider\LoginProviderInterface;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\View\ViewInterface;
use TYPO3\CMS\Fluid\View\FluidViewAdapter;

#[Autoconfigure(public: true)]
final readonly class OAuth2LoginProvider implements LoginProviderInterface
{
    public function __construct(
        private PageRenderer $pageRenderer,
    ) {}

    public function modifyView(ServerRequestInterface $request, ViewInterface $view): string
    {
        if ($view instanceof FluidViewAdapter) {
            $templatePaths = $view->getRenderingContext()->getTemplatePaths();
            $templateRootPaths = $templatePaths->getTemplateRootPaths();
            $templateRootPaths[] = 'EXT:oauth2/Resources/Private/Templates';
            $templatePaths->setTemplateRootPaths($templateRootPaths);
            $layoutPaths = $view->getRenderingContext()->getTemplatePaths();
            $layoutRootPaths = $layoutPaths->getLayoutRootPaths();
            $layoutRootPaths[] = 'EXT:oauth2/Resources/Private/Layouts';
            $layoutPaths->setLayoutRootPaths($layoutRootPaths);
            $view->assign('oauthProviders', Registry::getAvailableResourceServers());
        }

        if (!empty($request->getQueryParams()['state'] ?? '')) {
            $view->assign('hasOAuthLoginError', true);
        }

        return 'OAuth2Login';
    }
}
