# UI3-B manual verification

**Branch:** `codex/ui3-b-owner-admin-auth-20261111`. **Dependency:** UI3-A #35 (`4e560dcb295f9e89fea2fc61a5cd10bd3a46df08`). **Status:** pending Owner verification. No migration.

```bash
git fetch origin
git switch codex/ui3-b-owner-admin-auth-20261111
composer install
npm ci
npm run build
```

Open the normal XAMPP Shop address and hard reload. Use dedicated Owner, restricted Staff and Admin test accounts. Use reversible test data; do not permanently close a live business account to inspect a dialog. Native MFA/recovery requires a working development mail setup. Record commit, actual device/browser and per-case observations. UI3-A manual acceptance remains separate if not already completed.

| Case | Steps | Expected | Owner result |
|---|---|---|---|
| B01 | Windows build and actual Shop/Admin entry | All modules/CSS/icons load; no PHP/CSP errors; accepted UI3-A foundation present | Pending |
| B02 | Owner vs restricted Staff, denied direct hashes, all access modes/offline | Existing gates and allowed reads; no Owner or Admin write leakage or offline queue claim | Pending |
| B03 | Desktop/phone/tablet/rotation/keyboard, long names and 200–400% zoom | Comfortable compact forms; reachable controls; no covered fields or routine horizontal scrolling | Pending |
| B04 | Keyboard/skip links/dialogs/sheets/accordions and screen reader | Labeled controls, clear focus/current state, safe Escape/Cancel and returned focus | Pending |
| B05 | Staff list/search/Owner record | Accurate roles/status; no Staff status/reset actions on Owner | Pending |
| B06 | Add Staff, invalid/duplicate email, short password and server denial | Native/inline/summary guidance; non-secret entries retained; password cleared on failure; no duplicate submission | Pending |
| B07 | Allowed-action catalogue/search/grant/revoke/history | Same codes/grants and reasons, readable labels, history retained; reserved grants do not create future screens | Pending |
| B08 | Staff inactive/reactivate | Explicit consequence/reason, existing access/session effects and server constraints | Pending |
| B09 | Staff password reset, repeated click, close/reopen | Existing session invalidation; password shown once, no print/storage/logging; cleared after close | Pending |
| B10 | Enter temporary password then navigate away | Dirty guard where applicable; entered password cleared on leaving/unmount; no stale secret in another page | Pending |
| B11 | Shop details and timezone save/error/reload | Existing fields/server validity; native labels/errors; guarded loss on reload; read-only can reload | Pending |
| B12 | Appearance names/colours/contrast validity/long text | Existing validation; live preview and complete reload instruction; accepted brand touchpoints retained | Pending |
| B13 | Logo/icon/background PNG/JPEG upload and invalid type/dimensions | Existing normalization/security and exact 192/512 size checks; useful feedback and previews | Pending |
| B14 | Web address list/custom request/ownership check | Exact hostname/status/fallback; no unauthorized activation/TLS action in Owner UI | Pending |
| B15 | Copy-once DNS value, replace value, leave/reload | Clear copy instruction; replacement warning; leave warning and explicit acknowledgment; exact DNS values retained only as needed | Pending |
| B16 | Plan/current/upcoming/history/staff capacity/read-only | Real periods/access facts, no invented checkout, date/slot/unknown states readable | Pending |
| B17 | Starting cash: no baseline/start/count including zero | One original attempt; decimal strings; zero permitted; not a daily opening routine | Pending |
| B18 | Change count after save, commit count, review | Edited amount must be saved; correct saved amount/stages/support fields; no skipped stage | Pending |
| B19 | Finalize starting cash/cancel final dialog | Explicit final consequence; cancellation safe; saved baseline protected and no new attempt allowed | Pending |
| B20 | Abort starting-cash attempt/reason/history | Explicit decision; attempts/observations retained; next eligible attempt follows existing contract | Pending |
| B21 | Starting-cash repeated activation/lost result/status refresh | One original identity; no blind retry; current-state read/recovery; hard guard if status unavailable | Pending |
| B22 | Owner Activity/all eight filters/empty/failure/Load more | Same scope/cursor, useful states, exact references and metadata still available | Pending |
| B23 | Activity detail sheet/related records | Authorized fields only; safe text metadata; correct focus/close and no scope mixing | Pending |
| B24 | Admin grants individually absent/present | Correct sections and controls; no Shop operating impersonation; server denial still handled | Pending |
| B25 | Admin Add shop details/native errors/review/back | Original business/shop/owner fields; password hidden in review; correctable entries and clear steps | Pending |
| B26 | Admin create/duplicate click/error/completion/leave | Original payload; password cleared after submit/failure/leave; only returned identifiers/address; no false rollback claim | Pending |
| B27 | Admin Shops search and account/shop access actions | Current target/states/reasons; explicit pause/stop/reopen/permanent-close consequences; history retained | Pending |
| B28 | Admin Plans all five operations/three actual plan codes | Original payload/eligibility/date rules; clearly UTC; correct plan form’s errors and explicit review | Pending |
| B29 | Admin web addresses TLS ready/failed/activate/primary/fallback/deactivate | Same exact commands/ready boolean/reasons/grants; consequences and restrictions preserved | Pending |
| B30 | Admin Activity with/without separate support grant | Support entry absent when unauthorized; server remains authoritative; no support query triggered merely by rendering | Pending |
| B31 | Explicit support target/reason/detail/cursor/leave and late reply | Same support access ID and scoped evidence; clear Admin identity; leave clears scope and stale results cannot return | Pending |
| B32 | Native login/remembering/recovery availability | Existing email/password/CSRF/action/setting gates; busy once; same-origin routes | Pending |
| B33 | Recovery request/message for known/unknown address | Same privacy-preserving generic message and existing mail policy; no account enumeration | Pending |
| B34 | Forced password change current/new/confirm and invalid fields | Same server policy/session effects, native required/minlength aid, no lost non-secret email input or secret logging | Pending |
| B35 | MFA email request/code verify, leading zero and invalid/expired code | Correct native routes/names/CSRF; real Shield verification; numeric text entry; no new code policy | Pending |
| B36 | Email activation where enabled | Existing code/route/CSRF/server behavior; consistent layout and proper error | Pending |
| B37 | Admin step-up/session expiry/denial | Existing session restart/MFA/redirect; Admin identity; no shop-role promotion | Pending |
| B38 | Registration disabled and rate-limit response | Clear next step; public registration stays off; real 429/Retry-After values preserved | Pending |
| B39 | Native server-error summary/inline links and password visibility/BFCache | Correct input focus/ARIA; no repeated typing focus; remasked password and restored buttons on Back | Pending |
| B40 | 400/404/production error pages | Local stylesheet, helpful safe recovery, no raw exception or inline-CSS CSP issue; actual response status unchanged | Pending |
| B41 | Safe notice timers, close controls, secret output and print | Only safe messages time out; errors/security/one-time values persist; secrets excluded from print | Pending |
| B42 | UI3-A commercial regression, coherent release and rollback | Prior task identities/amounts/stock/private receipt behavior unchanged; all PHP/modules/render/CSS/icons together; rollback code/assets only | Pending |

Accept UI3-B only after reviewing actual outcomes. UI4-A remains full programme verification and release handover; it is not pre-completed by this checklist.
