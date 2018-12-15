<?php

declare(strict_types=1);

namespace Drupal\Tests\events_logging\Unit;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\Entity;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\events_logging\Logger;
use Drupal\events_logging\StorageBackendInterface;
use Drupal\events_logging\StorageBackendPluginManagerInterface;
use Drupal\Tests\Core\Entity\SimpleTestEntity;
use Drupal\Tests\UnitTestCase;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Throwable;

final class LoggerTest extends UnitTestCase
{
  /**
   * @var ObjectProphecy<StorageBackendInterface>
   */
  private $storageBackend;

  /**
   * @var ObjectProphecy<StorageBackendPluginManagerInterface>
   */
  private $storageBackendPluginManager;

  /**
   * @var ObjectProphecy<LoggerInterface>
   */
  private $drupalLogger;

  /**
   * @var ObjectProphecy<ConfigFactoryInterface>
   */
  private $configFactory;

  /**
   * @var ObjectProphecy<EntityTypeManager>
   */
  private $entityTypeManager;

  /**
   * @var ObjectProphecy<RequestStack>
   */
  private $requestStack;

  protected function setUp(): void {
    parent::setUp();

    $this->storageBackend = $this->prophesize(StorageBackendInterface::class);

    $this->storageBackendPluginManager = $this->prophesize(StorageBackendPluginManagerInterface::class);

    $this->drupalLogger = $this->prophesize(LoggerInterface::class);
    $this->configFactory = $this->prophesize(ConfigFactoryInterface::class);
    $this->entityTypeManager = $this->prophesize(EntityTypeManager::class);
    $this->requestStack = $this->prophesize(RequestStack::class);
  }

  public function testItFallbacksOnMockStorageAndLogsAWarningWhenPluginIsMissing(): void
  {
    $exception = new PluginException('something is wrong');

    $this->storageBackendPluginManager
      ->createInstance('database')
      ->willThrow($exception)
      ->shouldBeCalledOnce();

    $this->drupalLogger->warning($exception)->shouldBeCalledOnce();

    $this->createLogger();
  }

  public function testItSavesDataOnTheConfiguredBacked(): void {
    $data = ['foo' => 'bar'];

    $this->storageBackendPluginManager
      ->createInstance('database')
      ->willReturn($this->storageBackend->reveal())
      ->shouldBeCalledOnce();

    $this->storageBackend->save($data)->shouldBeCalledOnce();

    $this->createLogger()->log($data);
  }

  public function testItTellsIfTheGivenEntityHasEventsLoggingEnabled(): void
  {
    $config = $this->prophesize(ImmutableConfig::class);
    $config->get('enabled_content_entities')->willReturn(['foo', 'bar'])->shouldBeCalledOnce();
    $config->get('enabled_config_entities')->willReturn(['baz', 'quux'])->shouldBeCalledOnce();

    $this->configFactory->get('events_logging.config')->willReturn($config->reveal());

    $entity = $this->mockEntity('foo');

    $this->assertTrue($this->createLogger()->isLoggingEnabledForEntity($entity));
  }

  private function createLogger(): Logger
  {
    return new Logger(
      $this->storageBackendPluginManager->reveal(),
      $this->drupalLogger->reveal(),
      $this->configFactory->reveal(),
      $this->entityTypeManager->reveal(),
      $this->requestStack->reveal()
    );
  }

  private function mockEntity(string $id): EntityInterface
  {
    $entityType = $this->prophesize(EntityTypeInterface::class);
    $entityType->id()->willReturn($id)->shouldBeCalledOnce();

    $entity = $this->prophesize(EntityInterface::class);
    $entity->getEntityType()->willReturn($entityType->reveal());

    return $entity->reveal();
  }
}
