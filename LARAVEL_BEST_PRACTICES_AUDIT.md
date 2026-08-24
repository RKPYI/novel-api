# Laravel Best Practices Audit (Strict)

Date: 2026-04-21  
Project: `novel-api` (branch: `UKK`)

## Executive Summary

- **Total best-practice gaps found:** **15 categories**
- **Fixable incrementally (without rewriting everything):** **9 categories**
- **Hard to fix (cross-cutting / architecture-wide):** **6 categories**

> Notes:
> - This is a strict audit focused on Laravel conventions and maintainability.
> - Counts below are based on code currently in the repository.

## Measured Signals (evidence)

- Controllers: **16**
- Controllers larger than 300 LOC: **7**
- Largest controllers:
  - `app/Http/Controllers/NovelController.php` (**646 LOC**)
  - `app/Http/Controllers/ChapterController.php` (**615 LOC**)
  - `app/Http/Controllers/EditorController.php` (**531 LOC**)
- API route declarations in `routes/api.php`: **125**
- Controller methods: **97** (with explicit return types: **69**, missing return types: **28**)
- Form Requests in `app/Http/Requests`: **0**
- Inline `$request->validate(...)` in controllers: **18**
- `env()` usage outside config files: **3** (all in `AuthController`)
- Policy classes in `app/Policies`: **0**
- API Resource classes in `app/Http/Resources`: **0**
- `response()->json(...)` calls in controllers: **216**
- Manual role checks in controllers (`isAdmin`, `canCreateNovels`, `canReviewChapters`): **51**
- Test files: **2** total (`tests/Feature`: 1, `tests/Unit`: 1)
- Service classes in `app/Services`: **0**

---

## A) Fixable Incrementally (9)

These are high-value improvements that can be done step-by-step.

1. **Use Form Requests instead of inline validation**
   - Current: **18** inline validations, **0** Form Requests.
   - Why: centralizes validation/authorization and keeps controllers cleaner.

2. **Move `env()` usage out of controllers**
   - Current: **3** usages in `app/Http/Controllers/Auth/AuthController.php`.
   - Why: Laravel best practice is `env()` only in config files.

3. **Add missing return types in controller methods**
   - Current: **28** methods without explicit return types.
   - Why: improves static analysis, readability, and contract clarity.

4. **Replace broad `catch (\Exception)` with better exception strategy**
   - Current: multiple broad catches across controllers/helpers.
   - Why: prefer domain-specific exceptions + centralized rendering/reporting.

5. **Remove debug logging from hot-path model accessor**
   - Current: `User::getIsAdminAttribute()` logs debug for every serialization path.
   - Why: noisy logs + potential performance overhead.

6. **Remove dead/unused imports and minor hygiene issues**
   - Example: `App\Models\User` imported in `AdminMiddleware` but not used.
   - Why: keeps codebase clean and avoids confusion.

7. **Adopt static analysis tooling (Larastan/PHPStan)**
   - Current: not configured in `composer.json`.
   - Why: catches type and architecture issues before runtime.

8. **Strengthen automated style and quality gates in CI**
   - Current: Pint available, but no evidence of full CI gate policy in repo root.
   - Why: enforce consistency and prevent regressions.

9. **Modularize routes file for readability**
   - Current: `routes/api.php` is large (**125 route declarations**).
   - Why: split by bounded context/modules for maintainability.

---

## B) Hard to Fix (Need broad refactor) (6)

These are possible, but they touch many files and behaviors.

1. **Introduce proper Policy-based authorization across API**
   - Current: **0 policies**, **0** `authorize()/Gate` usages, many manual checks.
   - Why hard: requires creating policies + updating many endpoints and tests.

2. **Extract business logic from fat controllers into Actions/Services**
   - Current: **7** controllers > 300 LOC, heavy orchestration and domain logic in controllers.
   - Why hard: affects request flow, error handling, transactions, and tests.

3. **Adopt API Resources / Transformers for response contracts**
   - Current: **0 resources**, **216** manual JSON responses.
   - Why hard: large response-surface change; frontend contract compatibility risk.

4. **Increase test coverage to real domain/feature coverage**
   - Current: only **2** example tests.
   - Why hard: requires building fixtures/factories/scenarios for most endpoints and critical workflows.

5. **Refactor role/permission model to scalable authorization design**
   - Current: role checks spread across middleware/controllers and model helper methods.
   - Why hard: role semantics are cross-cutting (routes, middleware, policies, UI assumptions, seeders/tests).

6. **Rework cache invalidation strategy into coherent domain events/listeners**
   - Current: cache-clearing logic is spread (controllers + model hooks + helper fallback behavior).
   - Why hard: touches many flows and requires careful correctness/performance validation.

---

## Prioritized Next Steps (strict but practical)

1. **Week 1 quick wins**
   - Move `env()` access to config.
   - Create first Form Requests for auth + novel endpoints.
   - Remove debug accessor logging.
   - Add Larastan baseline.

2. **Week 2–3 architecture start**
   - Introduce API Resources for 1 module first (`Novel`).
   - Add Policies for `Novel`, `Chapter`, `Comment`.
   - Extract first Action/Service classes from `NovelController`.

3. **Week 3+ safety net**
   - Expand feature tests for auth + CRUD + authorization paths before deeper refactors.

---

## Final Answer to Your Question

- **How many things are not yet best practices?**
  - **15 categories total** (strict audit).
- **What can be fixed directly?**
  - **9 categories** in section **A**.
- **What is hard to fix and needs broad change?**
  - **6 categories** in section **B**.
