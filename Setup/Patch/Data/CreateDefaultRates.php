<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchVersionInterface;
use Meetanshi\RewardPoints\Model\EarningRateFactory;
use Meetanshi\RewardPoints\Model\ResourceModel\EarningRate as EarningRateResource;
use Meetanshi\RewardPoints\Model\ResourceModel\SpendingRate as SpendingRateResource;
use Meetanshi\RewardPoints\Model\SpendingRateFactory;

/**
 * Creates default earning and spending rates
 */
class CreateDefaultRates implements DataPatchInterface, PatchVersionInterface
{
    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param EarningRateFactory $earningRateFactory
     * @param EarningRateResource $earningRateResource
     * @param SpendingRateFactory $spendingRateFactory
     * @param SpendingRateResource $spendingRateResource
     */
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly EarningRateFactory $earningRateFactory,
        private readonly EarningRateResource $earningRateResource,
        private readonly SpendingRateFactory $spendingRateFactory,
        private readonly SpendingRateResource $spendingRateResource,
    ) {
    }

    /**
     * Apply patch: create default earning and spending rates
     *
     * @return void
     * @throws \Exception
     */
    public function apply(): void
    {
        $this->moduleDataSetup->startSetup();

        $earningRate = $this->earningRateFactory->create();
        $earningRate->setMoneyStep(1.0);
        $earningRate->setPoints(1);
        $earningRate->setMinOrderTotal(null);
        $earningRate->setPriority(0);
        $earningRate->setIsActive(true);

        $this->earningRateResource->save($earningRate);

        $spendingRate = $this->spendingRateFactory->create();
        $spendingRate->setPoints(1);
        $spendingRate->setCurrencyAmount(0.01);
        $spendingRate->setMinPointsPerOrder(0);
        $spendingRate->setPriority(0);
        $spendingRate->setIsActive(true);

        $this->spendingRateResource->save($spendingRate);

        $this->moduleDataSetup->endSetup();
    }

    /**
     * Get array of patches that have to be executed prior to this.
     *
     * @return string[]
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * Get version of patch schema
     *
     * @return string
     */
    public static function getVersion(): string
    {
        return '1.0.0';
    }

    /**
     * Get aliases of patch
     *
     * @return string[]
     */
    public function getAliases(): array
    {
        return [];
    }
}
