# DawaTrack

### Bringing Clarity and Trust to Nigeria's Neighborhood Chemists

## Technical Blueprint

**Version 1.0 — September 2026**

---

# Purpose of This Blueprint

This Technical Blueprint translates the approved DawaTrack Business & Product Blueprint into a practical software architecture.

It defines **how DawaTrack should be technically structured and how its major business rules should work** before detailed database design, API specification and implementation begin.

The blueprint deliberately remains above low-level implementation details such as individual database columns, migration files, API endpoints, controller names and screen layouts.

The objective is to establish a technical foundation that is:

* understandable and maintainable by the project owner;
* appropriate for the initial 3–5 shop pilot;
* secure and properly multi-tenant;
* reliable under weak or intermittent internet connectivity;
* capable of producing trustworthy inventory, financial and reconciliation records;
* auditable without unnecessary technical complexity; and
* extensible as DawaTrack grows.

---

# Technical Goals & Engineering Principles

DawaTrack's highest technical priority is **trustworthy business data**.

The system shall prioritise:

1. **Transactional accuracy and data integrity** over sophisticated features.
2. **Traceability and accountability** for important stock, sales, cash, debt and administrative activity.
3. **Offline-capable operation** for essential shop workflows.
4. **Simple and responsive operation** on affordable Android devices and ordinary computers.
5. **Strict tenant isolation and backend-enforced authorization.**
6. **Recoverability** through appropriate backup and disaster-recovery controls.
7. **Simple, maintainable architecture** appropriate to the pilot while preserving a practical path to growth.

The engineering principles are:

* correctness before cleverness;
* business rules enforced by the backend;
* important transactions have identity and history;
* explicit business movements rather than unexplained balance manipulation;
* offline-awareness from the beginning;
* security and tenant isolation by design;
* modular monolith before distributed complexity;
* design for extension, but implement only Phase 1 requirements.

The architecture deliberately avoids premature microservices, Kubernetes, GraphQL, distributed messaging infrastructure and other complexity not justified by current requirements.

---

# 1. System Architecture

## 1.1 Technology Stack

DawaTrack will use the following stack:

| Layer                     | Technology                        |
| ------------------------- | --------------------------------- |
| Backend Language          | PHP                               |
| Backend Framework         | Latest stable CodeIgniter 4       |
| Authentication Foundation | CodeIgniter Shield                |
| Architecture              | Modular Monolith                  |
| Application Style         | API-centred SPA/PWA               |
| Frontend                  | Vue 3                             |
| Vue Approach              | Composition API using `setup()`   |
| Frontend Language         | Modern JavaScript ES6+            |
| JavaScript Build Tool     | None                              |
| TypeScript                | No                                |
| Vue Router                | No                                |
| Pinia                     | No                                |
| State Management          | Vue Composition API / Composables |
| HTTP Client               | Native Fetch API                  |
| Styling                   | Bootstrap CSS                      |
| Bootstrap Production      | Precompiled/minified static CSS   |
| Server Database           | MySQL 8.4 LTS / InnoDB            |
| Offline Database          | IndexedDB                         |
| IndexedDB Interface       | Dexie.js                          |
| PWA                       | Web App Manifest + Service Worker |
| API                       | REST-style JSON over HTTPS        |

Vue, Dexie and other production-critical frontend resources will be **self-hosted** so the PWA does not depend on public CDNs to start while offline.

Bootstrap's browser development CDN will not be used in production. Production CSS will be compiled into a normal static CSS file without introducing a JavaScript application build pipeline.

## 1.2 High-Level Architecture

```text
USER DEVICE
│
├── Vue 3 SPA/PWA
│     ├── UI Components
│     ├── Composition API / Composables
│     ├── Data/API Layer
│     ├── Dexie → IndexedDB
│     └── Service Worker / Static Cache
│
│ HTTPS / REST JSON
▼
CODEIGNITER 4
│
├── API / Request Layer
├── Authentication / Tenant / Permission Controls
├── Business / Service Layer
├── Data Access Layer
├── Audit / Scheduled Operations
│
▼
MySQL / InnoDB
```

DawaTrack will have **one application codebase, one backend and one authoritative server database**.

It will not create separate backend applications for Inventory, Sales, Admin, Subscription or Synchronization.

## 1.3 Application Areas

There will be three logical application areas:

**Authentication/Entry** — establishes authorised access.

**Shop Application** — one Vue SPA/PWA shared by Shop Owner and Staff, with functionality determined by permissions.

**Platform Administration** — logically separate Vue administration interface for DawaTrack Platform Admin.

The Shop and Platform Admin interfaces use the same CI4 backend but maintain distinct authority and session boundaries.

## 1.4 Server Layering

CodeIgniter controllers remain relatively thin.

Important business rules belong in the Business/Service Layer:

```text
API Controller
      ↓
Business Service
      ↓
Data Access
      ↓
MySQL
```

Sales, inventory, debt, reconciliation and other important rules must not be scattered through controllers.

Business-critical multi-record operations use MySQL transactions so that a logical transaction either succeeds consistently or fails consistently.

MySQL is the **canonical shared system of record**.

IndexedDB is a durable local/offline working store, not a replacement for MySQL.

---

# 2. Multi-Tenant Architecture

## 2.1 Tenant and Shop Are Different Concepts

A **Tenant** represents the subscribing business/customer account.

A **Shop** represents an operating outlet belonging to that Tenant.

Phase 1:

```text
Tenant
  └── exactly 1 Shop
```

Future multi-branch:

```text
Tenant
  ├── Shop A
  ├── Shop B
  └── Shop C
```

Multi-branch functionality is not implemented in Phase 1, but the architecture will not make it necessary to redesign fundamental ownership later.

## 2.2 Database Tenancy

DawaTrack will use:

**one MySQL database + shared tables + tenant-scoped rows.**

Tenant-owned business records will carry direct tenant ownership through a stable internal `tenant_id`, with `shop_id` additionally used where operationally relevant.

Separate databases or table sets per shop will not be used.

## 2.3 Tenant Context

The server establishes tenant authority.

The browser may send tenant/shop identifiers where technically useful, but a client-supplied `tenant_id` never proves authorization.

The server determines:

```text
Authenticated User
      ↓
Tenant Membership
      ↓
Allowed Tenant
      ↓
Tenant Context
      ↓
Permission
      ↓
Tenant-scoped Business Operation
```

Normal tenant-aware data access must make cross-tenant access difficult by default.

## 2.4 Relationship Isolation

Tenant isolation applies to relationships as well as top-level queries.

For example:

* Tenant A Sale cannot reference Tenant B Customer.
* Tenant A Stock Movement cannot use Tenant B Product.
* Tenant A Debt Payment cannot settle Tenant B Receivable.

## 2.5 Shop Addressing

Phase 1 shops use DawaTrack-controlled subdomains/slugs conceptually such as:

`shop-slug.dawatrack-domain`

The subdomain identifies shop context but does not provide authorization.

The slug is not the permanent database identity of the Tenant or Shop.

Custom domains remain deferred.

## 2.6 Platform Admin

Platform Admin operates outside normal shop tenant context using dedicated administrative services.

Platform Admin is **not a member of every Tenant**, and Platform Admin access does not globally disable tenant security.

---

# 3. Users, Roles & Permissions

## 3.1 Authentication

CodeIgniter Shield will provide the authentication foundation.

Phase 1 uses:

**Username + Password + Server Session**

for ordinary Shop users.

JWT and personal access tokens are not required for ordinary PWA operation.

## 3.2 No Public Self-Registration

Phase 1 account creation follows:

```text
Platform Admin
      ↓
Creates Tenant + Shop
      ↓
Creates Primary Owner

Primary Owner
      ↓
Creates Staff
```

## 3.3 Identity and Membership

User Identity is separate from Tenant Membership.

```text
User
 ↓
Tenant Membership
 ├── Tenant
 ├── Role
 ├── Status
 └── Permission Grants
```

Phase 1 normally allows one active Tenant membership per ordinary Shop user while retaining structural support for multiple memberships later.

## 3.4 Authority Levels

Phase 1 has three authority types:

**Platform Admin** — operates DawaTrack itself.

**Primary Shop Owner** — full normal authority within their own Tenant.

**Staff** — access determined by permissions granted by the Owner.

Each Phase 1 Tenant has one Primary Shop Owner.

## 3.5 Staff Permissions

Staff permissions use:

> **deny by default + explicit permission grants.**

Granular permissions will correspond to actual operations such as:

* recording Sales;
* viewing Inventory;
* performing Stock Adjustments;
* managing Debtors;
* recording Debt Payments;
* viewing/performing/finalizing Reconciliation;
* viewing Reports;
* managing Staff.

Sensitive correction/reversal capabilities remain separate from normal transaction-entry permissions.

Frontend permission checks control presentation only.

CI4 remains authoritative.

## 3.6 Account Lifecycle

Users and memberships with historical activity are **deactivated rather than deleted**.

Password recovery follows:

```text
Staff → Shop Owner-controlled recovery
Owner → Platform Admin-controlled recovery
```

Existing passwords are never revealed.

---

# 4. Core Data Model

The model distinguishes **current operational state** from **business events**.

Important balances must be explainable from transaction history.

## 4.1 Core Entities

| Module                       | Core Entities                                                                                                                                     |
| ---------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------- |
| Identity & Access            | User, Tenant Membership, Membership Permission Grant                                                                                              |
| Tenant & Shop                | Tenant, Shop                                                                                                                                      |
| Product Catalog              | Product                                                                                                                                           |
| Inventory                    | Stock Position, Stock Intake, Stock Intake Item, Stock Movement                                                                                   |
| Sales                        | Sale, Sale Item, Sale Payment                                                                                                                     |
| Customer & Debt              | Customer, Debt/Receivable, Debt Payment, Debt Payment Allocation, Receivable Adjustment                                                           |
| Expense & Financial Movement | Financial Movement                                                                                                                                |
| Reconciliation               | Reconciliation Session, Reconciliation Stock Count, Reconciliation Financial/Cash Count, Reconciliation Variance, Reconciliation Staff Context    |
| Subscription                 | Subscription Plan, Subscription                                                                                                                   |
| Audit                        | Audit Event                                                                                                                                       |
| Offline/Sync                 | Device Registration, Sync Operation/Outbox Entry, Sync Receipt/Idempotency Record, Sync Cursor/Change Position, Offline User Provisioning Context |

Reporting has no independent source-of-truth transactional entity.

Platform Administration does not maintain duplicate versions of Tenant, User, Subscription or other authoritative records.

## 4.2 Main Relationships

```text
USER
  ↓
TENANT MEMBERSHIP
  ↓
TENANT
  ├── SHOP
  ├── PRODUCT
  ├── CUSTOMER
  └── SUBSCRIPTION
         ↓
    SUBSCRIPTION PLAN


SHOP + PRODUCT
      ↓
STOCK POSITION
      ↑
STOCK MOVEMENTS


STOCK INTAKE
      ↓
STOCK INTAKE ITEMS
      ↓
STOCK MOVEMENTS


SALE
 ├── SALE ITEMS → PRODUCT
 ├── SALE PAYMENTS
 └── UNPAID CREDIT
          ↓
      RECEIVABLE
          ↓
 PAYMENT ALLOCATIONS
          ↑
    DEBT PAYMENT


SALE RETURN/CORRECTION
          ↓
RECEIVABLE ADJUSTMENT


INVENTORY + SALES + DEBT + FINANCIAL MOVEMENTS
                      ↓
                 RECONCILIATION


IMPORTANT ACTIONS
       ↓
   AUDIT EVENT
```

Product Catalog information is tenant-level.

Operational stock and business transactions are shop-level where appropriate.

---

# 5. Inventory Architecture

DawaTrack will use a **movement-ledger inventory architecture**.

## 5.1 Stock Position

`Stock Position` represents DawaTrack's **current system-expected quantity** for a Product at a Shop.

It is not simply overwritten during physical counting.

## 5.2 Stock Movement

Every accountable stock increase or decrease creates a Stock Movement.

Phase 1 movement categories are:

* Opening Stock;
* Stock Intake;
* Sale;
* Customer Return;
* Damaged;
* Expired;
* Owner Withdrawal/Internal Use;
* acknowledged Missing/Loss;
* controlled Correction/Adjustment;
* Reversal/Compensating Movement;
* Reconciliation Adjustment.

## 5.3 Expected Stock

Conceptually:

```text
Expected Stock
=
Opening Stock
+ Stock Intake
+ Valid Customer Returns
+ Positive Corrections

- Sales
- Damaged
- Expired
- Owner Withdrawals
- Known Loss
- Negative Corrections
```

## 5.4 Opening Stock

Existing shops establish accountable Opening Stock during onboarding rather than receiving unexplained starting balances.

Opening Stock also establishes an appropriate initial cost basis.

## 5.5 Stock Intake

Stock Intake uses a:

```text
Draft → Posted
```

lifecycle.

A posted Intake produces Stock Movements and updates Stock Positions atomically.

Posted transactions are not silently rewritten.

## 5.6 Product Unit

Each Product uses one consistent base stock/sale unit in Phase 1.

Automatic carton → box → strip → tablet conversion is not implemented.

## 5.7 Physical Count

Physical counting does not directly modify Stock Position.

```text
Expected = 100
Actual   = 93
Variance = -7
```

The variance remains evidence.

An authorised post-Reconciliation Adjustment may later align operational Stock Position.

## 5.8 Negative Stock

Negative Stock Position is permitted where legitimate accepted transactions produce it, particularly because concurrent/offline Sales may occur.

Negative stock is an exceptional state and is prominently flagged for investigation.

---

# 6. Sales, Payments & Profit Logic

## 6.1 Sale

A Sale represents the commercial event of selling products.

```text
Cart / In Progress
        ↓
Confirm
        ↓
Completed / Posted Sale
```

Once posted, the Sale becomes protected historical evidence.

Business completion status and synchronization status remain separate.

## 6.2 Sale Items

Sale total derives from Sale Items.

Each Sale Item snapshots:

* quantity;
* unit selling price;
* applicable unit-cost basis.

Changing the Product's current price/cost later does not rewrite historical Sales.

## 6.3 Payment Channels

Phase 1 received-payment channels are:

**Cash**

and

**Transfer**

Credit is **not a payment method**.

Example:

```text
Sale Total      ₦20,000

Cash             ₦5,000
Transfer         ₦5,000
Credit          ₦10,000
```

creates:

```text
Sale Payments = ₦10,000
Receivable     = ₦10,000
```

Mixed payments are supported.

## 6.4 Credit and Debtors

Every credit Sale must belong to an identified Customer.

Each unpaid Sale portion creates a Sale-linked Receivable.

Customer balance is derived rather than directly editable.

```text
Customer Outstanding
=
Sum of outstanding Receivables
```

Debt Payments support Cash and Transfer.

They reduce Receivables but do not create new Sales Revenue or Gross Profit.

## 6.5 Debt Payment Allocation

One Debt Payment may settle multiple Receivables.

General repayments default to **oldest outstanding debt first**, while explicit allocation to a specific Receivable is permitted where appropriate.

Debt Payment cannot exceed legitimate total outstanding debt.

Phase 1 does not introduce a customer wallet/advance balance.

## 6.6 Debt Status

Settlement state is derived:

* Outstanding;
* Partially Paid;
* Settled.

Due date is optional.

A positive balance becomes Overdue only when an established due date has passed.

There is:

* no interest;
* no penalty;
* no late fee;
* no automatic debt growth.

## 6.7 Receivable Adjustments

Sale returns/corrections that reduce a Receivable use traceable Receivable Adjustments rather than pretending money was received.

Adjustments do not become unrestricted debt-write-off mechanisms.

## 6.8 Financial Movements

Legitimate non-sale money movements include:

* Shop Expense;
* Owner Cash Withdrawal;
* Owner Cash Introduction / Cash Float;
* controlled Financial Correction.

Every relevant movement identifies its Cash/Transfer channel.

This remains deliberately narrower than full accounting/general-ledger functionality.

## 6.9 Costing

DawaTrack will use **Moving Weighted Average Cost**.

```text
Current Quantity × Current Average Cost
+
Incoming Quantity × Incoming Unit Cost
────────────────────────────────────────
New Total Quantity

= New Weighted Average Cost
```

Normal Sales reduce quantity without recalculating average unit cost.

When expected quantity is zero/negative, DawaTrack retains the last valid cost basis for outgoing transaction costing. Later Stock Intake restoring positive inventory re-establishes the practical cost basis without negative-quantity averaging.

## 6.10 Gross Profit

Phase 1 reports **Gross Profit**, not formal Net Profit.

```text
Gross Profit
=
Net Sales
-
Historical Cost of Goods Sold
```

Sale Items preserve the historical cost used for the calculation.

Recorded Expenses are reported separately.

Credit Sales contribute to Sales and Gross Profit when the goods are sold.

Later Debt collection is collection of an existing Receivable, not new Revenue.

---

# 7. Reconciliation Engine

Reconciliation is DawaTrack's principal accountability engine.

It is a **period-based evidence process**.

## 7.1 Reconciliation Scopes

Phase 1 supports:

* Cash Reconciliation;
* Stock Reconciliation;
* Full Reconciliation.

## 7.2 Core Formula

```text
Variance = Actual − Expected
```

Therefore:

* negative = shortage;
* positive = surplus;
* zero = balanced.

## 7.3 Lifecycle

```text
Start
 ↓
Counting
 ↓
Counts Committed
 ↓
Review / Variance
 ↓
Finalized
```

Meaningful incomplete sessions may be retained as Aborted rather than deleted.

## 7.4 Cutoff

Starting authoritative reconciliation establishes a fixed server-controlled cutoff.

Relevant operations capable of changing the reconciliation scope are temporarily restricted until the session is completed or safely aborted.

Finalization requires connectivity and an authoritative synchronized state.

## 7.5 Stock Reconciliation

Expected quantity comes from Stock Position at cutoff.

Actual quantity comes from physical count.

Counts are **blind initially**: expected quantity is hidden until the physical count is committed.

Example:

```text
Expected = 51
Actual   = 48
Variance = -3
```

Finalization preserves all three values permanently.

Optional Reconciliation Adjustment may subsequently align operational Stock Position without erasing the historical variance.

## 7.6 Cash Reconciliation

Expected physical cash is:

```text
Opening Cash Baseline

+ Cash Sale Payments
+ Cash Debt Payments
+ Owner Cash Introduction / Float
+ legitimate positive cash corrections

- Cash Sale Refunds
- Cash Expenses
- Owner Cash Withdrawals
- legitimate negative cash corrections
```

Transfer and Credit do not affect physical cash.

The first control period establishes an Opening Physical Cash Baseline.

Thereafter, Actual Cash from the previous finalized reconciliation becomes the next opening physical baseline.

Previous shortages/surpluses remain preserved.

## 7.7 Reconciliation Evidence

A finalized reconciliation preserves:

* reconciliation period/cutoff;
* expected values;
* actual observations;
* variances;
* responsible/on-duty Staff context;
* users who performed relevant transactions;
* explanations;
* subsequent linked adjustments.

An explanation never erases a variance.

## 7.8 Staff Accountability

DawaTrack records evidence and responsibility context.

A variance **does not automatically prove misconduct by a particular Staff member**.

Reconciliation capabilities can separately control:

* viewing;
* counting;
* reviewing;
* finalizing;
* authorising adjustments.

---

# 8. Offline-First & Synchronization Architecture

DawaTrack will synchronize **business operations**, not database tables.

## 8.1 Local Architecture

IndexedDB/Dexie contains:

### Server-derived local state

* Product Catalog;
* relevant Stock Positions;
* Customers/Receivables required offline;
* Shop configuration;
* last-known capability/entitlement state.

### Local unsynchronized activity

Stored in an Outbox.

```text
Server Base State
      +
Pending Local Operations
      =
Local Working State
```

Refreshing server data must never erase unresolved local operations.

## 8.2 Outbox

Offline-capable operations are durably stored before synchronization.

Conceptual states:

* Pending;
* Syncing;
* Synced;
* Needs Attention.

Network failure keeps an operation Pending.

Business conflict moves it to Needs Attention.

## 8.3 Operation Identity and Idempotency

Every business synchronization operation receives a globally unique stable operation ID.

Retries reuse the same ID.

CI4 maintains persistent idempotency evidence so:

```text
same Operation ID
        ↓
processed once only
```

A timeout/retry must never duplicate a Sale or Payment.

## 8.4 Offline-Created Resources

Offline-created Customers and similar resources receive stable client correlation identifiers so dependent operations can reference them before server IDs exist.

Dependent operations synchronize in causal order.

## 8.5 Sync Cursor

Devices download authoritative changes through server-issued change positions/cursors rather than comparing device timestamps.

Device time is contextual evidence only.

Server acceptance/order remains authoritative.

## 8.6 Offline-Capable Phase 1 Operations

Core supported offline workflows include:

* viewing synchronized Product/Stock data;
* Cash/Transfer/Credit Sales;
* Customer creation required for credit;
* Debt Payments;
* Stock Intake;
* damaged/expired/withdrawal inventory activity;
* legitimate Financial Movements;
* continuing already-authoritatively-started Reconciliation counts if connectivity drops.

Security-sensitive administration remains online-controlled.

## 8.7 Conflict Rules

DawaTrack does not use generic last-write-wins for business transactions.

Example:

Two offline Sales may legitimately push stock negative and are preserved.

Two Debt Payments attempting to settle the same remaining ₦5,000 cannot silently over-settle the Receivable. The conflicting operation becomes Needs Attention.

Conflicted business evidence is never silently discarded.

## 8.8 Registered Devices

Operational PWA/browser installations are Registered Devices.

Device state supports:

* tenant/shop binding;
* revocation;
* synchronization state;
* reconciliation readiness.

## 8.9 Offline User Continuity

Previously server-provisioned users on a trusted shared device may use an individual device-local PIN for offline unlock.

The server password is never stored locally.

Offline identity remains tied to the actual responsible user.

Pending operations survive logout and user switching.

## 8.10 Reconciliation and Devices

Reconciliation readiness considers registered active devices.

Available devices synchronize before cutoff.

An Owner may explicitly mark an unavailable device as excluded/unavailable, with Audit evidence.

Late operations from a device that missed the cutoff cannot rewrite finalized reconciliation history.

---

# 9. Audit & Immutability Architecture

DawaTrack uses practical **append-and-correct immutability**.

## 9.1 Record Categories

### Draft / Uncommitted

Can be edited/discarded before becoming authoritative.

### Mutable Operational / Master Data

Examples:

* Product current name/price;
* Customer details;
* Staff permissions;
* Shop profile.

These may change, with important changes audited.

### Posted / Finalized Business Evidence

Examples:

* Sale;
* Sale Payment;
* Stock Movement;
* Debt Payment;
* Receivable Adjustment;
* Financial Movement;
* finalized Reconciliation.

These cannot be silently edited/deleted.

## 9.2 Correction Model

```text
Original Transaction
        ↓
Linked Reversal / Compensating Action
        ↓
Replacement Transaction if required
```

Posted history is corrected by **new history**, not rewritten history.

Sensitive corrections require permission and a reason.

## 9.3 Audit Event

Audit Event records accountability, not duplicate business truth.

It preserves enough information to establish:

* actor/system source;
* tenant/shop context;
* action;
* affected resource;
* time;
* reason where relevant;
* important before/after information for mutable data;
* causal relationships.

## 9.4 What Is Audited

Important examples include:

* transaction posting/reversal;
* Stock Adjustments;
* financial corrections;
* Debt Payment reversal;
* Receivable Adjustment;
* Reconciliation finalization;
* Reconciliation corrections/adjustments;
* Staff creation/deactivation;
* permission changes;
* Owner recovery;
* tenant suspension/reactivation;
* subscription administration;
* sensitive Platform Admin actions.

Ordinary screen views are not permanent business Audit Events by default.

## 9.5 Protection

Neither Shop Owner nor Platform Admin can modify/delete committed Audit Events through normal application functionality.

Platform Admin does not receive silent tenant-history rewrite capability.

No blockchain or distributed ledger is required.

---

# 10. Platform Admin Architecture

Platform Administration is an **online-only separate interface** within the same CI4 application.

## 10.1 Phase 1 Platform Role

Phase 1 has one conceptual Platform Admin role.

The underlying administrative actions remain explicit and auditable rather than implementing unrestricted `allow everything`.

## 10.2 Platform Dashboard

Focuses on operation of DawaTrack itself:

* registered Tenants/Shops;
* active Tenants;
* Trial/Active/Expired subscriptions;
* suspended Tenants;
* platform users;
* devices;
* synchronization health;
* subscription expiries.

It is not a cross-tenant surveillance dashboard for customers' Sales, cash, debts or profit.

## 10.3 Onboarding

New Tenant onboarding coordinates:

```text
Create Tenant
      ↓
Create Shop
      ↓
Assign slug/subdomain
      ↓
Create/identify User
      ↓
Primary Owner Membership
      ↓
Create Trial/Subscription
      ↓
Activate
```

Platform Administration orchestrates the authoritative services rather than maintaining duplicate Admin records.

## 10.4 Administration Capabilities

Platform Admin can manage:

* Tenant registration;
* Shop platform identity/configuration;
* Primary Owner creation/recovery/reassignment;
* subscription/trial administration;
* tenant suspension/reactivation;
* registered device security;
* synchronization diagnostics;
* platform operational dashboard;
* Platform Admin audit history.

Day-to-day Staff permission management remains the Shop Owner's responsibility.

## 10.5 Support Context

There is no silent Shop Owner impersonation in Phase 1.

Support access to tenant business information requires:

* explicit Tenant selection;
* reason;
* Audit Event.

Support access is read-only/diagnostic by default.

Platform Admin cannot act as ordinary Shop personnel by recording Sales, adjusting Stock, settling Debts or manipulating Reconciliation.

---

# 11. Security, Backup & Recovery

## 11.1 Transport and Application Security

Production operates over HTTPS only.

Authentication/session controls include:

* CodeIgniter Shield;
* secure adaptive password hashing;
* authentication rate limiting;
* Secure/HttpOnly/SameSite host-scoped cookies;
* CSRF protection;
* controlled same-origin API access;
* production error hiding;
* parameterized database operations;
* environment-managed secrets.

Tenant isolation and backend authorization remain mandatory.

## 11.2 Password and Session Policy

Ordinary users:

* minimum 10-character password;
* approximately 2-hour inactivity timeout;
* approximately 12-hour absolute session.

Platform Admin:

* stronger minimum password requirement;
* mandatory second factor;
* approximately 30-minute inactivity timeout;
* approximately 8-hour absolute session.

Sensitive administrative actions may require recent reauthentication.

## 11.3 Device Security

Offline device-local unlock uses individual PINs:

* minimum 6 digits;
* device-specific;
* not the server password;
* securely derived verifier rather than plaintext;
* failed-attempt throttling.

Trusted offline operational authorization is valid for **up to approximately 72 hours** after successful server validation.

After expiry:

```text
existing local data → readable
pending legitimate operations → preserved
new offline operations → blocked
reconnection → required
```

Lost/revoked devices are rejected on next server contact.

## 11.4 Data Protection

DawaTrack follows:

* data minimization;
* purpose limitation;
* tenant privacy boundaries;
* controlled support access;
* restricted logging of sensitive information.

Passwords, offline PINs, session secrets and unnecessary personal-data payloads are not written to logs.

## 11.5 Data Export and Closure

Authorised Shop Owners can obtain appropriate tenant-scoped machine-readable exports.

Closed Tenants enter retained/inactive state rather than immediate deletion.

The retention mechanism is configurable so DawaTrack can apply its documented contractual/legal/data-protection retention policy without destroying required transactional integrity.

## 11.6 Backups

MySQL backups include all information required to reconstruct business and synchronization truth, including:

* business data;
* Audit Events;
* Devices;
* synchronization/idempotency records.

Phase 1 baseline:

**Database backup:** at least every 6 hours.

**Daily recovery points:** approximately 30 days.

**Weekly recovery points:** approximately 12 weeks.

At least one current backup generation is maintained **off the production server/account failure boundary** and encrypted at rest.

## 11.7 Restore Testing

Backup restoration is tested at least monthly.

Restore verification includes:

* database restoration;
* tenant data integrity;
* Audit history;
* synchronization/idempotency information;
* successful application startup.

## 11.8 Recovery Targets

Initial technical targets:

**RPO:** approximately 6 hours where no better point-in-time recovery exists.

**RTO:** restoration of core service within one business day, preferably sooner.

The infrastructure may exceed these targets as DawaTrack grows.

## 11.9 Disaster Recovery and Devices

Server restoration changes a synchronization generation/epoch where necessary.

Devices detect the recovery boundary and perform a controlled refresh while preserving unresolved local operations.

Unsynchronized device-only transactions cannot be centrally backed up until they reach the server, so DawaTrack synchronizes aggressively whenever connectivity exists.

---

# 12. Subscription & Access Control

Tenant Operational Status, Subscription Status and Effective Access Mode remain separate.

## 12.1 Tenant Status

* Onboarding;
* Active;
* Suspended;
* Closed.

## 12.2 Subscription Status

* Trial;
* Active;
* Expired.

## 12.3 Access Modes

```text
Tenant ACTIVE
+ Trial/Active Subscription
= FULL ACCESS
```

```text
Tenant ACTIVE
+ Expired Subscription
= RESTRICTED / READ-ONLY
```

```text
Tenant SUSPENDED
= BLOCKED
```

```text
Tenant CLOSED
= NO NORMAL OPERATIONAL ACCESS
```

## 12.4 Trial

Trial begins when the Shop is actually activated for use.

Duration:

**14–30 days**, selected by Platform Admin within the approved business model.

Trial exposes the full Phase 1 feature set with:

**1 Primary Owner + up to 5 active Staff.**

## 12.5 Paid Plans

### Starter

* 1 Tenant;
* 1 Shop;
* 1 Primary Owner;
* up to 2 active Staff.

### Growth

* 1 Tenant;
* 1 Shop;
* 1 Primary Owner;
* up to 5 active Staff.

Custom/Multi-Branch remains deferred.

## 12.6 Expiry

Expired subscriptions retain read-only access to appropriate existing:

* Products/Stock;
* Sales;
* Customers/Debts;
* Reports;
* Reconciliation history;
* Audit information;
* export capability.

New ordinary business transactions are blocked.

Expiry does not delete data.

## 12.7 Offline Entitlement

Devices may operate offline only within their last trusted server-established entitlement.

Operations legitimately created before entitlement expiry remain eligible for synchronization after expiry.

New post-expiry business operations cannot be authorised merely using manipulated client timestamps.

## 12.8 Renewal

Phase 1 does not require automatic card billing.

Platform Admin records confirmed renewal through the Subscription Module.

Renewal preserves:

* plan;
* effective period;
* relevant external reference where appropriate;
* responsible Admin;
* Audit Event.

If a shop renews after being expired, the new period normally starts from the new effective renewal date.

## 12.9 Upgrade/Downgrade

Upgrade may take effect immediately.

Downgrade cannot become effective while the Tenant exceeds the new plan's active Staff limit.

The system never randomly deletes/deactivates Staff to satisfy a downgrade.

---

# 13. Technical Module Structure

DawaTrack contains **13 logical modules** inside one CI4 modular monolith.

| #  | Module                       | Responsibility                                     |
| -- | ---------------------------- | -------------------------------------------------- |
| 1  | Identity & Access            | Authentication, Users, Memberships, permissions    |
| 2  | Tenant & Shop                | Tenant/Shop identity and configuration             |
| 3  | Product Catalog              | Product master information                         |
| 4  | Inventory                    | Stock Position and accountable Stock Movements     |
| 5  | Sales                        | Sales, Sale Items and payment classification       |
| 6  | Customer & Debt              | Customers, Receivables, repayments and allocations |
| 7  | Expense & Financial Movement | Legitimate non-sale financial movements            |
| 8  | Reconciliation               | Expected vs actual stock/cash and variances        |
| 9  | Reporting                    | Derived Phase 1 summaries/reports                  |
| 10 | Subscription                 | Trial, plans and entitlement lifecycle             |
| 11 | Platform Administration      | SaaS management workflows                          |
| 12 | Audit                        | Cross-cutting accountability history               |
| 13 | Offline & Synchronization    | Local persistence and server synchronization       |

## 13.1 Module Rules

### One owner per business concept

Sales owns Sales.

Inventory owns Stock Movements.

Debt owns Receivables.

Reconciliation owns Reconciliation evidence.

### Writes pass through the owning module

Sales cannot directly update inventory quantities.

It requests an Inventory operation.

### Controlled cross-module reads are allowed

Reconciliation and Reporting may read authoritative records from other modules.

No unnecessary internal microservice APIs/message brokers are required.

### No circular ownership

No two modules maintain competing versions of the same truth.

### Logical boundaries only

All modules remain:

```text
One CI4 codebase
+
One deployment
+
One MySQL database
```

---

# 14. Infrastructure & Deployment

## 14.1 Environment Structure

DawaTrack will maintain three environments:

### Development

Developer's local environment for implementation.

Uses non-production configuration and local/test data.

### Staging

Isolated deployment for deployment validation and controlled review before production.

It must have its own:

* application configuration;
* database;
* secrets;
* subdomain/environment.

Production customer data should not be casually copied into Staging.

### Production

Live DawaTrack customer environment.

Production uses production-only secrets, HTTPS, backups, restricted debugging and operational monitoring.

## 14.2 Initial Hosting

Phase 1 may begin on **reputable managed/cPanel-style shared hosting** consistent with the original commercial roadmap, provided the provider supports the required architecture.

The host must provide:

* a PHP version supported by the selected stable CI4 release;
* required PHP extensions;
* MySQL 8.4 LTS/InnoDB or the approved production database target;
* HTTPS certificates;
* wildcard/subdomain support;
* cron/scheduled tasks;
* Composer/dependency deployment capability;
* SSH or controlled deployment access where possible;
* adequate database/storage limits;
* reliable off-host backup capability.

A provider that cannot satisfy the security, database, cron, wildcard-domain or backup requirements is not considered suitable merely because it is inexpensive.

## 14.3 Web/Application Structure

The web server exposes only CodeIgniter's intended public web root.

Application configuration, `.env`, writable runtime information and sensitive files remain outside direct public access.

Production-critical frontend dependencies are served locally.

## 14.4 DNS and HTTPS

Phase 1 infrastructure supports DawaTrack-controlled shop subdomains.

Wildcard DNS/TLS configuration will be used where supported to avoid manually configuring certificates for every individual shop.

Platform Administration uses a distinct administration subdomain/context.

## 14.5 Scheduled Processing

Phase 1 uses:

**Operating-system/hosting Cron → CI4 CLI/Spark commands → Business Services**

for server-required scheduled work such as:

* subscription state evaluation;
* scheduled housekeeping;
* backup orchestration/checks;
* other approved recurring server operations.

No Redis worker infrastructure, Kafka or distributed job-processing system is required initially.

## 14.6 Source Control and Deployment

Application source is maintained in Git.

Production secrets are not committed.

Deployment follows a controlled sequence:

```text
Develop
  ↓
Commit / Review
  ↓
Staging
  ↓
Validate
  ↓
Pre-deployment Production Backup
  ↓
Database Migration where required
  ↓
Application Deployment
  ↓
Post-deployment Verification
```

Production database changes are performed through controlled versioned migrations rather than manual undocumented schema changes.

## 14.7 PWA Deployment Safety

Because devices may remain offline while a new DawaTrack version is deployed:

* PWA/static assets require explicit cache versioning;
* Service Worker updates must not abruptly destroy pending local data;
* IndexedDB schema changes require versioned migration;
* synchronization protocol changes must preserve compatibility with legitimate pending operations from supported older clients;
* a deployment must never require users to clear browser storage as the normal upgrade mechanism.

## 14.8 Logging and Monitoring

Phase 1 monitors at minimum:

* application errors;
* failed authentication patterns;
* authorization failures;
* synchronization failures/conflicts;
* backup success/failure;
* scheduled-process failures;
* infrastructure storage/resource pressure.

Business Audit and technical/server logs remain different concerns.

## 14.9 VPS Migration

The Business Blueprint already plans VPS migration during the Local Scale stage.

The technical architecture therefore remains portable from shared hosting to VPS.

Planned migration should occur during the **50–100+ shop/local-scale period**, or earlier if any of these occur:

* shared-hosting performance becomes inconsistent;
* cron/background processing becomes unreliable;
* wildcard/subdomain control is inadequate;
* database resource limits interfere with normal operation;
* backup/recovery requirements cannot be satisfied;
* monitoring/security controls require greater server access;
* synchronization volume exceeds practical shared-hosting capacity.

The VPS migration should not require redesigning application modules or tenant architecture.

---

# 15. Technical Development Roadmap

This section defines **technical implementation order and dependencies**, not the commercial rollout roadmap and not the later detailed implementation plan.

The primary rule is:

> **Foundational data integrity, tenancy and business services must exist before UI breadth is prioritized. Offline support must influence transaction design from the beginning even though the full synchronization engine is implemented after the core server business rules are stable.**

## Stage 1 — Application Foundation

Establish:

* CI4 project foundation;
* environment configuration;
* Vue application shell;
* Bootstrap static styling pipeline;
* REST response conventions;
* MySQL connection/migrations;
* base service architecture;
* application logging;
* PWA manifest/service-worker foundation;
* transaction/operation identity conventions;
* common timestamps and auditing conventions.

**Dependency:** None.

---

## Stage 2 — Identity, Tenancy & Authorization Foundation

Implement:

* CodeIgniter Shield;
* User identity;
* Tenant;
* Shop;
* Tenant Membership;
* Primary Owner;
* Staff;
* granular permission framework;
* tenant-context resolution;
* tenant-scoped data-access pattern;
* session security;
* base Platform Admin identity.

At the end of this stage, DawaTrack must reliably answer:

> Who is this user?

> Which Tenant are they allowed to operate?

> What are they permitted to do?

**Dependency:** Stage 1.

---

## Stage 3 — Audit & Transaction Foundation

Implement the reusable architecture for:

* Audit Events;
* actor/tenant/shop context;
* draft versus posted transaction behaviour;
* immutable/compensating transaction conventions;
* operation/correlation identifiers;
* server-side transaction boundaries.

This occurs early because Inventory, Sales, Debt and Reconciliation depend on it.

**Dependency:** Stages 1–2.

---

## Stage 4 — Product Catalog & Inventory

Implement:

* Product;
* Stock Position;
* Opening Stock;
* Stock Intake;
* Stock Intake Items;
* Stock Movements;
* stock adjustments;
* damaged/expired/withdrawal movements;
* low/negative-stock handling;
* Moving Weighted Average Cost foundation.

No other module may bypass Inventory when changing stock.

**Dependency:** Stages 1–3.

---

## Stage 5 — Sales, Payments, Debt & Financial Movements

Implement as one coordinated business layer:

* Sale;
* Sale Items;
* Cash/Transfer Payments;
* Credit Sales;
* Customer;
* Receivable;
* Debt Payment;
* Debt Payment Allocation;
* Receivable Adjustment;
* Financial Movement;
* Customer returns;
* transaction reversal/correction behaviour;
* cost snapshots;
* Gross Profit calculations.

Sales and Inventory effects must be transactional.

**Dependency:** Inventory and Audit foundation.

---

## Stage 6 — Reconciliation Engine

Implement:

* Stock Reconciliation;
* Cash Reconciliation;
* Full Reconciliation;
* opening cash baseline;
* physical counts;
* blind count workflow;
* expected values;
* variance calculation;
* staff context;
* finalization;
* Reconciliation Adjustments;
* protected reconciliation history.

Reconciliation must consume authoritative records from Stages 4–5 rather than maintaining independent transaction truth.

**Dependency:** Inventory, Sales, Debt, Financial Movement and Audit.

---

## Stage 7 — Offline Persistence & Synchronization

Complete the full offline architecture:

* Dexie/IndexedDB schema;
* local base state;
* Outbox;
* operation IDs;
* idempotency receipts;
* client correlation IDs;
* synchronization cursors;
* dependency-aware processing;
* conflict handling;
* Registered Devices;
* offline user provisioning/PIN;
* tenant-bound local data;
* reconciliation device readiness/cutoff coordination;
* late-operation handling.

Although implemented here, all earlier transaction services must already have been designed to support stable operation IDs and offline synchronization.

**Dependency:** Stable authoritative server business services.

---

## Stage 8 — Subscription & Platform Administration

Complete:

* Platform Admin interface;
* coordinated Tenant onboarding;
* Primary Owner administration;
* Trial;
* Starter/Growth plan entitlement;
* expiry/read-only control;
* renewal;
* upgrade/downgrade;
* Tenant suspension/reactivation;
* device/security administration;
* support context;
* platform operational dashboard.

**Dependency:** Identity/Tenant foundation and stable business access control.

---

## Stage 9 — Reporting

Implement derived:

* daily Sales summary;
* monthly Sales summary;
* Gross Profit summary;
* Stock summaries;
* outstanding Debtor summaries;
* Reconciliation/variance summaries;
* approved dashboard information.

Reporting must read authoritative records rather than creating an independent transactional truth.

**Dependency:** Core business modules.

---

## Stage 10 — Production Security, Recovery & Deployment Hardening

Before live pilot operation, complete and verify:

* production HTTPS;
* Shield security configuration;
* Platform Admin second factor;
* authentication rate limits;
* CSRF/session hardening;
* tenant-isolation verification;
* production error handling;
* device/offline authorization controls;
* automated backups;
* off-host backup;
* restore test;
* logging/monitoring;
* Staging/Production deployment procedure;
* Service Worker/cache-update procedure;
* synchronization recovery procedure;
* Tenant export/retention mechanism.

Security is considered during every previous stage; this final stage verifies and hardens the integrated production system rather than attempting to “add security at the end.”

---

## Stage 11 — Pilot Readiness Validation

Before onboarding the first real shops, validate the complete operational flow:

```text
Tenant onboarding
      ↓
Owner / Staff
      ↓
Products / Opening Stock
      ↓
Stock Intake
      ↓
Cash / Transfer / Credit Sale
      ↓
Debt Collection
      ↓
Expenses / Withdrawals / Float
      ↓
Offline Transactions
      ↓
Multi-device Synchronization
      ↓
Stock & Cash Reconciliation
      ↓
Variance / Audit Evidence
      ↓
Subscription Lifecycle
      ↓
Backup / Restore
```

The pilot should begin only when the architecture can preserve transactional integrity across these flows.

This validation stage is **not the commercial pilot itself**. It is the technical readiness gate before real customer onboarding.

---

# Deferred Phase 1 Capabilities

Consistent with the approved Business & Product Blueprint, the following remain deliberately deferred:

* multi-branch operation;
* Supplier management;
* Purchase Order management;
* advanced analytics;
* forecasting;
* barcode scanning;
* automated card subscription billing;
* bank-account reconciliation;
* full accounting/general ledger;
* customer wallet/advance balance;
* payroll;
* native Android/iOS applications;
* custom domains;
* complex automatic package/unit conversion;
* enterprise distributed infrastructure.

The architecture may permit future extension, but Phase 1 implementation must not prematurely build these capabilities.

---

# Final Technical Position

DawaTrack Phase 1 will be a **CodeIgniter 4 modular-monolith, multi-tenant, API-centred Vue 3 PWA with MySQL as the authoritative server database and Dexie/IndexedDB providing durable offline operation**.

The architecture is built around four fundamental ideas:

**Every Tenant is isolated.**

**Every important business movement has an explanation.**

**Offline activity is preserved and synchronized without bypassing server business rules.**

**Protected history is corrected through new auditable history rather than silently rewritten.**

Inventory is movement-led.

Sales distinguish commercial activity from actual payment.

Credit creates traceable Receivables.

Gross Profit uses historical cost snapshots based on Moving Weighted Average Cost.

Reconciliation independently compares expected business positions with physical reality.

Audit preserves accountability.

Platform Administration manages DawaTrack without becoming a hidden Shop Owner.

Subscription expiry restricts future use without holding historical business data hostage.

Backups, synchronization and deployment are designed so the system can recover without sacrificing transactional integrity.

The implementation should therefore proceed from **identity, tenancy, transactional and audit foundations toward business modules, reconciliation and synchronization**, rather than beginning with disconnected UI screens.

This Technical Blueprint becomes the architectural source of truth for the next stage: **detailed database/schema design and the strategic implementation roadmap.**
