# Sameday Courier

If your are facing some issues when working with our solution our you want to leave us a feedback, please don't hesitate to contact us at plugineasybox@sameday.ro !

## Changelog

### 1.8.12
- Added configurable order status change on AWB generation, with a "Do not change" option.
- Store the previous order status on the AWB row and restore it when the AWB is removed.

### 1.8.11
- Added a bulk AWB confirmation disclaimer for cross-border currency mismatches between storefront and destination currency.
- Fixed bulk currency-alert AJAX bootstrapping so mismatch warnings load correctly on all supported PrestaShop versions.
- Fixed currency mismatch detection when the order currency row is soft-deleted.
- Fixed bulk AWB cancel to call Sameday before removing local data, and to clear orphan tracking left by failed cancels.
- Fixed PrestaShop 1.6 checkout carrier extras by registering/handling `displayCarrierList` (legacy `extraCarrier` alias).

### 1.8.10
- Fixed city nomenclature on AJAX-based checkout forms so the city field is converted to a select after the address form refreshes.

### 1.8.9
- Changed Bulgaria API currency from `BGN` to `EUR`.
- Removed the checkout-only BGN/EUR conversion label logic.
- Added destination currency to checkout estimation requests so BG estimates match AWB generation.
