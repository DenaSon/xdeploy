---
name: xdeploy
description: Develop, review, debug, test, and make product or architecture decisions for the xDeploy Laravel server management platform.
---

# xDeploy

Use this skill whenever working on the xDeploy project.

xDeploy is a Laravel-based Server & Application Management Platform.

Its purpose is to make VPS purchase, connection, inspection, application installation,
management, and cloud-server lifecycle operations simple and reliable for the user,
while keeping infrastructure complexity behind clear product workflows.

---

# Source of Truth

Do not treat this skill as a replacement for the project source of truth.

Before making significant product, architecture, persistence, security, or lifecycle decisions,
inspect the relevant current implementation.

Authority order:

1. Current production-intent code
2. Automated tests
3. Current migrations
4. Controlled E2E verification when external systems are involved
5. `XDOC-001` for product, MVP, user journey, and launch decisions
6. `XDOC-117` for technical and architectural decisions
7. `XDESIGN-001` for visual and UI language
8. Historical documents

If documentation and current code disagree, investigate the difference before changing behavior.

Do not copy large amounts of volatile implementation detail into this skill.

Stable engineering rules belong here.
Current implementation detail belongs in code, tests, and the active baseline documents.

---

# Product Principles

xDeploy should make complex infrastructure feel simple.

Prefer:

- Clear product workflows
- Real state over assumed state
- Safe automation
- Progressive disclosure of technical detail
- Small reliable features
- Fast MVP iteration
- Product-friendly failure messages

Avoid:

- Turning xDeploy into a generic SSH terminal
- Generic infrastructure orchestration without a real product need
- Building abstractions for hypothetical future features
- Exposing raw infrastructure complexity to users

Before proposing a feature or major refactor ask:

> Does this materially improve the current xDeploy product or launch reliability?

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

Prefer Laravel-native solutions before custom infrastructure when Laravel already provides
a good solution.

---

# Architecture

xDeploy is a Modular Monolith with Domain-Oriented boundaries.

Primary flow:

Presentation
→ Application
→ Domain
→ Contracts
→ Infrastructure
→ External systems

Main code locations:

## `app/Application`

Owns:

- Use cases
- Actions
- Workflow orchestration
- Transaction boundaries
- Cross-domain coordination
- Queue entry points

Application code coordinates work.

It should not contain low-level provider, SSH, HTTP, shell, or filesystem implementation details.

## `app/Domain`

Owns:

- Business concepts
- Business rules
- Contracts
- DTOs
- Enums
- Value Objects
- Domain policies
- Domain exceptions
- Invariants

Do not place Laravel-specific infrastructure concerns in Domain code unless already established
by project convention.

## `app/Infrastructure`

Owns implementation details such as:

- SSH / phpseclib
- Linux commands
- Installer delivery
- Cloud Provider APIs
- Payment gateways
- SMS providers
- Remote application gateways
- External services

## `app/Models`

All Eloquent models remain here.

Do not introduce repository abstractions around Eloquent without a concrete architectural need.

## Presentation

Controllers, Livewire components, routes, and Blade should handle:

- User interaction
- UI validation
- Presentation state
- Calling application use cases
- Product-friendly messaging

Do not place business logic, SSH commands, Provider payloads, or infrastructure mutation directly
inside Livewire or Blade.

---

# Domain Boundaries

Preserve the current conceptual boundaries.

Important concepts include:

- Application
- Platform
- PublicEndpoint
- Server
- Cloud
- Billing
- Authentication
- User

Do not introduce a new Domain merely to organize files.

A new Domain requires a real distinct business concept.

---

# Application vs Platform

This distinction is fundamental.

Applications are user-facing software.

Examples:

- Marzban
- n8n
- AmneziaWG

Platforms are internal reusable infrastructure.

Examples:

- Docker
- Docker Compose
- Caddy

Invariant:

Application != Platform

An Application declares its requirements.

It does not own or duplicate installation of shared Platforms.

Example:

n8n
→ requires Docker Compose

Public exposure of n8n
→ may require Caddy

But:

n8n
!= owner of Docker
!= owner of Caddy

---

# Caddy

Caddy is a shared first-class Platform.

Invariant:

Caddy is not owned by Marzban.
Caddy is not owned by n8n.
Caddy is not an Application.

Applications that need Domain/HTTPS should use the shared PublicEndpoint and Caddy boundaries.

Do not add Application-specific Caddy installation logic.

Do not overwrite the entire Caddy configuration when the managed site abstraction can be used.

External or unknown configuration should be detected and preserved safely.

---

# PublicEndpoint

PublicEndpoint is a shared capability for Application exposure through Domain/HTTPS.

Application-specific behavior belongs behind its driver or gateway.

Typical flow:

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

AmneziaWG is not a PublicEndpoint consumer in the current architecture.

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
- AmneziaWG runtime peer telemetry
- SSH availability

Do not turn remote runtime state into authoritative database state unless the product explicitly
requires persisted workflow or product metadata.

Important invariant:

Unknown != NotInstalled

Failure to inspect a remote system does not prove absence.

Never turn an inspection error into a false `NotInstalled`, `Stopped`, or empty state.

---

# Safe Remote Operations

Treat every remote operation as unreliable.

Always consider:

- Connection failure
- Timeout
- Partial execution
- Exit code
- Permissions
- Duplicate execution
- Retry safety
- Idempotency
- Ambiguous remote state

Preferred mutation pattern:

Precondition
→ Mutation
→ Post-condition verification

Never assume success merely because a command produced output.

Prefer structured execution results including output and exit status.

After ambiguous mutation failure:

Inspect remote reality
→ reconcile state

Do not blindly repeat remote mutations.

---

# SSH

SSH state is scoped to the current request or job lifecycle.

Do not make stateful SSH connections global singletons.

Infrastructure-specific SSH logic stays behind contracts.

Sensitive commands and output must never expose secrets.

For sensitive execution:

- Hide command body
- Hide sensitive output excerpts
- Avoid logging generated credentials
- Avoid logging client configuration
- Avoid logging provider secrets

User-controlled shell input must be validated or safely escaped.

---

# Server Readiness

Operational readiness is not equivalent to simple network reachability.

Conceptually:

Reachable
+ Authenticated
+ Command-capable
+ Supported OS
+ Required privileges
  = Ready

Do not bypass established readiness checks before remote mutation.

---

# Commercial vs Operational State

Never mix these concepts:

Commercial Order State
!=
Server Operational Readiness
!=
Cloud Service Lifetime

A valid state is:

Order = Fulfilled
Server = Inactive

Provider resource delivery may succeed even when SSH is temporarily unavailable.

Do not mark a commercially fulfilled order as failed merely because xDeploy cannot currently
connect through SSH.

Likewise:

Order Expired
!=
Cloud Server Service Expired

Order history represents commercial truth.

Server status represents operational manageability.

Service lifetime represents the lifetime of a cloud resource.

---

# Cloud Operations

Cloud Provider details remain behind Cloud contracts and Infrastructure adapters.

Do not leak Provider-specific payload structures into Presentation or generic Domain code.

For billable Provider mutations such as VPS creation:

- Avoid blind retries
- Preserve provider correlation
- Recover using real provider state
- Keep operations idempotent where possible

Provider `ACTIVE` does not imply xDeploy Server `active`.

---

# Cloud Server Lifecycle

Cloud-created and manually-added Servers have different lifecycle semantics.

Manual Server deletion:

local soft delete
!=
remote VPS deletion

Cloud-created Server expiration:

Expired
→ operationally inactive
→ Provider termination
→ verify Provider terminal state
→ local lifecycle completion
→ soft delete

Provider deletion must happen before local correlation is removed.

If Provider deletion fails:

- Preserve Server record
- Preserve Provider correlation
- Persist safe failure metadata
- Allow retry or reconciliation

Provider `Not Found` may represent the desired terminal state during termination.

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

Never hold a transaction while:

- Waiting for SSH
- Calling Cloud APIs
- Calling Payment gateways
- Running installers
- Waiting for remote application operations

---

# Application Operations

Long-running Application install/uninstall operations should use the established queued operation
model.

Conceptually:

Presentation
→ ApplicationOperation
→ Queue
→ ApplicationManager
→ Requirements
→ Platform/System dependencies
→ Application mutation
→ Verification
→ terminal operation state

Do not create a second competing mechanism for the same workflow.

Do not start competing SSH runtime inspection while an active Application mutation is running.

Application workflow state may be persisted.

Application runtime truth remains remote.

---

# Queue Safety

Remote or billable mutations should not be blindly retried.

A retry is safe only when the operation semantics make it safe.

For ambiguous failures:

Inspect current state first.

Queue workers and Scheduler are production-critical parts of xDeploy.

Do not design a workflow that depends on them without considering:

- Idempotency
- Duplicate dispatch
- Worker restart
- Timeout
- Failure persistence
- Recovery

---

# Installer Delivery

Applications and Platforms should not care how installer assets are delivered.

Use the existing installer source abstraction.

Production installer rules:

- HTTPS delivery
- Pinned integrity verification
- Temporary bounded staging
- Cleanup
- Verification before execution

Never use:

`curl | sh`

Do not execute an installer before validating its integrity.

Unsupported operating systems should fail before installer execution.

---

# Security

Security-sensitive areas include:

- Server credentials
- SSH keys
- Provider tokens
- Payment secrets
- OTP codes
- Shell arguments
- VNC URLs
- VPN client configurations
- Private keys
- Preshared keys

Rules:

- Never log plaintext secrets
- Never expose secrets through public serialization
- Validate ownership before every user-owned resource operation
- Validate authorization in backend code, not only UI
- Encrypt required secret-bearing persistence
- Use no-store/private responses for secret downloads when appropriate
- Avoid unsafe shell interpolation
- Sanitize infrastructure failure messages before exposing them to users

Ownership checks must remain authoritative even if routes or UI already imply ownership.

---

# Livewire

Livewire components should primarily manage:

- UI state
- Validation
- User interaction
- Application action invocation
- Polling or deferred loading

Do not perform infrastructure work directly inside Livewire.

Prefer:

First paint from local data
→ deferred remote inspection

A page should not block its initial HTML render on SSH unless there is a strong product reason.

When an existing successful snapshot is available:

Keep it visible during refresh.

Do not replace valid stale data with empty or unknown state merely because refresh failed.

---

# UI & Product Copy

xDeploy is Persian-first and RTL-first.

For visual decisions follow `XDESIGN-001`.

Core direction:

Calm Infrastructure

Prefer:

- Minimal surfaces
- Clear hierarchy
- Semantic state
- Soft borders
- Restrained color
- Lucide icons
- Technical values in LTR
- Product-friendly Persian copy

Prefer Mary UI / DaisyUI before building custom UI primitives.

Custom components are appropriate when they encode a real reusable xDeploy pattern.

Avoid:

- Decorative complexity
- Excessive shadows
- Excessive gradients
- Neon infrastructure UI
- Too many badges
- Raw technical errors
- Fake progress
- Red for normal lifecycle completion

UI state must reflect actual backend/product semantics.

Do not invent backend capabilities through design.

---

# Errors

Distinguish:

Expected Domain / Validation Failure
Infrastructure Failure
Unexpected Programming Failure

Expected failures should become product-friendly messages.

Infrastructure details belong in logs.

Unexpected programming failures should remain observable and should not be silently converted into
fake expected states.

Never expose raw Provider or SSH exceptions directly to the user.

---

# Tests

For meaningful changes:

- Preserve existing tests
- Add focused tests for changed behavior
- Test business invariants
- Test authorization
- Test failure paths
- Test duplicate/idempotency behavior where relevant
- Test post-condition verification
- Test unsupported states
- Test remote boundaries through fakes/contracts where appropriate

Use:

Unit tests
→ isolated logic

Feature tests
→ application behavior + persistence + Laravel integration

Controlled E2E
→ real VPS / Provider / Payment / Application behavior when external reality matters

Do not replace Controlled E2E confidence with mocked tests when the feature depends on real
external behavior.

After architecture-sensitive changes:

Focused tests
→ full regression
→ Controlled E2E when external behavior changed

---

# Review Workflow

When reviewing xDeploy code:

1. Inspect the current implementation.
2. Inspect relevant tests.
3. Identify the actual problem.
4. Check the relevant Product or Technical baseline when needed.
5. Verify ownership and security boundaries.
6. Verify remote-state semantics.
7. Verify mutation and retry safety.
8. Check project conventions.
9. Recommend the smallest effective change.
10. Add or adjust tests where meaningful.
11. Mention larger refactors separately.

Do not turn every review into a refactor.

Distinguish between:

- Bug
- Security issue
- Architecture violation
- Reliability improvement
- Product improvement
- Optional cleanup

---

# Engineering Style

Prefer:

1. Simple code
2. Existing project conventions
3. Laravel-native solutions
4. Explicit behavior
5. Small focused classes
6. Strong boundaries where external side effects exist
7. Testable code
8. Production-safe operations

Avoid:

- Premature abstraction
- Generic repositories
- God interfaces
- Hidden side effects
- Large refactors without measurable value
- Generic engines before multiple real consumers exist
- Clever code that reduces readability
- Parallel architecture for an existing solved problem

Abstractions should follow real consumers, not imagined future ones.

---

# Decision Format

When multiple approaches exist, use:

**Recommended:** the preferred option

**Why:** the main architectural or product reasoning

**Trade-off:** the meaningful downside or cost

Prefer concrete recommendations over presenting many equivalent alternatives.

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

Commercial truth, operational readiness, and resource lifetime are separate concerns.

For infrastructure:

Never declare success before verification.
