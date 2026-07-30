# TODO - Nairobi timezone + Shillings currency

- [ ] Add a global Nairobi timezone initializer for all PHP requests (e.g., in config/database.php or a shared core bootstrap).
- [ ] Create a shared currency formatting helper (KES/shillings) and replace all hardcoded `$` displays/labels in billing/invoice-related PHP.
- [ ] Update billing UI inputs/preview labels from "Amount ($)" and "USD" to "Amount (KSh)" and "Amount in Shillings".
- [ ] Update receipt and invoice views to display amounts as "KSh" instead of `$`.
- [ ] Update any activity logs that include `$` formatting.
- [ ] Update remaining billing module pages (index/view/edit/add/delete/receipt) that show currency.
- [ ] Quick sanity check: verify no remaining `$<amount>` strings in billing modules.

