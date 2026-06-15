<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Test\Unit\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Meetanshi\RewardPoints\Model\ReferralCode;
use Meetanshi\RewardPoints\Model\ReferralCodeFactory;
use Meetanshi\RewardPoints\Model\ReferralCodeGenerator;
use Meetanshi\RewardPoints\Model\ResourceModel\ReferralCode as ReferralCodeResource;
use Meetanshi\RewardPoints\Model\ResourceModel\ReferralCode\Collection as ReferralCodeCollection;
use Meetanshi\RewardPoints\Model\ResourceModel\ReferralCode\CollectionFactory as ReferralCodeCollectionFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for ReferralCodeGenerator
 */
#[AllowMockObjectsWithoutExpectations]
class ReferralCodeGeneratorTest extends TestCase
{
    /** @var ReferralCodeResource&MockObject */
    private ReferralCodeResource $referralCodeResource;

    /** @var ReferralCodeCollectionFactory&MockObject */
    private ReferralCodeCollectionFactory $collectionFactory;

    /** @var ReferralCodeFactory&MockObject */
    private ReferralCodeFactory $referralCodeFactory;

    /** @var ScopeConfigInterface&MockObject */
    private ScopeConfigInterface $scopeConfig;

    /** @var LoggerInterface&MockObject */
    private LoggerInterface $logger;

    private ReferralCodeGenerator $generator;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->referralCodeResource = $this->createMock(ReferralCodeResource::class);
        $this->collectionFactory = $this->createMock(ReferralCodeCollectionFactory::class);
        $this->referralCodeFactory = $this->createMock(ReferralCodeFactory::class);
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->generator = new ReferralCodeGenerator(
            $this->referralCodeResource,
            $this->collectionFactory,
            $this->referralCodeFactory,
            $this->scopeConfig,
            $this->logger,
        );
    }

    // -------------------------------------------------------------------------
    // generate() — basic behaviour
    // -------------------------------------------------------------------------

    #[Test]
    public function generateReturnsNonEmptyString(): void
    {
        $this->configurePrefix(websiteId: 1, prefix: '');
        $this->configureConnectionWithCodeNotExists();

        $result = $this->generator->generate(42, 1);

        $this->assertNotEmpty($result);
        $this->assertIsString($result);
    }

    #[Test]
    public function generateReturnsDifferentCodesOnConsecutiveCalls(): void
    {
        $this->configurePrefix(websiteId: 1, prefix: '');
        // Allow both calls to resolve without collision.
        $this->configureConnectionWithCodeNotExists();

        $code1 = $this->generator->generate(42, 1);
        $code2 = $this->generator->generate(42, 1);

        // Two independent calls produce independent random suffixes; collision is
        // astronomically unlikely in test (charset=32 chars, 4 positions = 32^4 = 1M combos).
        $this->assertNotSame($code1, $code2);
    }

    #[Test]
    public function generateRespectsConfiguredPrefix(): void
    {
        $this->configurePrefix(websiteId: 1, prefix: 'ACME');
        $this->configureConnectionWithCodeNotExists();

        $result = $this->generator->generate(1, 1);

        $this->assertStringStartsWith('ACME', $result);
    }

    #[Test]
    public function generateUsesDefaultPrefixWhenConfiguredPrefixIsEmpty(): void
    {
        $this->configurePrefix(websiteId: 1, prefix: '');
        $this->configureConnectionWithCodeNotExists();

        $result = $this->generator->generate(1, 1);

        $this->assertStringStartsWith('REF', $result);
    }

    #[Test]
    public function generateIncludesBase36CustomerIdInCode(): void
    {
        $this->configurePrefix(websiteId: 1, prefix: 'REF');
        $this->configureConnectionWithCodeNotExists();

        // Customer ID 42 in base36 (uppercase) = '16'
        $result = $this->generator->generate(42, 1);

        $base36 = strtoupper(base_convert('42', 10, 36));
        $this->assertStringContainsString($base36, $result);
    }

    #[Test]
    public function generateThrowsLocalizedExceptionAfterMaxRetries(): void
    {
        $this->configurePrefix(websiteId: 1, prefix: '');

        // Always report the code as already existing to exhaust all retries.
        $this->configureConnectionWithCodeAlwaysExists();

        $this->expectException(\Magento\Framework\Exception\LocalizedException::class);

        $this->generator->generate(1, 1);
    }

    // -------------------------------------------------------------------------
    // getOrCreateCode() — existing code returned
    // -------------------------------------------------------------------------

    #[Test]
    public function getOrCreateCodeReturnsExistingCodeWithoutGeneratingNewOne(): void
    {
        $existingCode = 'REF1K2ABC';

        $existingItem = $this->createMock(ReferralCode::class);
        $existingItem->method('getId')->willReturn(99);
        $existingItem->method('getData')->with('code')->willReturn($existingCode);

        $collection = $this->buildCollection($existingItem);
        $this->collectionFactory->method('create')->willReturn($collection);

        // Factory and resource save must NOT be called.
        $this->referralCodeFactory->expects($this->never())->method('create');
        $this->referralCodeResource->expects($this->never())->method('save');

        $result = $this->generator->getOrCreateCode(1, 1);

        $this->assertSame($existingCode, $result);
    }

    // -------------------------------------------------------------------------
    // getOrCreateCode() — no existing code: new one persisted
    // -------------------------------------------------------------------------

    #[Test]
    public function getOrCreateCodeCreatesAndPersistsCodeWhenNoneExists(): void
    {
        // getFirstItem() returns an item with no ID (empty model).
        $emptyItem = $this->createMock(ReferralCode::class);
        $emptyItem->method('getId')->willReturn(null);

        $collection = $this->buildCollection($emptyItem);
        $this->collectionFactory->method('create')->willReturn($collection);

        // Configure a fresh ReferralCode model returned by the factory.
        $newCodeModel = $this->createMock(ReferralCode::class);
        $newCodeModel->method('setCustomerId')->willReturnSelf();
        $newCodeModel->method('setWebsiteId')->willReturnSelf();
        $newCodeModel->method('setCode')->willReturnSelf();
        $this->referralCodeFactory->method('create')->willReturn($newCodeModel);

        // Resource must be called to save the new model.
        $this->referralCodeResource->expects($this->once())->method('save')->with($newCodeModel);

        // Ensure the DB uniqueness check says no collision.
        $this->configurePrefix(websiteId: 1, prefix: '');
        $this->configureConnectionWithCodeNotExists();

        $result = $this->generator->getOrCreateCode(1, 1);

        $this->assertNotEmpty($result);
        $this->assertIsString($result);
    }

    // -------------------------------------------------------------------------
    // Helper builders
    // -------------------------------------------------------------------------

    /**
     * @param string $prefix
     * @param int $websiteId
     * @return void
     */
    private function configurePrefix(int $websiteId, string $prefix): void
    {
        $this->scopeConfig
            ->method('getValue')
            ->with(
                'meetanshi_rewardpoints/referral/code_prefix',
                \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITE,
                $websiteId,
            )
            ->willReturn($prefix);
    }

    /**
     * Configure the connection so that codeExists() always returns false.
     *
     * @return void
     */
    private function configureConnectionWithCodeNotExists(): void
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $select->method('limit')->willReturnSelf();

        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        // fetchOne returns '' / falsy → code does not exist.
        $connection->method('fetchOne')->willReturn('');

        $this->referralCodeResource->method('getConnection')->willReturn($connection);
        $this->referralCodeResource->method('getMainTable')->willReturn('meetanshi_rewardpoints_referral_code');
    }

    /**
     * Configure the connection so that codeExists() always returns true
     * (to exhaust MAX_RETRIES).
     *
     * @return void
     */
    private function configureConnectionWithCodeAlwaysExists(): void
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $select->method('limit')->willReturnSelf();

        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        // fetchOne returns a truthy value → code always exists.
        $connection->method('fetchOne')->willReturn('1');

        $this->referralCodeResource->method('getConnection')->willReturn($connection);
        $this->referralCodeResource->method('getMainTable')->willReturn('meetanshi_rewardpoints_referral_code');

        // Logger will record debug entries for each collision.
        $this->logger->method('debug');
    }

    /**
     * Build a collection mock that returns $firstItem from getFirstItem().
     *
     * @param ReferralCode&MockObject $firstItem
     * @return ReferralCodeCollection&MockObject
     */
    private function buildCollection(ReferralCode $firstItem): ReferralCodeCollection
    {
        $collection = $this->createMock(ReferralCodeCollection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('setPageSize')->willReturnSelf();
        $collection->method('getFirstItem')->willReturn($firstItem);

        return $collection;
    }
}
