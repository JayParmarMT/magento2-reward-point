<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Test\Unit\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Meetanshi\RewardPoints\Api\Data\TierInterface;
use Meetanshi\RewardPoints\Api\TierRepositoryInterface;
use Meetanshi\RewardPoints\Helper\Config;
use Meetanshi\RewardPoints\Model\ResourceModel\Account as AccountResource;
use Meetanshi\RewardPoints\Model\ResourceModel\Tier\CollectionFactory as TierCollectionFactory;
use Meetanshi\RewardPoints\Model\TierCalculator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for TierCalculator
 */
#[AllowMockObjectsWithoutExpectations]
class TierCalculatorTest extends TestCase
{
    /** @var ScopeConfigInterface&MockObject */
    private ScopeConfigInterface $scopeConfig;

    /** @var TierCollectionFactory&MockObject */
    private TierCollectionFactory $tierCollectionFactory;

    /** @var AccountResource&MockObject */
    private AccountResource $accountResource;

    /** @var TierRepositoryInterface&MockObject */
    private TierRepositoryInterface $tierRepository;

    /** @var ResourceConnection&MockObject */
    private ResourceConnection $resourceConnection;

    /** @var Config&MockObject */
    private Config $config;

    /** @var LoggerInterface&MockObject */
    private LoggerInterface $logger;

    private TierCalculator $tierCalculator;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->tierCollectionFactory = $this->createMock(TierCollectionFactory::class);
        $this->accountResource = $this->createMock(AccountResource::class);
        $this->tierRepository = $this->createMock(TierRepositoryInterface::class);
        $this->resourceConnection = $this->createMock(ResourceConnection::class);
        $this->config = $this->createMock(Config::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->tierCalculator = new TierCalculator(
            $this->scopeConfig,
            $this->tierCollectionFactory,
            $this->accountResource,
            $this->tierRepository,
            $this->resourceConnection,
            $this->config,
            $this->logger,
        );
    }

    // -------------------------------------------------------------------------
    // getEligibleTier — tier disabled
    // -------------------------------------------------------------------------

    #[Test]
    public function getEligibleTierReturnsNullWhenTierProgrammeIsDisabled(): void
    {
        $this->config->method('isTierEnabled')->willReturn(false);

        $result = $this->tierCalculator->getEligibleTier(1, 1, 0);

        $this->assertNull($result);
    }

    // -------------------------------------------------------------------------
    // getEligibleTier — no tiers at all
    // -------------------------------------------------------------------------

    #[Test]
    public function getEligibleTierReturnsNullWhenNoTiersExist(): void
    {
        $this->config->method('isTierEnabled')->willReturn(true);
        $this->configureEarnedPointsBasis(websiteId: 1, periodDays: 0);
        $this->configureEarnedPointsQuery(customerId: 1, websiteId: 1, earnedPoints: 500);

        $collection = $this->buildTierCollection([]);
        $this->tierCollectionFactory->method('create')->willReturn($collection);

        $result = $this->tierCalculator->getEligibleTier(1, 1, 0);

        $this->assertNull($result);
    }

    // -------------------------------------------------------------------------
    // getEligibleTier — customer below minimum points
    // -------------------------------------------------------------------------

    #[Test]
    public function getEligibleTierReturnsNullWhenCustomerBelowAllTierMinimums(): void
    {
        $this->config->method('isTierEnabled')->willReturn(true);
        $this->configureEarnedPointsBasis(websiteId: 1, periodDays: 0);
        // Customer has only 50 pts.
        $this->configureEarnedPointsQuery(customerId: 1, websiteId: 1, earnedPoints: 50);

        $tier = $this->buildTier(tierId: 1, minPoints: 100, earningBonusPercent: 10.0);
        $collection = $this->buildTierCollection([$tier]);
        $this->tierCollectionFactory->method('create')->willReturn($collection);

        $result = $this->tierCalculator->getEligibleTier(1, 1, 0);

        $this->assertNull($result);
    }

    // -------------------------------------------------------------------------
    // getEligibleTier — customer meets exactly one tier
    // -------------------------------------------------------------------------

    #[Test]
    public function getEligibleTierReturnsTierWhenCustomerMeetsExactlyOneThreshold(): void
    {
        $this->config->method('isTierEnabled')->willReturn(true);
        $this->configureEarnedPointsBasis(websiteId: 1, periodDays: 0);
        // Customer has 100 pts — exactly meets the 100-pt threshold.
        $this->configureEarnedPointsQuery(customerId: 1, websiteId: 1, earnedPoints: 100);

        $tier = $this->buildTier(tierId: 1, minPoints: 100, earningBonusPercent: 10.0);
        $collection = $this->buildTierCollection([$tier]);
        $this->tierCollectionFactory->method('create')->willReturn($collection);

        // Scope check: no junction rows means tier applies to all.
        $this->configureScopeCheckWithNoRows(tierId: 1);

        $result = $this->tierCalculator->getEligibleTier(1, 1, 0);

        $this->assertSame($tier, $result);
    }

    // -------------------------------------------------------------------------
    // getEligibleTier — customer qualifies for multiple tiers, highest returned
    // -------------------------------------------------------------------------

    #[Test]
    public function getEligibleTierReturnsHighestEligibleTierWhenMultipleQualify(): void
    {
        $this->config->method('isTierEnabled')->willReturn(true);
        $this->configureEarnedPointsBasis(websiteId: 1, periodDays: 0);
        // Customer has 500 pts — qualifies for both 100-pt and 400-pt tiers.
        $this->configureEarnedPointsQuery(customerId: 1, websiteId: 1, earnedPoints: 500);

        $tierGold   = $this->buildTier(tierId: 2, minPoints: 400, earningBonusPercent: 20.0);
        $tierSilver = $this->buildTier(tierId: 1, minPoints: 100, earningBonusPercent: 10.0);

        // Collection is sorted DESC by min_points so gold comes first.
        $collection = $this->buildTierCollection([$tierGold, $tierSilver]);
        $this->tierCollectionFactory->method('create')->willReturn($collection);

        // Both tiers have no scope restrictions.
        $this->configureScopeCheckWithNoRows(tierId: 2);
        $this->configureScopeCheckWithNoRows(tierId: 1);

        $result = $this->tierCalculator->getEligibleTier(1, 1, 0);

        // The first matching tier (highest min_points = Gold) must be returned.
        $this->assertSame($tierGold, $result);
    }

    // -------------------------------------------------------------------------
    // applyTierBenefits — tier disabled
    // -------------------------------------------------------------------------

    #[Test]
    public function applyTierBenefitsReturnsPointsUnchangedWhenTierProgrammeIsDisabled(): void
    {
        $this->config->method('isTierEnabled')->willReturn(false);

        $result = $this->tierCalculator->applyTierBenefits(200, 1, 1, 0);

        $this->assertSame(200, $result);
    }

    // -------------------------------------------------------------------------
    // applyTierBenefits — no eligible tier
    // -------------------------------------------------------------------------

    #[Test]
    public function applyTierBenefitsReturnsPointsUnchangedWhenNoEligibleTier(): void
    {
        $this->config->method('isTierEnabled')->willReturn(true);
        $this->configureEarnedPointsBasis(websiteId: 1, periodDays: 0);
        $this->configureEarnedPointsQuery(customerId: 1, websiteId: 1, earnedPoints: 50);

        $tier = $this->buildTier(tierId: 1, minPoints: 100, earningBonusPercent: 10.0);
        $collection = $this->buildTierCollection([$tier]);
        $this->tierCollectionFactory->method('create')->willReturn($collection);

        $result = $this->tierCalculator->applyTierBenefits(200, 1, 1, 0);

        $this->assertSame(200, $result);
    }

    // -------------------------------------------------------------------------
    // applyTierBenefits — 0% bonus leaves points unchanged
    // -------------------------------------------------------------------------

    #[Test]
    public function applyTierBenefitsReturnsPointsUnchangedWhenBonusPercentIsZero(): void
    {
        $this->config->method('isTierEnabled')->willReturn(true);
        $this->configureEarnedPointsBasis(websiteId: 1, periodDays: 0);
        $this->configureEarnedPointsQuery(customerId: 1, websiteId: 1, earnedPoints: 200);

        $tier = $this->buildTier(tierId: 1, minPoints: 100, earningBonusPercent: 0.0);
        $collection = $this->buildTierCollection([$tier]);
        $this->tierCollectionFactory->method('create')->willReturn($collection);

        $this->configureScopeCheckWithNoRows(tierId: 1);

        $result = $this->tierCalculator->applyTierBenefits(200, 1, 1, 0);

        $this->assertSame(200, $result);
    }

    // -------------------------------------------------------------------------
    // applyTierBenefits — 10% bonus adds correct amount
    // -------------------------------------------------------------------------

    #[Test]
    public function applyTierBenefitsAddsCorrectBonusForTenPercent(): void
    {
        $this->config->method('isTierEnabled')->willReturn(true);
        $this->configureEarnedPointsBasis(websiteId: 1, periodDays: 0);
        $this->configureEarnedPointsQuery(customerId: 1, websiteId: 1, earnedPoints: 200);

        $tier = $this->buildTier(tierId: 1, minPoints: 100, earningBonusPercent: 10.0);
        $collection = $this->buildTierCollection([$tier]);
        $this->tierCollectionFactory->method('create')->willReturn($collection);

        $this->configureScopeCheckWithNoRows(tierId: 1);

        // 200 * (1 + 10/100) = 200 * 1.1 = 220 → floor(220) = 220
        $result = $this->tierCalculator->applyTierBenefits(200, 1, 1, 0);

        $this->assertSame(220, $result);
    }

    // -------------------------------------------------------------------------
    // applyTierBenefits — rounding of fractional bonus
    // -------------------------------------------------------------------------

    #[Test]
    public function applyTierBenefitsFloorsTheFinalBonusPoints(): void
    {
        $this->config->method('isTierEnabled')->willReturn(true);
        $this->configureEarnedPointsBasis(websiteId: 1, periodDays: 0);
        $this->configureEarnedPointsQuery(customerId: 1, websiteId: 1, earnedPoints: 200);

        // 15% bonus on 101 pts → 101 * 1.15 = 116.15 → floor = 116
        $tier = $this->buildTier(tierId: 1, minPoints: 100, earningBonusPercent: 15.0);
        $collection = $this->buildTierCollection([$tier]);
        $this->tierCollectionFactory->method('create')->willReturn($collection);

        $this->configureScopeCheckWithNoRows(tierId: 1);

        $result = $this->tierCalculator->applyTierBenefits(101, 1, 1, 0);

        $this->assertSame(116, $result);
    }

    // -------------------------------------------------------------------------
    // Helper builders
    // -------------------------------------------------------------------------

    /**
     * Build a mock collection that returns the given tiers from getItems().
     *
     * @param TierInterface[] $tiers
     * @return MockObject
     */
    private function buildTierCollection(array $tiers): MockObject
    {
        $collection = $this->getMockBuilder(\Meetanshi\RewardPoints\Model\ResourceModel\Tier\Collection::class)
            ->disableOriginalConstructor()
            ->getMock();

        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('setOrder')->willReturnSelf();
        $collection->method('getItems')->willReturn($tiers);

        return $collection;
    }

    /**
     * @param int $tierId
     * @param int $minPoints
     * @param float $earningBonusPercent
     * @return TierInterface&MockObject
     */
    private function buildTier(int $tierId, int $minPoints, float $earningBonusPercent): TierInterface
    {
        $tier = $this->createMock(TierInterface::class);
        $tier->method('getTierId')->willReturn($tierId);
        $tier->method('getMinPoints')->willReturn($minPoints);
        $tier->method('getEarningBonusPercent')->willReturn($earningBonusPercent);

        return $tier;
    }

    /**
     * Configure scopeConfig to return earned_points basis and the given period.
     *
     * @param int $websiteId
     * @param int $periodDays
     * @return void
     */
    private function configureEarnedPointsBasis(int $websiteId, int $periodDays): void
    {
        $this->scopeConfig
            ->method('getValue')
            ->willReturnMap([
                [
                    'meetanshi_rewardpoints/tier/basis',
                    \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITE,
                    $websiteId,
                    'earned_points',
                ],
                [
                    'meetanshi_rewardpoints/tier/period_days',
                    \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITE,
                    $websiteId,
                    $periodDays,
                ],
            ]);
    }

    /**
     * Configure the account resource DB call for getEarnedPointsInPeriod.
     *
     * When period_days = 0 the WHERE clause for date is omitted, so we just
     * need fetchOne() to return the expected total.
     *
     * @param int $customerId
     * @param int $websiteId
     * @param int $earnedPoints
     * @return void
     */
    private function configureEarnedPointsQuery(int $customerId, int $websiteId, int $earnedPoints): void
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('join')->willReturnSelf();
        $select->method('where')->willReturnSelf();

        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchOne')->willReturn((string) $earnedPoints);
        $connection->method('getTableName')->willReturnArgument(0);

        $this->accountResource->method('getConnection')->willReturn($connection);
        $this->accountResource->method('getMainTable')->willReturn('meetanshi_rewardpoints_account');
    }

    /**
     * Configure ResourceConnection to return empty rows for scope junction tables,
     * meaning the tier applies to all websites and all customer groups.
     *
     * @param int $tierId
     * @return void
     */
    private function configureScopeCheckWithNoRows(int $tierId): void
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();

        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        // Empty array = no scope restrictions → tier applies to all.
        $connection->method('fetchCol')->willReturn([]);

        $this->resourceConnection->method('getConnection')->willReturn($connection);
        $this->resourceConnection
            ->method('getTableName')
            ->willReturnArgument(0);
    }
}
