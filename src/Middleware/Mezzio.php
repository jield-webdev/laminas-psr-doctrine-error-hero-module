<?php

declare(strict_types=1);

namespace ErrorHeroModule\Middleware;

use Closure;
use Error;
use function ErrorHeroModule\detectMessageContentType;
use ErrorHeroModule\Handler\Logging;
use ErrorHeroModule\HeroTrait;
use function ErrorHeroModule\isExcludedException;
use Exception;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Psr7Bridge\Psr7ServerRequest;
use Laminas\View\Model\ViewModel;
use Mezzio\LaminasView\LaminasViewRenderer;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;

use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

class Mezzio implements MiddlewareInterface
{
    use HeroTrait;

    private ?ServerRequestInterface $request = null;

    /**
     * @param mixed[] $errorHeroModuleConfig
     */
    public function __construct(
        private array $errorHeroModuleConfig,
        private Logging $logging,
        private ?TemplateRendererInterface $templateRenderer
    ) {
        $this->errorHeroModuleConfig = $errorHeroModuleConfig;
    }

    public function process(
        ServerRequestInterface $serverRequest,
        RequestHandlerInterface $requestHandler
    ): ResponseInterface {
        if (! $this->errorHeroModuleConfig['enable']) {
            return $requestHandler->handle($serverRequest);
        }

        try {
            $this->request = $serverRequest;
            $this->phpError();
            return $requestHandler->handle($serverRequest);
        } catch (Throwable $throwable) {
        }

        return $this->exceptionError($throwable);
    }

    /**
     * @throws Error      When 'display_errors' config is 1 and Error has thrown.
     * @throws Exception  When 'display_errors' config is 1 and Exception has thrown.
     */
    public function exceptionError(Throwable $throwable): Response
    {
        if (
            isset($this->errorHeroModuleConfig['display-settings']['exclude-exceptions'])
            && isExcludedException($this->errorHeroModuleConfig['display-settings']['exclude-exceptions'], $throwable)
        ) {
            throw $throwable;
        }

        /** @var  ServerRequestInterface $request */
        $request = $this->request;
        $this->logging->handleErrorException(
            $throwable,
            Psr7ServerRequest::toLaminas($request)
        );

        if ($this->errorHeroModuleConfig['display-settings']['display_errors']) {
            throw $throwable;
        }

        // show default view if display_errors setting = 0.
        return $this->showDefaultView();
    }

    private function showDefaultView(): Response|HtmlResponse
    {
        if ($this->templateRenderer === null) {
            return $this->responseByConfigMessage('no_template');
        }

        /** @var  ServerRequestInterface $request */
        $request          = $this->request;
        $isXmlHttpRequest = $request->hasHeader('X-Requested-With')
            && $request->getHeaderLine('X-Requested-With') === 'XmlHttpRequest';

        if (
            $isXmlHttpRequest &&
            isset($this->errorHeroModuleConfig['display-settings']['ajax']['message'])
        ) {
            return $this->responseByConfigMessage('ajax');
        }

        if ($this->templateRenderer instanceof LaminasViewRenderer) {
            $viewModel = new ViewModel();
            $viewModel->setTemplate($this->errorHeroModuleConfig['display-settings']['template']['layout']);

            $rendererLayout = &Closure::bind(
                static fn&($renderer) => $renderer->layout,
                null,
                $this->templateRenderer
            )($this->templateRenderer);
            $rendererLayout = $viewModel;
        }

        return new HtmlResponse(
            $this->templateRenderer->render($this->errorHeroModuleConfig['display-settings']['template']['view']),
            500
        );
    }

    private function responseByConfigMessage(string $key): Response
    {
        $message     = $this->errorHeroModuleConfig['display-settings'][$key]['message'];
        $contentType = detectMessageContentType($message);

        $response = new Response();
        $response->getBody()->write($message);
        $response = $response->withHeader('Content-type', $contentType);

        return $response->withStatus(500);
    }
}
