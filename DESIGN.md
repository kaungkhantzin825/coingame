# Merge Zoo — Backend & Economy Design

Laravel 10 + Sanctum API backing the Flutter merge game. Server is the **single
source of truth** for every balance. The client only reports *game facts*
(score, merges); the server decides rewards, with caps.

## Currencies

| Currency | Symbol | Withdrawable | Earned from |
|----------|--------|--------------|-------------|
| Coins    | G      | no (convert first) | merges, daily check-in, tasks, rewarded ads, live-ops timer, mini-game, referrals |
| Cash     | RM     | **yes**      | converting coins, some task/referral payouts |
| Gems     | ◆      | no           | rare rewards, IAP (later) — spent on boosters/skips |

- Convert rate + min withdraw live in `app_configs` (default: `100000` coins = `RM 1.00`, min withdraw `RM 5.00`).
- All money stored as integer **cents** (`cash_cents`). Never floats.

## Ledger

`transactions` is append-only. Every coin/cash/gem change is one row with the
`*_delta` and the `balance_*_after`. `users.coins / cash_cents / gems` are a
cache; a reconcile job can rebuild them from the ledger. All mutations go
through `LedgerService::apply()` inside a DB transaction with a row lock on the
user and an `idempotency_key`.

## Anti-fraud / integrity

- Client never sends "+N coins". `/game/session/end` sends `{score, merges}`;
  server recomputes the reward and clamps it (`max_coins_per_session`,
  plausibility: merges vs. session time).
- Daily caps: `daily_coin_cap`, `daily_ad_reward_cap`, `daily_session_cap`.
- Rewarded ads: client call gives **provisional** coins; AdMob **SSV callback**
  confirms or the reward is revoked. `ad_views.verified`.
- Withdrawals: manual admin review, min amount, hold, one verified contact +
  one device per payout, KYC over a threshold, velocity checks.
- `devices` table: fingerprint, emulator flag (Play Integrity later), payout
  count. One paid device.
- Rate limiting per route; Sanctum token per device.

## Schema (migrations)

- **users** (+columns): `device_id?` (unique), `referral_code?` (unique, 12),
  `referred_by?`, `coins`, `cash_cents`, `gems`, `daily_streak`,
  `last_daily_claim_on?`, `total_withdrawn_cents`, `status`, `is_admin`,
  `banned_at?`, `meta` json. `name/email/password` stay **non-nullable** —
  device accounts get generated placeholders (`Player####`, `d_…@device.local`,
  random password) in `DeviceAuthController` instead of a doctrine/dbal
  nullable migration.
- **devices**: `device_id` (unique — **not** `uuid`), `user_id`, `platform`,
  `model`, `os_version`, `app_version`, `is_emulator`, `last_ip`,
  `last_seen_at`, `payout_count`, `fingerprint` json.
- **app_configs**: `key`, `value` (json), `group`. Cached.
- **transactions**: `user_id`, `type`, `coins_delta`, `cash_cents_delta`,
  `gems_delta`, `balance_coins_after`, `balance_cash_after`, `balance_gems_after`,
  `idempotency_key` (unique), `meta` json, `source`.
- **game_sessions**: `user_id`, `token`, `started_at`, `ended_at?`, `score`,
  `merges`, `coins_awarded`, `status`, `reject_reason?`, `ip`, `meta`.
- **daily_rewards**: `day` (1-7), `reward_coins`, `reward_cash_cents`,
  `reward_gems`, `is_bonus`.
- **tasks**: `key`, `title`, `description`, `kind` (one_time|daily|progress),
  `goal`, `event` (which game event advances it: `play_game`, `merge`,
  `reach_score`, `watch_ad`, `invite`), `reward_coins`, `reward_cash_cents`,
  `reward_gems`, `icon`, `sort`, `active`.
- **user_tasks**: `user_id`, `task_id`, `period` (date|null), `progress`,
  `completed_at?`, `claimed_at?`. unique(user,task,period).
- **liveops_events**: `key`, `title`, `kind` (tap_timer), `duration_seconds`,
  `reward_coins`, `ad_multiplier`, `cooldown_seconds`, `active`, `starts_at?`, `ends_at?`.
- **user_liveops**: `user_id`, `event_id`, `started_at`, `claimed_at?`, `boosted`.
- **ad_placements**: `placement`, `network`, `ad_unit_id`, `format`,
  `priority`, `floor_ecpm_cents`, `reward_coins`, `daily_cap`, `active`.
  Backend serves the ordered waterfall per placement.
- **ad_views**: `user_id`, `placement`, `network`, `idempotency_key` (unique),
  `reward_coins`, `provisional_at`, `verified`, `verified_at?`, `ssv_payload` json.
- **withdraw_methods**: `key`, `label`, `type` (ewallet|bank), `min_cents`,
  `fee_cents`, `fee_percent`, `fields` json, `active`, `sort`.
- **withdrawals**: `user_id`, `method_key`, `amount_cents`, `fee_cents`,
  `net_cents`, `account` (encrypted **text** column, not json), `status`
  (pending|approved|processing|paid|rejected), `admin_note?`, `reference?`,
  `processed_by?`, `processed_at?`, `ip`.
- **referrals**: `referrer_id`, `referee_id`, `bonus_coins`, `bonus_cash_cents`,
  `status`, `qualified_at?`. unique(referee).

## API (`/api`, `auth:sanctum` unless noted)

**Only the phase-1 routes are live.** `php artisan route:list --path=api` returns
5 routes. Rows marked *planned* have a migration, a model and (for most) a
Filament resource, but **no route and no controller** — requesting them 404s.
Do not treat this table as a list of what exists; use the `Status` column.

| Status | Method | Path | Purpose |
|--------|--------|------|---------|
| live | POST | `/auth/device` | *(public)* register/login by `device_id`, returns token + profile |
| live | GET  | `/me` | profile + balances + config + flags |
| live | POST | `/game/session/start` | → session token |
| live | POST | `/game/session/end` | `{session_token, score, merges}` → validated coin reward |
| live | GET  | `/config` | *(public)* remote config, min version, maintenance |
| planned | GET  | `/daily` | 7-day calendar + state |
| planned | POST | `/daily/claim` | claim today, advance streak |
| planned | GET  | `/tasks` | tasks + progress |
| planned | POST | `/tasks/{key}/claim` | claim finished task |
| planned | GET  | `/liveops` | active event + user state |
| planned | POST | `/liveops/{key}/start` | start server timer |
| planned | POST | `/liveops/{key}/claim` | claim after duration (optional `boosted` via ad) |
| planned | GET  | `/ads/waterfall?placement=` | ordered ad units + reward + remaining cap |
| planned | POST | `/ads/reward` | `{placement, network, idempotency_key}` → provisional coins |
| planned | POST | `/ads/ssv/admob` | *(public, signed)* AdMob server-side verify callback |
| planned | POST | `/wallet/convert` | `{coins}` → cash |
| planned | GET  | `/withdraw/methods` | payout options |
| planned | POST | `/withdraw` | `{method_key, amount_cents, account}` → pending |
| planned | GET  | `/withdraw/history` | |
| live | GET  | `/referral` | code, invite stats, admin-set amounts, `can_redeem` |
| live | POST | `/referral/redeem` | `{code}` → credits the invitee bonus, holds the inviter bonus |

Rate limits in force: `auth/device` 20/min, both `game/session/*` 60/min,
`referral/redeem` 10/min.

**Referral program.** Two payouts, both priced by the admin at
`/admin/referral-settings` (keys `referral_enabled`, `referral_bonus_coins`,
`referral_referee_bonus_coins`, `referral_qualify_sessions`):

1. The invitee is credited the moment a code is accepted
   (`referral_redeem:{user}`).
2. The inviter is credited only after the invitee finishes
   `referral_qualify_sessions` runs — checked inside
   `GameController::end()` via `ReferralService::qualifyIfEligible()`, keyed
   `referral_bonus:{referral_id}` so it can never pay twice.

A player can only ever be referred once (`users.referred_by` is set on
success); the same code path serves both signup (`POST /auth/device` with
`referral_code`) and the in-app "enter a friend's code" screen.

Admin: the Filament 3 panel **already exists** at `/admin` (guard: `User`
implements `FilamentUser`, `canAccessPanel()` requires `is_admin`). Resources
cover Users, Devices, Transactions, GameSessions, DailyRewards, Tasks,
LiveopsEvents, AdPlacements, AdViews, WithdrawMethods, Withdrawals, Referrals
and AppConfigs. Withdrawals expose approve / mark-paid / reject row actions
backed by `WithdrawalService`; the rest are plain CRUD. Create the first admin
with `php artisan make:admin <email> <password>`. There is no fraud dashboard
yet beyond `Widgets\StatsOverview`.

## Ads — waterfall

Two layers:
1. **AdMob (`google_mobile_ads`)** as the SDK, with **mediation** adapters
   (AppLovin, Unity, Meta, ironSource, Pangle) configured in the AdMob console
   — that gives a real optimized waterfall + bidding with one SDK.
2. **Backend placement waterfall** on top: `/ads/waterfall` returns an ordered
   list of `{network, ad_unit_id}` per placement so floors/networks can change
   without an app update. The Flutter `AdService` tries each in order.

Rewarded placements: `liveops`, `double_reward` (post game-over 2×),
`free_booster`, `daily_boost`, `task_skip`. Interstitials between games (capped).

## Flutter side

Implemented (`lib/services/`):

- `ApiClient` — `package:http`, JSON envelope, `Authorization: Bearer` when a
  token exists, 15s timeout. Base URL from `--dart-define=API_BASE_URL=…`
  (default `http://10.0.2.2:8000/api`, the Android emulator host).
- `AuthService` — device register/login on launch, token persisted via
  `shared_preferences`. Token is **not** in `flutter_secure_storage` yet.
- `WalletService` — `ValueNotifier<Wallet>` + `online` flag, reads the economy
  numbers from the remote config (`coins_per_unit`, `min_convert_coins`,
  `min_withdraw_cents`, `currency_code`). All writes go through the API.
- `GameSessionService` — `start` on game load/reset, `end` on game over with
  `{score, merges}`, then pushes the returned wallet into `WalletService`.
- `LocalStorageService` — offline cache + device id + token only.
- `screens/wallet_screen.dart` — balances, coin→cash convert, payout request
  form (renders per-method `fields`), payout history.

Not built yet: Daily, Tasks, Live-Ops and Invite screens, `AdService` /
`google_mobile_ads`. The home shortcuts for those still show "coming soon".

**Naming trap:** the client still calls its local soft currency `stardust` /
`crystals` (see `LocalStorageService`, `AppColors.kStardustSymbol`) while the
server economy is `coins` / `cash_cents` / `gems`. They are the same two
currencies; only the server balances are authoritative. `LocalStorageService`
still claims these are "not real money" — that is stale: `cash_cents` is
withdrawable and must be described as such anywhere it is shown.

## Build phases

1. **Foundation** — migrations, models, `LedgerService`, `Config` helper,
   device auth, `/me`, `/game/session/*`, seeder. ✅
2. Daily check-in, tasks, live-ops. **API done** (`/daily`, `/daily/claim`,
   `/tasks`, `/tasks/{key}/claim` + `TaskService`); live-ops still open;
   Flutter screens not built.
3. Ad waterfall + reward + SSV; wallet convert; withdraw + methods.
   **Wallet + withdraw API done** (`/wallet/convert`, `/withdraw*`) with the
   hold/refund state machine; ads + SSV still open.
4. Filament admin (withdrawals, config, fraud). ✅ panel, resources and
   approve/pay/reject actions exist; only the fraud dashboard is missing.
5. Flutter: ApiClient + Auth + Wallet; wire game rewards. **Core done** —
   auth, wallet, game-session reporting and the wallet/withdraw screen work;
   the merge counter is now sent to the server.
6. Flutter: Daily / Tasks / LiveOps / Wallet / Invite screens. Wallet done,
   the other four are still placeholders.
7. Flutter ads: `google_mobile_ads` + `AdService` + placements. Not started.
8. Hardening: Play Integrity, rate limits, caps, tests. Rate limits + session
   caps + plausibility clamps exist; **15 feature tests** cover the economy
   flow (`tests/Feature/EconomyFlowTest.php`). Play Integrity not started.
