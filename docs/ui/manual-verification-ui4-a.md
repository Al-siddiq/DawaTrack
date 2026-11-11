# UI4-A final manual programme gate

> **Current status, 11 November 2026:** Owner accepted the UI programme and reported merge/rollout complete. [Final decision](acceptance/2026-11-11-ui-programme-owner-closure.md) and [closure ledger](programme-closure.md) supersede pre-review Pending wording below. Historical engineering limits and unreported individual results remain unchanged.

**Branch:** `codex/ui4-a-verification-handover-20261111`. **Status:** native results and final acceptance pending. No migration. The branch includes accepted UI3-B missing from main.

```bash
git fetch origin
git switch codex/ui4-a-verification-handover-20261111
composer install
npm ci
npm run build
```

Use your normal XAMPP Shop URL. Admin entry is `/platform-admin/login`; workspace is `/platform-admin`; current security check is `/platform-admin/mfa`. Do not use the 4173 component gallery as proof of the actual application. See [fixtures and uncertain-response instructions](verification/ui4-a-fixtures.md).

Record actual results in `verification/ui4-a-manual-results.csv`. Reuse prior evidence for the same unchanged code/path/device; do not rerun all 40 A and 42 B cases merely because a handover file changed. The new direct-print, collapsed-field and cache/release paths need focused verification. Keep record IDs and amounts but redact passwords, tokens, private contacts and one-time setup values from evidence.

| Gate | Concrete verification | Expected | Related package cases |
|---|---|---|---|
| M01 | Traverse all permitted Shop and Admin destinations, Stock subtask, saved receipt links and contextual actions in navigation-map.csv | Correct title/group/back path; no dead/future destinations; mandatory password route is not a fabricated account page | C05–C11; A21/A31/A38; B24–B31 |
| M02 | Reader/Cashier/Returns Staff vs Owner, including other-shop record hash and denied writes | Actual grants and server scope; no Owner/private fields or unauthorized command | A04/A06/A22/A32; B02/B07 |
| M03 | Admin entry, MFA, each granted section and support/non-support account | Separate Admin identity; no selling/stock impersonation; explicit support target/reason | B24–B31/B35/B37 |
| M04 | Actual phone portrait/landscape/keyboard; 320/360/390 widths; tablet 768/1024; desktop 1280+; 200/400% zoom | No covered input/action or routine horizontal task scroll; readable labels and safe navigation | C02–C04/C24; A02; B03 |
| M05 | Network panel after normal build/reload; inspect icon requests | Local fingerprinted SVG/icons with labels; no font/CDN; no missing removed legacy module | C01/C26; A01; B01 |
| M06 | Slow reads and a safe test write; repeated activation | Correct local busy labels, stable width, no duplicate submit and no fabricated percent | A13/A18/A28; B06/B21/B26 |
| M07 | Invalid fields, then click summary link with field inside collapsed details/accordion | Container opens before field focus; useful inline error; valid entries stay; typing does not steal focus | A07/A10/A27; B06/B11/B39 |
| M08 | Keyboard/Escape/Cancel through Menu, stock sheet, error correction and confirmation | One modal at a time, contained/returned focus, explicit unsaved loss, background unavailable | C12–C16; A03/A14/A29; B04 |
| M09 | Reduced motion + screen reader; safe notice timeout/hover/focus/close; unknown/security/one-time message | Reduced decorative motion; meaningful announcement; only safe notices time out; important facts remain | A39; B41 |
| M10 | Saved draft/return/receipt direct hash, reload, Back/Forward, leave and resume | Original identity; no automatic new record; correct saved vs unsaved state; no hash loop/double prompt | C09–C12; A21/A29/A38 |
| M11 | Existing accepted Phase 4 cash/transfer/mixed/unpaid/partial and stock cases | Same exact server amounts/stock/evidence; no floats/new balance logic | A12/A25–A28/A40; Phase 4 WP3 checklist |
| M12 | Debt-first return, refund split/cap/quantity denial; eligible cancellation/replacement and dependency denial | Original prices/limits/reasons/linked inverse history retained | A34–A38; Phase 4 WP4-B/C/D checklists |
| M13 | Lost result before save and after commit using fixture guide | Persistent unknown; same ID status read; no new operation/retry inferred from timeout | A18/A28/A36; B21/B26 |
| M14 | Change test user's grant/access mode through supported test operations, return to old tab | Restricted navigation/actions and server denial handled safely; no stale authority claim | C07–C08; A04; B02/B24 |
| M15 | Owner/private Staff receipt; print button AND browser Ctrl+P; both pilot paper sizes | No cost/profit/internal stock/private contact leak; reference/products/payments retained; no clipped content | A32–A33; B41 |
| M16 | Login/remember/recovery/password change/code/activation/rate-limit/denial/sign-out and BFCache | Original routes/CSRF/policy/session behavior; no enumeration/secret persistence; native errors linked | B32–B40 |
| M17 | Owner Staff/settings/appearance/images/domain copy-once/plan/Activity; Admin account/plan/domain/onboarding/support | Every existing function retained; real scopes, UTC and one-time values; no checkout or future service | B05–B31; source/design matrices |
| M18 | Stage complete release, inspect live headers/CSP/cache, refresh old tab, rehearse complete code rollback | Coherent PHP/JS/CSS/icons; mutable assets revalidated; no sensitive SW cache; records/identities survive | A40/B42; release-handover.md |

Use real Windows Chrome/Edge/XAMPP, affordable Android Chrome, a tablet/intermediate viewport and the second supported browser; include Safari/iOS if supported by the intended deployment. A resized desktop is not phone acceptance. Record actual versions rather than copying a suggested list as evidence.

Release blockers include duplicate/data-integrity effects, authority/privacy leakage, inaccessible/unusable essential actions, unrecoverable lost edits or broken mobile navigation. Source fixes are not closed native defects until the affected path is verified. Complete the release decision only for the exact reviewed commit/content hash and actual environment.
