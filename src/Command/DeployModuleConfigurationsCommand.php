<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

declare(strict_types=1);

namespace OxidEsales\DeploymentTools\Command;

use OxidEsales\EshopCommunity\Internal\Framework\Console\Executor;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Configuration\Dao\ShopConfigurationDaoInterface;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Configuration\DataObject\ModuleConfiguration;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Configuration\DataObject\ShopConfiguration;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Setup\Service\ModuleActivationServiceInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DeployModuleConfigurationsCommand extends Command
{
    private int $status = Command::SUCCESS;

    public function __construct(
        private ShopConfigurationDaoInterface $shopConfigurationDao,
        private ModuleActivationServiceInterface $moduleActivationService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('
            This command activates all modules which have "activated=true" state in the module configuration (yaml files)
            and deactivates all modules with "activated=false" state.
        ')
            ->addArgument(
                Executor::SHOP_ID_PARAMETER_OPTION_NAME,
                InputArgument::OPTIONAL,
                'Id of a shop'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $shopId = (int) $input->getArgument(Executor::SHOP_ID_PARAMETER_OPTION_NAME);
        if ($shopId) {
            $this->deployModuleConfigurations($output, $this->shopConfigurationDao->get($shopId), $shopId);
        } else {
            foreach ($this->shopConfigurationDao->getAll() as $shopId => $shopConfiguration) {
                $this->deployModuleConfigurations($output, $shopConfiguration, $shopId);
            }
        }

        return $this->status;
    }

    private function deployModuleConfigurations(
        OutputInterface $output,
        ShopConfiguration $shopConfiguration,
        int $shopId
    ): void {
        $output->writeln('<info>Deploying modules for the shop with id ' . $shopId . ':</info>');

        foreach ($shopConfiguration->getModuleConfigurations() as $moduleConfiguration) {
            if ($moduleConfiguration->isActivated()) {
                $this->activateModule($output, $moduleConfiguration, $shopId);
            } else {
                $this->deactivateModule($output, $moduleConfiguration, $shopId);
            }
        }
    }

    private function activateModule(OutputInterface $output, ModuleConfiguration $moduleConfiguration, int $shopId): void
    {
        try {
            $output->writeln('<info>Activating ' . $moduleConfiguration->getId() . '</info>');
            $this->moduleActivationService->activate($moduleConfiguration->getId(), $shopId);
        } catch (\Throwable $throwable) {
            $this->handleError($output, $throwable);
        }
    }

    private function deactivateModule(OutputInterface $output, ModuleConfiguration $moduleConfiguration, int $shopId): void
    {
        try {
            $output->writeln('<info>Deactivating ' . $moduleConfiguration->getId() . '</info>');
            $this->moduleActivationService->deactivate($moduleConfiguration->getId(), $shopId);
        } catch (\Throwable $throwable) {
            $this->handleError($output, $throwable);
        }
    }

    private function handleError(OutputInterface $output, \Throwable $throwable): void
    {
        $this->status = Command::FAILURE;
        $this->showErrorMessage($output, $throwable);
    }

    private function showErrorMessage(OutputInterface $output, \Throwable $throwable): void
    {
        $output->writeln(
            '<error>'
            . 'An exception occurred: '
            . $throwable::class . ' '
            . $throwable->getMessage()
            . '</error>'
        );
    }
}
