---
name: xdeploy
description: Develop, review, debug, test, and make product or architecture decisions for Coreflare, the Laravel server and application management platform in DenaSon/xdeploy.
---

# Coreflare

Use this skill whenever working on Coreflare or its legacy technical repository `DenaSon/xdeploy`.

Coreflare is a Laravel-based Server & Application Management Platform. Its purpose is to make VPS purchase, connection, inspection, application installation, management, integrations, and cloud-server lifecycle operations simple and reliable while keeping infrastructure complexity behind clear product workflows.

The technical skill identifier/path remains `xdeploy` for compatibility. Product-facing prose should use **Coreflare** unless an existing persisted/runtime/config identifier literally remains `xdeploy`.

---

# Source of Truth

Do not treat this skill as a replacement for the project source of truth.

Before significant product, architecture, persistence, security, identity, integration, or lifecycle decisions, inspect the relevant current implementation and tests.

Authority order:

1. Current production-intent code
2. Automated tests
3. Current migrations / physical schema
4. Controlled E2E verification when external systems are involved
5. `XDOC-001` for product, MVP, user journey, and launch decisions
6. `XDOC-119` for Identity, Authentication, Account Security, Admin Security, Sensitive Re-Authentication, Recovery, and authentication-security invariants
7. `XDOC-117` for general technical and architectural decisions
8. `XDESIGN-001` for visual and UI language
9. Historical documents

`XDOC-118` is specialized Livewire 4 adoption guidance and does not replace the general architecture baseline.

If documentation and current code disagree, investigate the difference before changing behavior.

Stable engineering rules belong here. Volatile implementation detail belongs in code, tests, migrations, and active baseline documents.

---

# Product Principles

Coreflare should make complex infrastructure feel simple.

Prefer:

- Clear product workflows
- Real state over assumed state
- Safe automation
- Progressive disclosure of technical detail
- Small reliable features
- Fast MVP iteration
- Product-friendly failure messages

Avoid:

- Turning Coreflare into a generic SSH terminal
- Generic infrastructure orchestration without a real product need
- Building abstractions for hypothetical future features
- Exposing raw infrastructure complexity to users

Before proposing a feature or major refactor ask:

> Does this materially improve the current Coreflare product or launch reliability?

If not, keep it out of the critical path.

---

# Stack

Primary stack:

- PHP 8.3+
- Laravel 13
- Livewire 4
- Tailwind CSS
- Mary UI
- DaisyUI
- MySQL
- phpseclib
- Linux
- SSH

Prefer Laravel-native solutions before custom infrastructure when Laravel already provides a good solution.

---

# Architecture

Coreflare is a Modular Monolith with Domain-Oriented boundaries.

Primary flow:

Presentation
→ Application
→ Domain
→ Contracts
→ Infrastructure
→ External systems

## `app/Application`

Owns use cases, actions, workflow orchestration, transaction boundaries, cross-domain coordination, queue entry points, and integration coordination.

Application code coordinates work. It should not contain low-level provider, SSH, HTTP, shell, or filesystem implementation details.

## `app/Domain`

Owns business concepts, rules, contracts, DTOs, enums, value objects, policies, exceptions, and invariants.

Do not place Laravel-specific infrastructure concerns in Domain code unless already established by project convention.

## `app/Infrastructure`

Owns implementation details such as:

- SSH / phpseclib
- Linux commands
- Installer delivery
- Cloud Provider APIs
- Payment gateways
- SMS providers
- Cloudflare / Telegram provider protocol details
- Remote application gateways
- External services

## `app/Models`

All Eloquent models remain here.

Do not introduce repository abstractions around Eloquent without a concrete architectural need.

## Presentation

Controllers, Livewire components, routes, and Blade should handle user interaction, UI validation, presentation state, calling application use cases, and product-friendly messaging.

Do not place business logic, SSH commands, provider payloads, or infrastructure mutation directly inside Livewire or Blade.

---

# Domain and Feature Boundaries

Preserve the current conceptual boundaries. Important concepts include:

- Application
- Platform
- PublicEndpoint
- Server
- Cloud
- Billing
- Authentication
- User
- Integration

`Integration` is a real implementation boundary for focused external integrations such as Cloudflare and Telegram. Do not turn it into a generic iPaaS or workflow-automation engine without multiple real consumers and a validated need.

Do not introduce a new Domain merely to organize files. A new Domain requires a distinct business concept.

---

# Application vs Platform

Applications are user-facing software, for example Marzban, n8n, and WordPress.

Platforms are internal reusable infrastructure, for example Docker, Docker Compose, and Caddy.

Invariant:

Application != Platform

An Application declares requirements. It does not own or duplicate installation of shared Platforms.

---

# Caddy and PublicEndpoint

Caddy is a shared first-class Platform. It is not owned by Marzban or n8n and is not an Application.

Applications that need Domain/HTTPS should use shared PublicEndpoint and Caddy boundaries. Do not add Application-specific Caddy installation logic or overwrite unrelated external Caddy configuration.

Typical PublicEndpoint flow:

Resolve owned Server
→ inspect Application
→ validate Domain
→ DNS/server preflight
→ ensure required Platform
→ apply endpoint
→ validate configuration
→ reload
→ inspect
→ verify final state

Do not report endpoint success before the actual post-condition is verified.

Persisted PublicEndpoint lifecycle may represent product/workflow state such as Pending, Active, and Disabled. It does **not** replace remote runtime truth. Inspect Caddy/Application reality where operational truth matters.

---

# Remote Runtime State

Remote runtime truth belongs to the system that owns the runtime.

Examples:

- Application running state
- Docker state
- Caddy state
- Linux services
- Containers
- CPU / RAM / Disk
- SSH availability

Do not turn remote runtime state into authoritative database state unless the product explicitly requires persisted workflow or product metadata.

Important invariant:

Unknown != NotInstalled

Failure to inspect a remote system does not prove absence. Never turn an inspection error into a false `NotInstalled`, `Stopped`, or empty state.

---

# Safe Remote Operations

Treat every remote operation as unreliable. Consider connection failure, timeout, partial execution, exit code, permissions, duplicate execution, retry safety, idempotency, and ambiguous remote state.

Preferred mutation pattern:

Precondition
→ Mutation
→ Post-condition verification

Never assume success merely because a command or API call returned output.

After ambiguous mutation failure:

Inspect remote reality
→ reconcile state

Do not blindly repeat remote or billable mutations.

---

# SSH

SSH state is scoped to the current request or job lifecycle. Do not make stateful SSH connections global singletons.

Infrastructure-specific SSH logic stays behind contracts.

Sensitive commands and output must never expose secrets. Hide command bodies and sensitive output excerpts when necessary; never log generated credentials, client configuration, provider secrets, or private keys.

User-controlled shell input must be validated or safely escaped.

---

# Server Readiness

Operational readiness is not equivalent to simple network reachability.

Reachable
+ Authenticated
+ Command-capable
+ Supported OS
+ Required privileges
= Ready

Do not bypass established readiness checks before remote mutation.

---

# Commercial vs Operational State

Never mix:

Commercial Order State
!= Server Operational Readiness
!= Cloud Service Lifetime

A valid state is:

Order = Fulfilled
Server = Inactive

Provider resource delivery may succeed while SSH is temporarily unavailable. Do not mark a commercially fulfilled order as failed merely because Coreflare cannot currently connect through SSH.

Order history represents commercial truth. Server status represents operational manageability. Service lifetime represents cloud-resource lifetime.

---

# Multi-Provider Cloud Operations

Cloud Provider details remain behind Cloud contracts and Infrastructure adapters. Do not leak provider-specific payload structures into Presentation or generic Domain code.

Coreflare uses provider identity, registry/capability routing, and persisted resource ownership. Keep these semantics distinct:

- `enabled = true` means a provider is operationally available for resources it owns.
- `purchase_enabled = true` means the provider may accept new purchases.
- Purchase availability must be enforced in Application/backend code, not only hidden in UI.
- Existing Server lifecycle routes through persisted provider ownership, not the current default provider or purchasable set.
- Provider capabilities may differ; do not expose unsupported operations as if all providers were identical.

For billable provider mutations such as VPS creation:

- Avoid blind retries
- Preserve provider correlation
- Recover using real provider state
- Keep operations idempotent where possible

Provider `ACTIVE` does not imply Coreflare Server `active`.

---

# Cloud Server Lifecycle

Cloud-created and manually-added Servers have different lifecycle semantics.

Manual Server deletion:

local soft delete
!= remote VPS deletion

Cloud-created Server expiration:

Expired
→ operationally inactive
→ Provider termination
→ verify Provider terminal state
→ local lifecycle completion
→ soft delete

Provider deletion must happen before local correlation is removed.

If Provider deletion fails, preserve the Server record and provider correlation, persist safe failure metadata, and allow retry/reconciliation. Provider `Not Found` may represent the desired terminal state during termination.

---

# External Integrations

Provider-specific OAuth, API, webhook, and protocol semantics stop at the Infrastructure boundary unless a stable Domain vocabulary is genuinely shared.

Current focused integrations include Cloudflare and Telegram.

Rules:

- Persist external secrets only when required.
- Encrypt secret-bearing persistence at rest.
- Hide secrets from serialization.
- Never raw-log access tokens, refresh tokens, bot/link secrets, or provider credentials.
- Keep temporary challenge/link state purpose-bound and time-bounded.
- Keep connection state, user preference state, and delivery outcome conceptually separate.
- Do not create a generic integration framework before multiple real consumers justify it.

---

# Notification Preferences

Notification preferences are persisted Product State when implemented.

Do not conflate:

Preference enabled
!= Integration connected
!= Notification delivery succeeded

A missing preference row may intentionally mean the product default; preserve the current service semantics rather than inventing a different default in UI.

---

# Support Requests and Attachments

Support Requests are a focused product capability, not a generic helpdesk platform.

Preserve tenant isolation on request, Server context, message, and attachment reads/writes.

Support attachments are deliberately bounded. Keep upload validation, normalization, ownership/admin authorization, private delivery, and no-store/nosniff behavior intact.

Do not use attachment support as justification for a generic media library or helpdesk automation subsystem.

---

# Identity and Security

Identity, Authentication, Account Security, Admin Security, Sensitive Re-Authentication, Recovery, and authentication-security invariants are canonical in `XDOC-119`.

General rules:

- Never log plaintext secrets.
- Never expose secrets through public serialization.
- Validate ownership before every user-owned resource operation.
- Validate authorization in backend code, not only UI.
- Encrypt required secret-bearing persistence.
- Use private/no-store responses for sensitive downloads where appropriate.
- Avoid unsafe shell interpolation.
- Sanitize infrastructure failure messages before exposing them to users.

Current product identity principles include Phone + OTP bootstrap/fallback and optional Passkey for returning authentication. A verified Google email is an optional verified account attribute unless XDOC-119 explicitly promotes it to a login or recovery method.

## Admin Impersonation

Controlled Admin user impersonation is a purpose-built exception, not generic identity switching.

Invariant:

- Start requires protected Admin authorization and the established Admin Passkey gate.
- Self-target and Admin-target impersonation are rejected.
- The target session does not inherit Admin privilege.
- Original Admin identity is kept only in controlled session state required for restoration.
- Identity transitions regenerate the session ID.
- Stop must verify the original account still exists and is still Admin before restoration.
- Impersonation is distinct from Support Access and must not share sensitive-grant semantics implicitly.

Do not claim durable impersonation audit history unless the implementation actually persists it.

---

# Transactions and External Side Effects

Do not keep database transactions open across slow external operations.

Preferred pattern:

Short DB transaction
→ claim or persist state
→ commit
→ external side effect
→ short DB transaction
→ persist result

Use row locks only around short critical state transitions.

Never hold a transaction while waiting for SSH, calling Cloud APIs, calling payment gateways, running installers, or waiting for remote application operations.

---

# Application Operations and Queue Safety

Long-running Application install/uninstall/lifecycle operations should use the established queued operation model. Do not create a second competing mechanism for the same workflow.

Application workflow state may be persisted. Application runtime truth remains remote.

Remote or billable mutations should not be blindly retried. For ambiguous failures, inspect current state first.

Queue workers and Scheduler are production-critical. Consider idempotency, duplicate dispatch, worker restart, timeout, failure persistence, and recovery.

---

# Installer Delivery

Applications and Platforms should not care how installer assets are delivered. Use the existing installer source abstraction.

Production installer rules:

- HTTPS delivery
- Pinned integrity verification
- Temporary bounded staging
- Cleanup
- Verification before execution

Never use `curl | sh`.

Unsupported operating systems should fail before installer execution.

---

# Reverse Proxy / HTTPS

Coreflare may run behind Cloudflare or another TLS-terminating reverse proxy.

Generated URLs and redirects must preserve the visitor's HTTPS context through correctly trusted forwarded headers.

`APP_URL=https://...` is not a substitute for correct proxy handling.

If forwarded headers are trusted broadly, infrastructure must control the proxy/origin boundary so arbitrary direct clients cannot spoof trusted forwarding context.

---

# Livewire

Livewire components should primarily manage UI state, validation, user interaction, application action invocation, polling, or deferred loading.

Do not perform infrastructure work directly inside Livewire.

Prefer:

First paint from local data
→ deferred remote inspection

When an existing successful snapshot is available, keep it visible during refresh. Do not replace valid stale data with empty/unknown state merely because refresh failed.

---

# UI & Product Copy

Coreflare is Persian-first and RTL-first. For visual decisions follow `XDESIGN-001`.

Core direction: Calm Infrastructure.

Prefer minimal surfaces, clear hierarchy, semantic state, soft borders, restrained color, Lucide icons, technical values in LTR, and product-friendly Persian copy.

Prefer Mary UI / DaisyUI before building custom UI primitives.

Avoid decorative complexity, excessive shadows/gradients, neon infrastructure UI, too many badges, raw technical errors, fake progress, or UI that invents backend capability.

Product-facing naming should use **Coreflare**. Preserve literal legacy `xdeploy` identifiers only where compatibility/migration requires them.

---

# Errors

Distinguish:

Expected Domain / Validation Failure
Infrastructure Failure
Unexpected Programming Failure

Expected failures should become product-friendly messages. Infrastructure details belong in logs. Unexpected programming failures should remain observable and should not be silently converted into fake expected states.

Never expose raw Provider or SSH exceptions directly to the user.

---

# Tests

For meaningful changes:

- Preserve existing tests
- Add focused tests for changed behavior
- Test business invariants
- Test authorization and tenant isolation
- Test failure paths
- Test duplicate/idempotency behavior where relevant
- Test post-condition verification
- Test unsupported states/capabilities
- Test remote boundaries through fakes/contracts where appropriate

Use:

Unit tests
→ isolated logic

Feature tests
→ application behavior + persistence + Laravel integration

Controlled E2E
→ real VPS / Provider / Payment / external Integration / Application behavior when external reality matters

Do not replace Controlled E2E confidence with mocked tests when the feature depends on real external behavior.

After architecture-sensitive changes:

Focused tests
→ full regression
→ Controlled E2E when external behavior changed

Do not claim CI green for a commit unless the exact relevant workflow run has been verified.

---

# Review Workflow

When reviewing Coreflare code:

1. Inspect current implementation.
2. Inspect relevant tests and migrations.
3. Identify the actual problem.
4. Check the relevant canonical baseline (`XDOC-001`, `XDOC-119`, `XDOC-117`) when needed.
5. Verify ownership and security boundaries.
6. Verify remote-state semantics.
7. Verify mutation and retry safety.
8. Verify provider/integration capability boundaries where relevant.
9. Check project conventions.
10. Recommend the smallest effective change.
11. Add or adjust tests where meaningful.
12. Mention larger refactors separately.

Do not turn every review into a refactor.

Distinguish between bug, security issue, architecture violation, reliability improvement, product improvement, and optional cleanup.

---

# Engineering Style

Prefer simple code, existing project conventions, Laravel-native solutions, explicit behavior, small focused classes, strong boundaries around external side effects, testable code, and production-safe operations.

Avoid premature abstraction, generic repositories, god interfaces, hidden side effects, large refactors without measurable value, generic engines before multiple real consumers exist, clever code that reduces readability, or parallel architecture for an already-solved problem.

Abstractions should follow real consumers, not imagined future ones.

---

# Decision Format

When multiple approaches exist, use:

**Recommended:** the preferred option

**Why:** the main architectural or product reasoning

**Trade-off:** the meaningful downside or cost

Prefer concrete recommendations over many equivalent alternatives.

---

# Final Principles

For remote systems:

Inspect reality.
Verify readiness.
Mutate safely.
Verify the result.

For architecture:

Strong enough boundaries for safe infrastructure operations.
Simple enough boundaries for fast product development.

For product scope:

Launch reliable value first.
Expand from real usage and evidence.

For state:

Commercial truth, operational readiness, persisted workflow state, and remote runtime truth are separate concerns.

For infrastructure:

Never declare success before verification.