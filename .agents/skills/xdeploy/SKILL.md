```md
---
name: xdeploy
description: Develop, review, debug, and make architecture/product decisions for the xDeploy Laravel server management platform.
---

# xDeploy

Use this skill whenever working on the xDeploy project.

## Product

xDeploy is a server management platform focused on making VPS operations easier.

Core responsibilities include:

- Server connection and management
- SSH operations
- Application installation and lifecycle management
- Server inspection and health information
- Domain and HTTPS management
- Infrastructure automation

The product should feel simple even when the underlying infrastructure is complex.

## Stack

- PHP 8.3+
- Laravel 13
- Livewire 4
- Tailwind CSS
- Mary UI / DaisyUI
- MySQL
- phpseclib
- Linux / SSH

## Architecture

Follow the existing modular monolith structure.

Prefer:

- `app/Application`
  - Actions
  - Use cases
  - Application orchestration

- `app/Domain`
  - Business rules
  - Domain services
  - Value objects
  - Enums

- `app/Infrastructure`
  - SSH
  - External APIs
  - Providers
  - System integrations

- `app/Models`
  - Eloquent models

Do not introduce a new architectural pattern when the current structure solves the problem.

## Engineering Principles

Always prefer:

1. Simple code
2. Existing project conventions
3. Laravel-native solutions
4. Small focused classes
5. Explicit behavior
6. Testable code
7. Production-safe operations

Avoid:

- Premature abstraction
- Large refactors without clear value
- Unnecessary interfaces
- Generic repositories around Eloquent
- Hidden side effects
- Clever code that reduces readability

## Laravel

Prefer built-in Laravel features before custom implementations.

Use:

- Actions for important application operations
- Enums for finite states
- Form Requests or Livewire validation where appropriate
- Queues for slow operations
- Scheduler for recurring operations
- Events only when decoupling provides real value
- Dependency injection for infrastructure boundaries

Keep controllers and Livewire components thin.

Business logic should not live in Blade files.

## Livewire

Livewire components should primarily handle:

- UI state
- Validation
- User interaction
- Calling application actions

Do not place SSH or infrastructure logic directly inside Livewire components.

Long-running server operations should not block the UI when they can reasonably run asynchronously.

## Server Operations

Treat every remote operation as potentially unreliable.

Consider:

- Connection failures
- Command timeout
- Partial execution
- Retry safety
- Idempotency
- Exit codes
- Permissions
- Invalid server state

Never assume an SSH command succeeded only because it returned output.

Prefer structured results containing output and exit status.

## Security

Pay special attention to:

- SSH credentials
- Passwords
- Private keys
- Tokens
- API keys
- Shell command arguments
- User-controlled input

Never log secrets.

Avoid unsafe shell interpolation.

Validate ownership and authorization before server operations.

## Application Management

Application lifecycle operations should remain predictable.

Typical lifecycle:

`inspect → install → configure → start → stop → restart → uninstall`

Operations should be as idempotent as practical.

Before installing something:

- Inspect current state
- Check requirements
- Avoid destroying existing installations
- Return useful failure information

## UI

xDeploy is Persian and RTL-first.

UI copy should be:

- Short
- Clear
- Professional
- Human
- Action-oriented

Prefer Mary UI / DaisyUI components before creating custom UI abstractions.

Keep visual hierarchy simple.

Do not add decorative complexity without improving usability.

## MVP Discipline

Before proposing a feature or refactor ask:

> Does this materially improve the current xDeploy product?

Prefer shipping a reliable small solution over building infrastructure for hypothetical future requirements.

## Tests

For meaningful changes:

- Preserve existing tests
- Add tests for business rules
- Test failure paths, not only happy paths
- Test authorization for user-owned resources
- Test infrastructure behavior at boundaries where practical

Do not rewrite large test suites unnecessarily.

## Review Workflow

When reviewing xDeploy code:

1. Understand the current implementation.
2. Identify the actual problem.
3. Check existing project conventions.
4. Find correctness or security issues.
5. Suggest the smallest effective change.
6. Consider tests.
7. Mention larger refactors separately.

Do not turn every review into a refactor.

## Decision Format

When multiple approaches exist, prefer:

**Recommended:** best option

**Why:** important reasoning

**Trade-off:** meaningful downside

Keep architectural explanations concise unless deeper analysis is requested.
```
