# UI programme closure ledger

UI4-A, 11 November 2026. This ledger separates code delivery, Owner acceptance, engineering evidence and native release evidence.

| Boundary | Recorded state | Evidence |
|---|---|---|
| UI2 foundation / brand | Prior Owner acceptance preserved | `acceptance/2026-11-09-ui2-b-owner-acceptance.md`, `2026-11-10-ui2-c-owner-acceptance.md`, `2026-11-10-ui2-brand-owner-acceptance.md` |
| UI3-A/B integrated candidate | Owner accepted delivered B #36 including its A prerequisite | `acceptance/2026-11-11-ui3-b-owner-acceptance.md`; no per-case/device results invented |
| Main integration | #35 in main; #36 only in feature branch at inspection | UI4 first commit integrates the exact accepted B tree onto main |
| Current UI4 corrections | Implemented and engineering checked | `verification/ui4-a-defects.csv`; native re-verification pending |
| Requirements | All 46 source mappings reconciled | `requirements-register.csv`, explicit UI4 source references and M cases |
| Navigation | All 29 entries/actions reconciled | `navigation-map.csv`; mandatory password flow/contextual owner details distinguished from nonexistent standalone pages |
| Screens/designs | All 35 inventory rows mapped; all 18 specimens explained | `screen-inventory.csv`, `verification/ui4-a-design-reconciliation.csv` |
| Wording | Original 96 IDs and extended catalogue preserved | `wording-catalogue.csv`; current implementation/future exclusions retained; manual reading still Owner-owned |
| Build/content | Candidate inventory and checks recorded | `verification/ui4-a-engineering.json`, `releases/ui4-a-artifact-manifest.json` |
| Native programme gate | Pending missing observations | `verification/ui4-a-manual-results.csv`; use previous evidence where applicable |
| Deployment/rollback | Not performed here | `releases/ui4-a-release-decision.csv`; exact host/candidate/operator decision pending |
| Phase 5 readiness | Not declared complete | Full agreed UI definition of done includes final manual/release acceptance |

## What the design reconciliation means

The 18 SVGs are representative designs, not fake production screenshots. The source matrix records what implements each intent and why legitimate differences exist. No screenshot-to-browser or device parity is claimed from source inspection alone. Financial review, errors and essential next steps remain visible; supplementary history/support details can be collapsed. No mock reporting totals, Customer portal, plan checkout, standalone Owners page or general account-management service has been invented.

## How acceptance closes

1. Review the runnable UI4 branch/commit, including the integrated accepted B and focused fixes.
2. Record M01–M18 actual outcomes and device/environment details, or reference existing observations for unchanged code. A blanket package acceptance remains valid approval but does not fabricate detailed observations.
3. Resolve release blockers and verify the affected path. A new minor correction does not require repeating unrelated accepted workflows.
4. Bind the reviewed commit and artifact content SHA-256 to the actual staging/production environment and complete release/cache/rollback checks.
5. Record Owner's final UI4 decision against that concrete candidate. Do not silently turn Pending into Pass or Not applicable because a fixture/device was unavailable.

Full UI completion is declared only after this final gate. The engineering packet and candidate are delivered now; native programme/deployment evidence remains pending.
