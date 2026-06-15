<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Test\Unit\Model\Total\Quote;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Store\Model\StoreManagerInterface;
use Meetanshi\RewardPoints\Api\AccountRepositoryInterface;
use Meetanshi\RewardPoints\Api\SpendingRateRepositoryInterface;
use Meetanshi\RewardPoints\Helper\Config;
use Meetanshi\RewardPoints\Model\Calculator\SpendingCalculator;
use Meetanshi\RewardPoints\Model\Rule\Validator\SpendingRuleConditionValidator;
use Meetanshi\RewardPoints\Model\Total\Quote\Discount;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for Discount::fetch()
 */
#[AllowMockObjectsWithoutExpectations]
class DiscountTest extends TestCase
{
    /** @var Config&MockObject */
    private Config $config;

    /** @var AccountRepositoryInterface&MockObject */
    private AccountRepositoryInterface $accountRepository;

    /** @var SpendingRateRepositoryInterface&MockObject */
    private SpendingRateRepositoryInterface $spendingRateRepository;

    /** @var SearchCriteriaBuilder&MockObject */
    private SearchCriteriaBuilder $searchCriteriaBuilder;

    /** @var SpendingCalculator&MockObject */
    private SpendingCalculator $spendingCalculator;

    /** @var StoreManagerInterface&MockObject */
    private StoreManagerInterface $storeManager;

    /** @var PriceCurrencyInterface&MockObject */
    private PriceCurrencyInterface $priceCurrency;

    /** @var SpendingRuleConditionValidator&MockObject */
    private SpendingRuleConditionValidator $spendingRuleConditionValidator;

    /** @var LoggerInterface&MockObject */
    private LoggerInterface $logger;

    private Discount $discount;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->accountRepository = $this->createMock(AccountRepositoryInterface::class);
        $this->spendingRateRepository = $this->createMock(SpendingRateRepositoryInterface::class);
        $this->searchCriteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $this->spendingCalculator = $this->createMock(SpendingCalculator::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->priceCurrency = $this->createMock(PriceCurrencyInterface::class);
        $this->spendingRuleConditionValidator = $this->createMock(SpendingRuleConditionValidator::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->discount = new Discount(
            $this->config,
            $this->accountRepository,
            $this->spendingRateRepository,
            $this->searchCriteriaBuilder,
            $this->spendingCalculator,
            $this->storeManager,
            $this->priceCurrency,
            $this->spendingRuleConditionValidator,
            $this->logger,
        );
    }

    // -------------------------------------------------------------------------
    // fetch — discount is zero
    // -------------------------------------------------------------------------

    #[Test]
    public function fetchReturnsNullWhenRewardPointsDiscountIsZero(): void
    {
        $quote = $this->createMock(Quote::class);
        $total = $this->createMock(Total::class);

        $quote->method('getData')->with('reward_points_discount')->willReturn(0.0);

        $result = $this->discount->fetch($quote, $total);

        $this->assertNull($result);
    }

    #[Test]
    public function fetchReturnsNullWhenRewardPointsDiscountIsNegative(): void
    {
        $quote = $this->createMock(Quote::class);
        $total = $this->createMock(Total::class);

        $quote->method('getData')->with('reward_points_discount')->willReturn(-5.0);

        $result = $this->discount->fetch($quote, $total);

        $this->assertNull($result);
    }

    // -------------------------------------------------------------------------
    // fetch — discount is positive
    // -------------------------------------------------------------------------

    #[Test]
    public function fetchReturnsArrayWithCorrectCodeWhenDiscountIsPositive(): void
    {
        $quote = $this->createMock(Quote::class);
        $total = $this->createMock(Total::class);

        $quote->method('getData')->with('reward_points_discount')->willReturn(15.0);
        $this->config->method('getDiscountLabel')->willReturn('My Reward Discount');

        $result = $this->discount->fetch($quote, $total);

        $this->assertIsArray($result);
        $this->assertSame('reward_points', $result['code']);
    }

    #[Test]
    public function fetchReturnsNegativeValueEqualToDiscount(): void
    {
        $quote = $this->createMock(Quote::class);
        $total = $this->createMock(Total::class);

        $quote->method('getData')->with('reward_points_discount')->willReturn(25.50);
        $this->config->method('getDiscountLabel')->willReturn('Reward');

        $result = $this->discount->fetch($quote, $total);

        $this->assertIsArray($result);
        $this->assertSame(-25.50, $result['value']);
    }

    #[Test]
    public function fetchUsesConfiguredDiscountLabelAsTitle(): void
    {
        $quote = $this->createMock(Quote::class);
        $total = $this->createMock(Total::class);

        $quote->method('getData')->with('reward_points_discount')->willReturn(10.0);
        $this->config->method('getDiscountLabel')->willReturn('Loyalty Discount');

        $result = $this->discount->fetch($quote, $total);

        $this->assertIsArray($result);
        // title is a Phrase object wrapping the configured label.
        $this->assertSame('Loyalty Discount', (string) $result['title']);
    }

    #[Test]
    public function fetchDefaultsTitleToRewardPointsDiscountWhenLabelIsEmpty(): void
    {
        $quote = $this->createMock(Quote::class);
        $total = $this->createMock(Total::class);

        $quote->method('getData')->with('reward_points_discount')->willReturn(10.0);
        $this->config->method('getDiscountLabel')->willReturn('');

        $result = $this->discount->fetch($quote, $total);

        $this->assertIsArray($result);
        $this->assertSame('Reward Points Discount', (string) $result['title']);
    }

    #[Test]
    public function fetchReturnsTitleAsPhraseObject(): void
    {
        $quote = $this->createMock(Quote::class);
        $total = $this->createMock(Total::class);

        $quote->method('getData')->with('reward_points_discount')->willReturn(10.0);
        $this->config->method('getDiscountLabel')->willReturn('Bonus');

        $result = $this->discount->fetch($quote, $total);

        $this->assertIsArray($result);
        // TotalsConverter calls render() only when is_object() — verify title is not a plain string.
        $this->assertIsObject($result['title']);
    }
}
