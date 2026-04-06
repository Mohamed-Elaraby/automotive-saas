# Stripe Consistency Review Runbook

## Goal
- validate real Stripe-linked subscription records after the latest recovery/sync fixes
- identify records that still need manual cleanup before continuing multi-product rollout

## Commands
Run in this order on the environment that has the real central database and Stripe config:

```bash
php artisan billing:review-stripe-consistency --only-issues
php artisan billing:review-stripe-consistency --sync --only-issues --format=json --output=/tmp/stripe-review.json
php artisan billing:review-stripe-consistency --sync --only-issues --format=csv --output=/tmp/stripe-review.csv
php artisan billing:repair-product-subscription-mirrors --only-missing
php artisan billing:repair-product-subscription-mirrors --apply --only-missing
```

## Recommended Sequence
1. Run without `--sync` first.
2. Review the raw remaining issues.
3. Run again with `--sync`.
4. Compare whether `needs_review` drops.
5. Export `json` or `csv` if follow-up cleanup is needed.

## Meaning Of Main Fields
- `Sync`
  - `SKIPPED`: no sync attempted
  - `SYNCED`: sync executed and local record changed/refreshed
  - `NO_RESULT`: sync path ran but did not resolve/update the record
  - `FAILED`: sync threw an exception and needs investigation
- `Mirror`
  - `MATCHED`: `tenant_product_subscriptions` matches the legacy `subscriptions` record
  - `MISSING`: expected mirror row does not exist
  - `MISMATCH`: mirror row exists but differs
- `Mixed Cust Inv`
  - count of local ledger invoices for the same Stripe customer that point to another Stripe subscription id

## Issue Interpretation
- `recoverable_missing_gateway_subscription_id`
  - local Stripe subscription id is missing
  - local checkout session exists, so current recovery logic should usually fix it during `--sync`
- `missing_gateway_subscription_id`
  - local Stripe subscription id is missing and there is no checkout session to recover from
  - this usually needs direct Stripe/customer review
- `local_plan_price_mismatch`
  - local `plan_id` does not match the current local `gateway_price_id` expectation
  - verify whether local plan is stale or Stripe subscription is on the wrong price
- `missing_product_subscription_mirror`
  - legacy `subscriptions` row exists but `tenant_product_subscriptions` was not mirrored
  - this should be fixed before relying on tenant/product read path
- `product_subscription_mirror_mismatch`
  - mirrored row exists but lifecycle or gateway linkage diverged
  - compare both rows before any manual correction
- `mixed_customer_invoice_history`
  - one customer has invoices tied to another Stripe subscription id
  - this may be legitimate for historical restarts, but needs verification before using invoice history operationally

## Triage Order
1. `FAILED`
2. `missing_gateway_subscription_id`
3. `local_plan_price_mismatch`
4. `missing_product_subscription_mirror`
5. `product_subscription_mirror_mismatch`
6. `mixed_customer_invoice_history`

## What To Do Per Result
- If `needs_review=0` after `--sync`
  - billing consistency step is complete
  - move to second-product purchase/enablement flow on the same tenant
- If issues remain after `--sync`
  - export JSON
  - review records by tenant
  - fix linkage/mirror issues first
  - rerun the command until clean or until remaining cases are confirmed intentional

## Mirror Repair
Use this when the report shows:
- `missing_product_subscription_mirror`
- `product_subscription_mirror_mismatch`

Recommended sequence:
```bash
php artisan billing:repair-product-subscription-mirrors --only-missing
php artisan billing:repair-product-subscription-mirrors --apply --only-missing
php artisan billing:repair-product-subscription-mirrors --apply --subscription=<local_subscription_id>
php artisan billing:review-stripe-consistency --only-issues
```

Meaning:
- dry-run shows what would be created or repaired
- `--only-missing` is the safest first production pass
- `--subscription=<id>` is the safest way to repair one mismatched mirror deliberately

## Notes
- this command reads local database state; it does not replace direct Stripe dashboard verification for ambiguous records
- use `--tenant=<tenant_id>` or `--subscription=<id>` for focused follow-up checks
- do not use `route:cache` in this project
