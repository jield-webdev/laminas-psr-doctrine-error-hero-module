<?php

declare(strict_types=1);

namespace ErrorHeroModule\Handler\Writer;

use DateTime;
use Doctrine\Common\Collections\Order;
use Doctrine\ORM\EntityManager;
use ErrorHeroModule\Entity\LogEntityInterface;
use ErrorHeroModule\Handler\Logging;
use Laminas\Log\Writer\AbstractWriter;
use Override;
use Webmozart\Assert\Assert;

final class DoctrineWriter extends AbstractWriter
{
    /** @var string */
    private const string NAME = 'doctrine';



    public function __construct(protected EntityManager $entityManager, protected array $config = [])
    {
        parent::__construct();
    }

    #[Override]
    protected function doWrite(array $event)
    {
        //Now we can create an entity and persist it
        $entityName = $this->config['logging-settings']['doctrine-entity-name'] ?? 'ErrorHeroModule\Entity\Error';

        /** @var LogEntityInterface $log */
        $log = new $entityName();

        Assert::isInstanceOf(value: $log, class: LogEntityInterface::class);

        $priority = Logging::getPsrPrioryFromSeverity(severity: $event['priority'], fromLegacy: false);

        $log->setDate(date: $event['timestamp']);
        $log->setPriority(priority: $priority);
        $log->setErrorMessage(errorMessage: $event['message']);
        $log->setUrl(url: $event['extra']['url'] ?? null);
        $log->setFile(file: $event['extra']['file'] ?? $event['extra']['class']);
        $log->setLine(line: $event['extra']['line'] ?? null);
        $log->setErrorType(errorType: $event['extra']['error_type'] ?? 'Symfony/Message issue');
        $log->setTrace(trace: $event['extra']['trace'] ?? '');
        $log->setRequestData(requestData: $event['extra']['request_data'] ?? $event['extra']);

        $this->entityManager->persist(entity: $log);
        $this->entityManager->flush(entity: $log);
    }

    public function isExists(string $errorFile, int $errorLine, string $errorMessage, string $url, string $errorType): bool
    {
        //We need to know if the error has occurred in the given time windows
        $sameErrorLogTimeRange = $this->config['logging-settings']['same-error-log-time-range'] ?? 60 * 60 * 24; // 24 hours;

        //We also need to know the $entity
        $entityName = $this->config['logging-settings']['doctrine-entity-name'] ?? 'ErrorHeroModule\Entity\Error';

        //The entity has to implement the LogEntityInterface
        Assert::isInstanceOf(value: new $entityName(), class: LogEntityInterface::class);

        //Find the latest error entity
        /** @var LogEntityInterface $latestErrorEntity */
        $latestErrorEntity = $this->entityManager->getRepository(entityName: $entityName)->findOneBy(criteria: [
            'file'         => $errorFile,
            'line'         => $errorLine,
            'errorMessage' => $errorMessage,
            'errorType'    => $errorType,
        ], orderBy: ['date' => Order::Ascending->value]);
        //The last entity should exist and should be within the time range
        return $latestErrorEntity && $latestErrorEntity->getDate() > new DateTime(datetime: '-' . $sameErrorLogTimeRange . ' seconds');
    }
}
