<?php

declare(strict_types=1);

namespace ErrorHeroModule\Handler\Writer;

use Doctrine\Common\Collections\Order;
use Doctrine\ORM\EntityManager;
use ErrorHeroModule\Entity\LogEntityInterface;
use ErrorHeroModule\Handler\Logging;
use Laminas\Log\Writer\AbstractWriter;
use Webmozart\Assert\Assert;

final class DoctrineWriter extends AbstractWriter
{
    /** @var string */
    private const NAME = 'doctrine';
    protected EntityManager $entityManager;
    protected array         $config;

    public function __construct(EntityManager $entityManager, array $config = [])
    {
        $this->entityManager = $entityManager;
        $this->config        = $config;

        parent::__construct();
    }

    protected function doWrite(array $event)
    {
        //Now we can create an entity and persist it
        $entityName = $this->config['logging-settings']['doctrine-entity-name'] ?? 'ErrorHeroModule\Entity\Error';

        /** @var LogEntityInterface $log */
        $log = new $entityName();

        Assert::isInstanceOf($log, LogEntityInterface::class);

        $priority = Logging::getPsrPrioryFromSeverity($event['priority'], false);

        $log->setDate($event['timestamp']);
        $log->setPriority($priority);
        $log->setErrorMessage($event['message']);
        $log->setUrl($event['extra']['url'] ?? null);
        $log->setFile($event['extra']['file'] ?? $event['extra']['class']);
        $log->setLine($event['extra']['line'] ?? null);
        $log->setErrorType($event['extra']['error_type'] ?? 'Symfony/Message issue');
        $log->setTrace($event['extra']['trace'] ?? '');
        $log->setRequestData($event['extra']['request_data'] ?? $event['extra']);

        $this->entityManager->persist($log);
        $this->entityManager->flush($log);
    }

    public function isExists(string $errorFile, int $errorLine, string $errorMessage, string $url, string $errorType): bool
    {
        //We need to know if the error has occurred in the given time windows
        $sameErrorLogTimeRange = $this->config['logging-settings']['same-error-log-time-range'] ?? 60 * 60 * 24; // 24 hours;

        //We also need to know the $entity
        $entityName = $this->config['logging-settings']['doctrine-entity-name'] ?? 'ErrorHeroModule\Entity\Error';

        //The entity has to implement the LogEntityInterface
        Assert::isInstanceOf(new $entityName(), LogEntityInterface::class);

        //Find the latest error entity
        /** @var LogEntityInterface $latestErrorEntity */
        $latestErrorEntity = $this->entityManager->getRepository($entityName)->findOneBy([
            'file'         => $errorFile,
            'line'         => $errorLine,
            'errorMessage' => $errorMessage,
            'errorType'    => $errorType,
        ], ['date' => Order::Ascending->value]);

        //The last entity should exist and should be within the time range
        if ($latestErrorEntity && $latestErrorEntity->getDate() > new \DateTime('-' . $sameErrorLogTimeRange . ' seconds')) {
            return true;
        }

        return false;
    }
}
