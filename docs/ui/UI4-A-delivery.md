# UI4-A — Programme verification and release handover

**Date:** 11 November 2026 (Africa/Lagos). **Branch:** `codex/ui4-a-verification-handover-20261111`. **Base main:** `3a290f38bc047fe9eedea0a3626e093e07a3c491`. **Integrated accepted B:** `3e13f2f1db7d86c1b2be00cbda2915113d5c521f`. **UI4 acceptance / deployment:** pending.

## Delivered work

The first commit integrates accepted UI3-B, which #36 merged into the UI3-A feature branch rather than main. Its tree is exactly the accepted B tree `8289a3729862ecf33178572a841172148291d902`. The following commit delivers this verification/handover and fixes. This PR targets **main directly**. It does not assume a closed stacked PR reached main, and it does not restore unrelated reverted Phase 4 documentation.

Source review found and corrected:
- Browser-native Print could expose internal cost/profit or stock sections even though the receipt button stripped those nodes. Shared print CSS now hides them on both paths. Native print re-verification remains M15.
- Error-summary links did not reveal targets inside collapsed native details/shared accordions. Links now reveal owned containers before focusing, with a host-adapter check. Native keyboard/screen-reader re-verification remains M07/M08.
- Unused legacy Auth placeholder modules and the unreferenced compiler-enabled Vue bundle remained in the shipped assets. They are removed; active Shop/Admin use runtime-only Vue, Auth stays native. Vendor licence notices are now copied with runtime assets.
- Stable-path CSS/JS had no repository-level revalidation rule for Apache. Optional mod_headers rules now request `no-cache`; actual host/CDN headers and atomic deployment remain M18. Service Worker/business-data cache behavior is unchanged.

The handover includes all 46 requirement mappings, 29 destination/action mappings, 35 inventory rows including 18 design reconciliations, original COPY-001–096 dispositions and the extended copy catalogue, an evidence/defect register, safe fixture/lost-response instructions, 18 final programme gates, release decision record, deterministic build inventory and deployment/rollback runbook. Review [programme closure](programme-closure.md), [manual verification](manual-verification-ui4-a.md) and [release handover](release-handover.md).

## Engineering evidence

Fresh `npm ci`, production frontend build and reference build completed in the available Linux/Node environment. The icon build reports 34 symbols, 17,008 raw / 5,102 gzip bytes within the existing budget. Source/public parity, JavaScript syntax/import graph, PHP grammar, default-token contrast, repeat-build identity and release inventory are recorded in `verification/ui4-a-engineering.json` and the artifact manifest. These are engineering observations, not native acceptance.

The actual UI3-A handlers were exercised again with mocked responses: receipt filtering and single-post/same-ID status recovery. Owner/starting-cash/Admin/Activity host rendering was checked at this candidate, including the corrected hidden-field reveal path. No committed test framework/suite is introduced.

Native Chromium startup fails with `socket() failed: Operation not permitted (1)`. PHP is not installed here. There is no connected XAMPP/MySQL/mail/hosting environment, actual Android/Windows/Safari device evidence or deployment/rollback rehearsal. No screenshot, HTTP/security, database, accessibility or production pass is invented. Owner's UI3-B acceptance is recorded separately and is not erased by these environment limitations.

## Release state

This is a concrete review/release candidate with engineering handover, **not a claim that the final native programme gate has passed**. Reuse valid prior manual evidence rather than repeating unaffected cases; complete the missing observations and focused fix/cache checks in M01–M18. Until that evidence and exact candidate/environment decision are recorded, production go/no-go and Phase 5 entry remain pending. No merge, deployment or database migration is performed by this delivery.
