# VoteLab live smoke test (production, after Stage 3b)

About 45 minutes, two people: an **owner** at a laptop and a **payer** with four phones/SIMs and a bank card. Real money: about GHS 5.10 in total, all refunded.

## 1. Before you start

- Stage 3b is deployed: HTTPS, security headers, `APP_ENV=production`. The app booted, so the boot guard passed: live Paystack key, phone and fingerprint keys set, shared cache, debug off, PayZephyr logging off.
- Queue worker and scheduler are running; `/health` returns `{"status":"ok"}` (with `X-Health-Token`, every check `ok`).
- The Paystack **live** webhook URL is set to `https://<domain>/api/webhooks/paystack` (not PayZephyr's `/payments/webhook`, which is disabled).
- Sentry is receiving events; note the time you start.
- In the Paystack dashboard, decide whether **customer receipts** are on. VoteLab sends no receipt email of its own; the receipt a voter gets is Paystack's.

**Smoke-test program.**
- In your own organization, whose payout subaccount you control, create a program titled “Smoke test”.
- Give it one paid category, “Smoke test”, at GHS 1.00 per vote, with two nominees.
- Publish it. Don't share the link.

## 2. The four live votes

For each vote, the payer opens the nominee link on that phone, buys **1 vote** and enters their own email for the receipt.

| # | Method | Payer's number / card |
|---|---|---|
| 1 | MTN MoMo | MTN line |
| 2 | Telecel Cash | Telecel line |
| 3 | AirtelTigo Money | AirtelTigo line |
| 4 | Card | Bank card |

**After each payment, check:**

1. **Result page:** “Your votes are in”, 1 vote, amount **GHS 1.02** (GHS 1.00 vote + fee), the reference shown with a copy button.
2. **Crediting:** the nominee's count went up by exactly 1. The owner checks this in Results; it should appear within seconds, from either the callback or the webhook.
3. **Transactions** (owner, Results & money → Transactions):
   - status is *successful*;
   - for mobile money, the masked phone ends in the **last three digits of the paying number** (`*******123`);
   - for the card, no phone is shown, which is correct.
4. **Lookup:** search the full phone number. It finds that one payment, and the lookup appears in History.
5. **Receipt:** if Paystack customer receipts are on, the payer's email has Paystack's receipt with the same reference and amount. If they're off, nothing arrives; note it.

Write down each **reference** and its **time**.

## 3. Totals

- Overview → **Revenue**: **GHS 4.00** (the vote value credited to you).
- Results & money → **Revenue**: charged to voters = the sum of the four amounts (about **GHS 4.08**).
- **Paystack dashboard:** the four transactions, with the same references, amounts and *success*. Their total matches “Charged to voters”, and settlements go to your subaccount.
- `php artisan votes:rebuild-tallies --all --dry-run` reports **no drift**.
- **Sentry:** no new errors since you started. Webhook deliveries in Paystack show **200**.

## 4. Refund path

1. **Close** the Smoke test category.
2. The payer starts one more MoMo payment and does **not** approve it yet.
3. The owner **reveals with “Reveal anyway”** (password asked).
4. The payer approves the payment.

Then check:
- It appears under **Refunds** (paid, not counted), and the nominee's count does **not** change.
- Refund it in the Paystack dashboard, then use **Record refund** in VoteLab with the Paystack refund reference (password asked). It moves out of the queue, and **History** shows `refund.recorded`.

## 5. Refund the four counted votes

- Refund payments 1–4 in the **Paystack dashboard**, then list their refund references next to the payment references in your smoke-test notes.
- VoteLab only records refunds for payments it didn't count. That's by design (D-98): a counted vote stays in the results, so these four aren't recorded in VoteLab.
- **Archive** the Smoke test program so it never shows publicly.

## 6. Pass / fail

**Pass when:**
- all four methods credit exactly once;
- the masked phones match;
- VoteLab and Paystack totals agree;
- there is no drift;
- Sentry is clean;
- the late payment is queued, refunded and recorded.

Any mismatch in money or votes is a **launch blocker**. Stop and report it with its references.
