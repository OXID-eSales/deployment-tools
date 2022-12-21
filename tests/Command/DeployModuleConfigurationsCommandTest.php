<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

declare(strict_types=1);

namespace OxidEsales\DeploymentTools\Tests\Command;

use OxidEsales\EshopCommunity\Internal\Framework\Module\Configuration\Dao\ShopConfigurationDaoInterface;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Configuration\DataObject\ModuleConfiguration;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Configuration\DataObject\ShopConfiguration;
use OxidEsales\EshopCommunity\Tests\Integration\Internal\Framework\Module\Command\ModuleCommandsTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Webmozart\PathUtil\Path;

final class DeployModuleConfigurationsCommandTest extends ModuleCommandsTestCase
{
    private const ACTIVATED_MODULE_STATUS = 'activated';
    private const DEACTIVATED_MODULE_STATUS = 'deactivated';

    private static array $moduleStatus = [];

    private string $commandName = "oe:module:deploy-configurations";

    public function setup(): void
    {
        $this->installTestModule();

        parent::setUp();
    }

    public function tearDown(): void
    {
        self::$moduleStatus = [];
        $this->get('oxid_esales.module.install.service.launched_shop_project_configuration_generator')->generate();
        parent::tearDown();
    }

    public function testReactivationOfActiveModule(): void
    {
        $this->prepareTestModuleConfigurations(true, 1);

        $commandTester = $this->getCommandTester($this->commandName);
        $commandTester->execute([]);

        $this->assertEquals(Command::SUCCESS, $commandTester->getStatusCode());

        $this->assertEquals([self::ACTIVATED_MODULE_STATUS], self::$moduleStatus);
    }

    public function testReDeactivationOfInactiveModule(): void
    {
        $this->prepareTestModuleConfigurations(false, 1);

        $commandTester = $this->getCommandTester($this->commandName);
        $commandTester->execute([]);

        $this->assertEquals(Command::SUCCESS, $commandTester->getStatusCode());

        $this->assertEquals([self::DEACTIVATED_MODULE_STATUS], self::$moduleStatus);
    }

    public function testReactivationForSubShop(): void
    {
        $this->prepareTestModuleConfigurations(true, 1);
        $this->prepareTestModuleConfigurations(false, 2);
        $this->prepareTestModuleConfigurations(true, 3);

        $commandTester = $this->getCommandTester($this->commandName);
        $commandTester->execute([]);

        $this->assertEquals(Command::SUCCESS, $commandTester->getStatusCode());

        $statuses = array_count_values(self::$moduleStatus);

        $this->assertEquals(2, $statuses[self::ACTIVATED_MODULE_STATUS]);
        $this->assertEquals(1, $statuses[self::DEACTIVATED_MODULE_STATUS]);
    }

    public function testCommandReturnsFailureStatusCodeIfActivationFails(): void
    {
        $moduleConfiguration = $this
            ->createModuleConfiguration()
            ->setActivated(true)
            ->addEvent(new ModuleConfiguration\Event('onActivate', self::class . '::nonExistent'));

        $this->saveModuleConfiguration($moduleConfiguration);

        $commandTester = $this->getCommandTester($this->commandName);
        $commandTester->execute([]);

        $this->assertEquals(Command::FAILURE, $commandTester->getStatusCode());
    }

    public function testCommandReturnsFailureStatusCodeIfDeactivationFails(): void
    {
        $moduleConfiguration = $this
            ->createModuleConfiguration()
            ->setActivated(false)
            ->addEvent(new ModuleConfiguration\Event('onDeactivate', self::class . '::nonExistent'));

        $this->saveModuleConfiguration($moduleConfiguration);

        $commandTester = $this->getCommandTester($this->commandName);
        $commandTester->execute([]);

        $this->assertEquals(Command::FAILURE, $commandTester->getStatusCode());
    }

    public static function setActiveModuleStatus(): void
    {
        self::$moduleStatus[] = self::ACTIVATED_MODULE_STATUS;
    }

    public static function setInactiveModuleStatus(): void
    {
        self::$moduleStatus[] = self::DEACTIVATED_MODULE_STATUS;
    }

    private function prepareTestModuleConfigurations(bool $activated, int $shopId): void
    {
        $moduleToActivate = $this
            ->createModuleConfiguration()
            ->setActivated($activated)
            ->addEvent(new ModuleConfiguration\Event('onActivate', self::class . '::setActiveModuleStatus'))
            ->addEvent(new ModuleConfiguration\Event('onDeactivate', self::class . '::setInactiveModuleStatus'));

        $this->saveModuleConfiguration($moduleToActivate, $shopId);
    }

    private function getCommandTester(string $command): CommandTester
    {
        return new CommandTester(
            $this->get('console.command_loader')->get($command)
        );
    }

    private function saveModuleConfiguration(ModuleConfiguration $moduleToActivate, int $shopId = 1): void
    {
        $shopConfiguration = new ShopConfiguration();
        $shopConfiguration->addModuleConfiguration($moduleToActivate);

        $shopConfigurationDao = $this->get(ShopConfigurationDaoInterface::class);
        $shopConfigurationDao->save($shopConfiguration, $shopId);
    }

    private function createModuleConfiguration(): ModuleConfiguration
    {
        $configuration = new ModuleConfiguration();
        $configuration
            ->setId($this->moduleId)
            ->setModuleSource(Path::join($this->modulesPath, $this->moduleId));

        return $configuration;
    }
}
