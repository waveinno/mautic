<?php

declare(strict_types=1);

namespace Mautic\CoreBundle\Doctrine\Provider;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Doctrine\GeneratedColumn\GeneratedColumns;
use Mautic\CoreBundle\Event\GeneratedColumnsEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class GeneratedColumnsProvider implements GeneratedColumnsProviderInterface
{
    /**
     * @var string
     *
     * @see https://dev.mysql.com/doc/refman/5.7/en/innodb-foreign-key-constraints.html#innodb-foreign-key-generated-columns
     */
    public const MYSQL_MINIMUM_VERSION = '5.7.14';

    /**
     * @var string
     *
     * @see https://mariadb.com/kb/en/library/generated-columns
     */
    public const MARIADB_MINIMUM_VERSION = '10.2.6';

    private GeneratedColumns $generatedColumns;

    public function __construct(
        private VersionProviderInterface $versionProvider,
        private EventDispatcherInterface $dispatcher
    ) {
        $this->generatedColumns = new GeneratedColumns();
    }

    public function getGeneratedColumns(): GeneratedColumns
    {
        if ($this->generatedColumnsAreSupported()
            && 0 === $this->generatedColumns->count()
            && $this->dispatcher->hasListeners(CoreEvents::ON_GENERATED_COLUMNS_BUILD)
        ) {
            $event                  = $this->dispatcher->dispatch(new GeneratedColumnsEvent(), CoreEvents::ON_GENERATED_COLUMNS_BUILD);
            $this->generatedColumns = $event->getGeneratedColumns();
        }

        return $this->generatedColumns;
    }

    public function generatedColumnsAreSupported(): bool
    {
        return 1 !== version_compare($this->getMinimalSupportedVersion(), $this->versionProvider->getVersion());
    }

    public function getMinimalSupportedVersion(): string
    {
        if ($this->versionProvider->isMariaDb()) {
            return self::MARIADB_MINIMUM_VERSION;
        }

        return self::MYSQL_MINIMUM_VERSION;
    }
}
