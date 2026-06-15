<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Test\Unit\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;
use Meetanshi\RewardPoints\Helper\Config;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

/**
 * Unit tests for Config helper — formatPoints and point-label getters
 */
#[AllowMockObjectsWithoutExpectations]
class ConfigTest extends TestCase
{
    /** @var ScopeConfigInterface&MockObject */
    private ScopeConfigInterface $scopeConfig;

    private Config $config;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);

        // Config extends AbstractHelper which requires Context in its constructor.
        // We bypass the constructor and inject scopeConfig directly into the
        // protected property that AbstractHelper::__construct() would normally populate.
        $context = $this->createMock(Context::class);
        $context->method('getScopeConfig')->willReturn($this->scopeConfig);

        $this->config = new Config($context);
    }

    // -------------------------------------------------------------------------
    // formatPoints — zero, singular, plural
    // -------------------------------------------------------------------------

    #[Test]
    public function formatPointsReturnsZeroPointLabelWhenPointsIsZero(): void
    {
        $this->scopeConfig
            ->method('getValue')
            ->with(
                'meetanshi_rewardpoints/general/zero_point_label',
                ScopeInterface::SCOPE_STORE,
                null,
            )
            ->willReturn('No Points');

        $result = $this->config->formatPoints(0);

        $this->assertSame('No Points', $result);
    }

    #[Test]
    public function formatPointsUsesSingularLabelWhenPointsIsOne(): void
    {
        $this->configureLabelValues(
            singular: 'Point',
            plural: 'Points',
            zero: 'No Points',
        );

        $result = $this->config->formatPoints(1);

        $this->assertSame('1 Point', $result);
    }

    #[Test]
    public function formatPointsUsesPluralLabelWhenPointsIsTwo(): void
    {
        $this->configureLabelValues(
            singular: 'Point',
            plural: 'Points',
            zero: 'No Points',
        );

        $result = $this->config->formatPoints(2);

        $this->assertSame('2 Points', $result);
    }

    #[Test]
    public function formatPointsUsesPluralLabelForHighValues(): void
    {
        $this->configureLabelValues(
            singular: 'Point',
            plural: 'Points',
            zero: 'No Points',
        );

        $result = $this->config->formatPoints(1000);

        $this->assertSame('1000 Points', $result);
    }

    #[Test]
    public function formatPointsIncludesSpaceBetweenAmountAndLabel(): void
    {
        $this->configureLabelValues(
            singular: 'Pt',
            plural: 'Pts',
            zero: '—',
        );

        $result = $this->config->formatPoints(5);

        // Confirm the number and label are separated by a single space.
        $this->assertSame('5 Pts', $result);
    }

    #[Test]
    public function formatPointsPassesStoreIdToLabelGetters(): void
    {
        $this->scopeConfig
            ->method('getValue')
            ->with(
                $this->logicalOr(
                    $this->equalTo('meetanshi_rewardpoints/general/point_label_plural'),
                    $this->equalTo('meetanshi_rewardpoints/general/point_label'),
                ),
                ScopeInterface::SCOPE_STORE,
                5,
            )
            ->willReturn('Points');

        $result = $this->config->formatPoints(3, 5);

        $this->assertStringContainsString('3', $result);
    }

    // -------------------------------------------------------------------------
    // getPointLabel / getPointLabelPlural / getZeroPointLabel
    // -------------------------------------------------------------------------

    #[Test]
    public function getPointLabelReturnsConfiguredSingularLabel(): void
    {
        $this->scopeConfig
            ->method('getValue')
            ->with('meetanshi_rewardpoints/general/point_label', ScopeInterface::SCOPE_STORE, null)
            ->willReturn('Star');

        $this->assertSame('Star', $this->config->getPointLabel());
    }

    #[Test]
    public function getPointLabelPluralReturnsConfiguredPluralLabel(): void
    {
        $this->scopeConfig
            ->method('getValue')
            ->with('meetanshi_rewardpoints/general/point_label_plural', ScopeInterface::SCOPE_STORE, null)
            ->willReturn('Stars');

        $this->assertSame('Stars', $this->config->getPointLabelPlural());
    }

    #[Test]
    public function getZeroPointLabelReturnsConfiguredZeroLabel(): void
    {
        $this->scopeConfig
            ->method('getValue')
            ->with('meetanshi_rewardpoints/general/zero_point_label', ScopeInterface::SCOPE_STORE, null)
            ->willReturn('No Stars Yet');

        $this->assertSame('No Stars Yet', $this->config->getZeroPointLabel());
    }

    // -------------------------------------------------------------------------
    // getPointLabelPosition — before / after
    // -------------------------------------------------------------------------

    #[Test]
    public function getPointLabelPositionReturnsBeforeWhenConfiguredAsBefore(): void
    {
        $this->scopeConfig
            ->method('getValue')
            ->with('meetanshi_rewardpoints/general/label_position', ScopeInterface::SCOPE_STORE, null)
            ->willReturn('before');

        $this->assertSame('before', $this->config->getPointLabelPosition());
    }

    #[Test]
    public function getPointLabelPositionReturnsAfterWhenConfiguredAsAfter(): void
    {
        $this->scopeConfig
            ->method('getValue')
            ->with('meetanshi_rewardpoints/general/label_position', ScopeInterface::SCOPE_STORE, null)
            ->willReturn('after');

        $this->assertSame('after', $this->config->getPointLabelPosition());
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

    /**
     * Configure scopeConfig to return label strings for all three label paths.
     *
     * @param string $singular
     * @param string $plural
     * @param string $zero
     * @return void
     */
    private function configureLabelValues(string $singular, string $plural, string $zero): void
    {
        $this->scopeConfig
            ->method('getValue')
            ->willReturnMap([
                [
                    'meetanshi_rewardpoints/general/point_label',
                    ScopeInterface::SCOPE_STORE,
                    null,
                    $singular,
                ],
                [
                    'meetanshi_rewardpoints/general/point_label_plural',
                    ScopeInterface::SCOPE_STORE,
                    null,
                    $plural,
                ],
                [
                    'meetanshi_rewardpoints/general/zero_point_label',
                    ScopeInterface::SCOPE_STORE,
                    null,
                    $zero,
                ],
            ]);
    }
}
