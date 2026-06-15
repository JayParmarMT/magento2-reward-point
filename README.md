# Meetanshi RewardPoints

**Version:** 1.0.0
**Magento Compatibility:** Magento Open Source / Adobe Commerce 2.4.8 – 2.4.9
**PHP Compatibility:** PHP 8.3, PHP 8.4

---

## Overview

Meetanshi RewardPoints is a production-grade loyalty and reward points extension for Magento 2, combining the best features of leading reward point solutions.

---

## Installation

### Via Composer (Recommended)

```bash
composer require meetanshi/module-reward-points
bin/magento module:enable Meetanshi_RewardPoints
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy -f
bin/magento cache:flush
```

### Manual Installation

1. Download the extension package.
2. Extract to `app/code/Meetanshi/RewardPoints/`.
3. Run the setup commands above.

---

## Configuration

Navigate to **Stores → Configuration → Meetanshi → Reward Points** to configure:

- **General** — Enable module, labels, balance limits
- **Landing Page** — CMS page integration, footer link
- **Highlight** — Storefront display options
- **Earning** — Rounding, tax/shipping, holding periods
- **Spending** — Min/max limits, shipping discount, tax handling
- **Display** — Top link, minicart, cart display options
- **Email** — Notification templates and schedules
- **Social Behavior** — Facebook, Twitter, Pinterest integration
- **Customer Referrals** — Referral codes, URL modes
- **Tiered Rewards Program** — Milestone tiers configuration
- **Advanced** — Custom events, CSS overrides

---

## Upgrade

```bash
composer update meetanshi/module-reward-points
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy -f
bin/magento cache:flush
```

---

## Uninstall

```bash
bin/magento module:disable Meetanshi_RewardPoints
bin/magento setup:upgrade
composer remove meetanshi/module-reward-points
```

To remove database tables:
```bash
bin/magento setup:uninstall
```

---

## CLI Commands

| Command | Description |
|---|---|
| `meetanshi:rewardpoints:expire` | Manually run point expiration |
| `meetanshi:rewardpoints:tier:recalculate` | Recompute customer tiers |
| `meetanshi:rewardpoints:reminders:send` | Send pre-expiration reminder emails |
| `meetanshi:rewardpoints:rule:rebuild-index` | Rebuild catalog rule index |
| `meetanshi:rewardpoints:account:repair` | Recompute balance from ledger |
| `meetanshi:rewardpoints:status` | Module health check |

---

## FAQ

**Q: Can I use this alongside Adobe Commerce native rewards?**
A: Yes, ensure `Magento_Reward` is disabled to avoid conflicts.

**Q: Does it support multi-store setups?**
A: Yes, all configurations are scoped per website/store view.

**Q: Is Hyvä theme supported?**
A: An optional companion module `meetanshi/module-reward-points-hyva` provides Hyvä compatibility.

---

## Support

- Email: support@meetanshi.com
- Website: https://meetanshi.com

---

## Changelog

### 1.0.0 — Initial Release

- Full earning rate engine (catalog rules, cart rules, behavior events)
- Full spending rate engine (cart spending rules, checkout integration)
- Tier/Milestone program
- Referral program
- Sell by Points
- Email notifications
- REST API & GraphQL
- Import/Export
- Reports
- Hyvä companion module
# magento2-reward-point
