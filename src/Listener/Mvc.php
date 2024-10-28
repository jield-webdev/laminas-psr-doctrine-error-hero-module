<?php

declare(strict_types=1);

namespace ErrorHeroModule\Listener;

use ErrorHeroModule\Handler\Logging;
use ErrorHeroModule\HeroTrait;
use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Http\PhpEnvironment\Request;
use Laminas\Http\PhpEnvironment\Response;
use Laminas\Mvc\MvcEvent;
use Laminas\Mvc\SendResponseListener;
use Laminas\Stdlib\RequestInterface;
use Laminas\View\Renderer\PhpRenderer;
use Override;
use Throwable;
use Webmozart\Assert\Assert;
use function ErrorHeroModule\detectMessageContentType;
use function ErrorHeroModule\isExcludedException;

final class Mvc extends AbstractListenerAggregate
{
    use HeroTrait;

    private ?MvcEvent $mvcEvent = null;

    /** @var string */
    private const string DISPLAY_SETTINGS = 'display-settings';

    /** @var string */
    private const string MESSAGE = 'message';

    public function __construct(
        private readonly array       $errorHeroModuleConfig,
        private readonly Logging     $logging,
        private readonly PhpRenderer $phpRenderer
    )
    {
    }

    /**
     * @param int $priority
     */
    #[Override]
    public function attach(EventManagerInterface $events, $priority = 1): void
    {
        if (!$this->errorHeroModuleConfig['enable']) {
            return;
        }

        // exceptions
        $this->listeners[] = $events->attach(eventName: MvcEvent::EVENT_RENDER_ERROR, listener: $this->exceptionError(...));
        $this->listeners[] = $events->attach(eventName: MvcEvent::EVENT_DISPATCH_ERROR, listener: $this->exceptionError(...), priority: 100);

        // php errors
        $this->listeners[] = $events->attach(eventName: MvcEvent::EVENT_BOOTSTRAP, listener: $this->phpError(...));
    }

    public function exceptionError(MvcEvent $mvcEvent): void
    {
        $exception = $mvcEvent->getParam(name: 'exception');
        if (!$exception instanceof Throwable) {
            return;
        }

        if (
            isset($this->errorHeroModuleConfig[self::DISPLAY_SETTINGS]['exclude-exceptions'])
            && isExcludedException(
                excludeExceptionsConfig: $this->errorHeroModuleConfig[self::DISPLAY_SETTINGS]['exclude-exceptions'],
                throwable: $exception
            )
        ) {
            // rely on original mvc process
            return;
        }

        $this->logging->handleErrorException(
            throwable: $exception,
            request: $request = $mvcEvent->getRequest()
        );

        if ($this->errorHeroModuleConfig[self::DISPLAY_SETTINGS]['display_errors']) {
            // rely on original mvc process
            return;
        }

        // show default view if display_errors setting = 0.
        $this->showDefaultView(mvcEvent: $mvcEvent, request: $request);
    }

    private function showDefaultView(MvcEvent $mvcEvent, RequestInterface $request): void
    {
        Assert::isInstanceOf(value: $request, class: Request::class);

        $response = $mvcEvent->getResponse();
        Assert::isInstanceOf(value: $response, class: Response::class);
        $response->setStatusCode(code: 500);

        $application    = $mvcEvent->getApplication();
        $eventManager   = $application->getEventManager();
        $serviceLocator = $application->getServiceManager();

        /** @var SendResponseListener $sendResponseListener */
        $sendResponseListener = $serviceLocator->get('SendResponseListener');
        $sendResponseListener->detach(events: $eventManager);

        $isXmlHttpRequest = $request->isXmlHttpRequest();
        if (
            $isXmlHttpRequest &&
            isset($this->errorHeroModuleConfig[self::DISPLAY_SETTINGS]['ajax'][self::MESSAGE])
        ) {
            $message     = $this->errorHeroModuleConfig[self::DISPLAY_SETTINGS]['ajax'][self::MESSAGE];
            $contentType = detectMessageContentType(message: $message);

            $response->getHeaders()->addHeaderLine('Content-type', $contentType);
            $response->setContent($message);
            $response->send();

            return;
        }

        $model = $mvcEvent->getViewModel();
        $model->setTemplate(template: $this->errorHeroModuleConfig[self::DISPLAY_SETTINGS]['template']['layout']);
        $model->setVariable(
            name: $model->captureTo(),
            value: $this->phpRenderer->render(nameOrModel: $this->errorHeroModuleConfig[self::DISPLAY_SETTINGS]['template']['view'])
        );

        $response->setContent($this->phpRenderer->render(nameOrModel: $model));
        $response->send();
    }
}
