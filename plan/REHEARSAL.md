# VoteLab dress rehearsal (before Stage 3b)

About 90 minutes with 8–12 people. Goal: the whole night (sharing, voting, hidden results, closing, the reveal) on real phones, on real networks, with nothing faked.

## 1. Set up (the day before)

**Server.** A staging deployment that matches production: HTTPS, `APP_ENV=staging`, `APP_DEBUG=false`, a shared cache store (database or redis), queue worker and scheduler running, Sentry DSN set.

**Keys and checks.**
- `VOTING_PHONE_HASH_KEY` and `VOTING_FINGERPRINT_KEY` are set, and `PAYMENTS_LOGGING_ENABLED=false`.
- Paystack TEST keys, with the webhook URL set in the Paystack dashboard.
- Run `php artisan ops:sentry-check` and confirm the event arrives in Sentry with phone numbers and emails scrubbed.
- `/health` returns `{"status":"ok"}`.

**Data.**
1. Run `php artisan organizations:create`. The owner accepts the invite and turns on two-factor.
2. The owner invites one admin and one staff member.
3. In Settings, upload the logo and set the brand colour.
4. Create one program with four categories:
   - Artiste of the Year: paid, GHS 1.00;
   - Most Popular: free and paid, 1 free vote a day;
   - Best Dancer: paid;
   - Best Newcomer: paid.
5. Add 4–6 nominees each, with real portraits. One must have a very long name.
6. Work through the launch checklist until it reads 9 of 9, then publish.
7. Run `php artisan nominees:photo-variants`.

**Kit.**
- **Projector:** laptop with Chrome, HDMI cable, mains power.
- **Phones:** at least two cheap Android phones, one iPhone, and one phone with "reduce motion" turned on.
- **Networks:** MTN, Telecel and AirtelTigo data, plus venue Wi-Fi.
- **Payment:** the Paystack test mobile-money number is `0551234987` (use the OTP that Paystack's test checkout shows).

## 2. Roles

| Who | Device | Does |
|---|---|---|
| **Coordinator** (you) | Laptop | Runs the clock, calls each step, collects issues |
| **Owner** | Phone + laptop | Opens/hides/closes/reveals categories, records a refund |
| **Admin** | Phone | Watches the overview and results, looks up a payment |
| **Staff** | Phone | Edits a nominee photo while voting is live; checks what staff *can't* see |
| **Stage operator** | Laptop on projector | Keeps `/stage/{org}/{program}` full screen; switches categories with `?category=` |
| **Voters ×5–8** | Their own phones, mixed networks | Follow the voter script below |

## 3. Running order

| Time | Step | Watch for |
|---|---|---|
| 0:00 | Owner posts the program link and two nominee share kits in a WhatsApp group | Link preview shows the nominee image, never counts |
| 0:05 | **Voters:** open the link, find a nominee **by code** and **by name**, cast a paid vote (1, 5 and 20 votes across the group) | Total shown before paying; Paystack prompt arrives; result page says “Your votes are in”; bars move on stage within ~5 s |
| 0:15 | **Voters:** one declines the mobile-money prompt, one lets it time out, one pays by card | Declined = “Payment not completed”; timed-out stays “Confirming…” then settles; nothing double-counted |
| 0:20 | **Voters:** free vote in Most Popular, then try a second free vote | Second attempt refused politely; “free votes left today” is right |
| 0:25 | Three voters on the **same Wi-Fi** vote at the same moment | No one is blocked by rate limits |
| 0:30 | **Owner:** Hide results in Best Dancer; voters keep voting there | Public page and stage show the padlock; **no counts anywhere** (page, share preview, stage), including after refresh and Back |
| 0:40 | **Staff:** re-crop a nominee photo during live voting | New photo on cards within a refresh; staff can't see revenue or hidden counts |
| 0:45 | **Owner:** Close Best Newcomer while a voter is mid-payment | That payment still counts if confirmed within the late window; stage says “results coming up” |
| 0:55 | **Owner:** Reveal Best Newcomer, then Best Dancer with **Reveal anyway** while one payment is pending | “And the winner is…” then the podium and confetti; joint winners if tied; the pending payment appears under Refunds |
| 1:05 | **Owner:** Record that refund (password asked). **Admin:** look up a payment by phone number | Refund is in History; lookup is in History |
| 1:10 | **Voters:** turn off data mid-vote, then back on; press Back after paying; rotate the phone | No error screens; the pending page re-checks by itself |
| 1:15 | Everyone at the back of the room reads the stage | Category, names, codes and the voting link readable from the back row |

**Accessibility pass (any two voters):** one with TalkBack/VoiceOver, one with the largest text size. Can they find a nominee and reach the Pay button?

## 4. Afterwards (coordinator)

- `php artisan votes:rebuild-tallies --all --dry-run` must report **no drift**.
- Compare **Revenue** on the overview with the Paystack test dashboard total.
- Sentry has no unhandled errors.
- `/health` is still `ok`.
- Nothing is stuck in `payments:reconcile-pending`.

## 5. Recording issues

One line each, in a shared sheet:

`time · who · phone + network · page · what happened · what you expected · screenshot`

Mark each as **blocker** (wrong money or votes, any count visible while hidden, a crash), **fix before launch**, or **later**. Stage 3b starts once there are no blockers.
