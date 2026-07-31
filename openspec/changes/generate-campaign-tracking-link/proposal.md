# Proposal - KAN-14: Generer un lien de tracking pour une campagne

## 1. Summary

Add an authenticated endpoint that generates a unique tracking link for an active campaign. The link contains a URL-safe code persisted in a new `tracking_links` table, and the full tracking URL is returned in a JSON `201` response. KAN-14 covers generation only; public redirect, click recording, and analytics are explicitly excluded.

## 2. Problem

Affiliates can create and manage campaigns but have no way to produce a measurable tracking URL. KAN-14 needs a link boundary that ties a unique, non-sequential code to an active campaign, enforces ownership through the existing Campaign → Offer → User chain, and rejects generation for suspended or draft campaigns.

## 3. Objectives

- Persist tracking links under existing campaigns with no duplicated ownership column.
- Restrict generation to the authenticated owner of an active campaign.
- Reject generation for draft and suspended campaigns.
- Generate a compact, URL-safe, non-sequential, unique code with a database UNIQUE constraint and bounded collision handling.
- Return the generated link and full tracking URL in a `201` JSON response.
- Keep controllers thin by using a Form Request with policy authorization, an Action, and an API Resource.
- Cover behavior and persisted state with Pest feature tests.
- Update `docs/conception-technique.md` only when KAN-14 is implemented.

## 4. In Scope

- Additive `tracking_links` migration.
- `TrackingLink` model and factory.
- `Campaign::trackingLinks()` relationship.
- `CampaignPolicy::generateTrackingLink` ability.
- `GenerateTrackingLinkRequest` for authorization and campaign-status validation.
- `GenerateTrackingLinkAction` for code generation, collision handling, and persistence.
- `TrackingLinkResource` for JSON serialization.
- Single `store` method on `CampaignController` for the generation endpoint.
- One authenticated `POST` route.
- Pest feature coverage and final technical documentation.

## 5. Out of Scope

- Public redirect endpoint (`GET /t/{code}`).
- Click recording and visit analytics.
- Conversion attribution.
- IP address collection.
- User-agent collection.
- Geolocation.
- Link expiration (`expires_at`).
- QR code generation.
- Link rotation, deactivation, or deletion.
- `is_active` column or status management on tracking links.
- Multiple-link-per-campaign management (list, revoke, replace).
- Dashboards.
- AI features.
- Frontend implementation.

## 6. Dependencies and Compatibility

- Existing `users`, `offers`, and `campaigns` tables.
- Existing `Campaign`, `CampaignPolicy`, `CampaignResource`, and `CampaignStatus` implementation from KAN-13.
- Existing Sanctum-protected `/api/v1` route group.
- Laravel policy auto-discovery and API Resource behavior.
- Pest feature-test conventions with `RefreshDatabase`.

The migration is additive. It creates only the `tracking_links` table and must not alter or drop existing data or schema.

## 7. Key Decisions

| Decision | Outcome |
|---|---|
| Ownership | Derived only through `TrackingLink → Campaign → Offer → User`; no `tracking_links.user_id` |
| Campaign deletion | `tracking_links.campaign_id` cascades on campaign deletion |
| Code generation | `Str::random(32)` — 32-character URL-safe alphanumeric string |
| Uniqueness | Database `UNIQUE` constraint on `code` plus bounded retry (max 5 attempts) on verified unique violation only |
| One vs. multiple links | A Campaign may have multiple tracking links |
| Draft Campaign | `422 Unprocessable Content` with `status` validation error |
| Suspended Campaign | `422 Unprocessable Content` with `status` validation error |
| Authorization ordering | Authorization runs before Campaign-status validation; foreign Campaign returns `403` regardless of status |
| URL construction | `url('/t/' . $code)` via Laravel URL generator — no named route (redirect not implemented) |
| Transaction | No transaction; each attempt is one atomic INSERT |
| Schema | `id`, `campaign_id`, `code`, `created_at`, `updated_at` — no `is_active`, no soft deletes |

## 8. Risks and Mitigations

| Risk | Mitigation |
|---|---|
| Code collision under load | Database UNIQUE constraint + `Str::random(32)` with 62^32 keyspace + bounded retry on verified unique violation only |
| Foreign Campaign data leaks during generation | Route model binding resolves Campaign globally; Form Request `authorize()` returns `403` before status checks |
| Suspended Campaign bypasses business rule | Authorization-first ordering ensures `403` for foreign; `422` for suspended owned Campaign |
| URL construction drift | Laravel URL generator `url('/t/' . $code)` resolves `APP_URL` automatically |
| Unrelated database errors swallowed | Action inspects SQLSTATE/driver error code; only verified unique violations trigger retry; all other exceptions rethrown |
| N+1 on listing (future) | Deferred; KAN-14 has no tracking-link listing endpoint |
| Migration conflicts | Additive `create_tracking_links_table` only; no schema alteration |

## 9. Success Criteria

- Owner generates a link for an active Campaign → `201` with tracking link data.
- Generated code is non-empty, 32 characters, URL-safe, and unique.
- Database UNIQUE constraint on `code` exists and is effective.
- Response contains the full tracking URL built with `url()`.
- Guest receives `401`.
- Foreign Campaign returns `403`.
- Authorization occurs before Campaign-status validation.
- Missing Campaign returns `404`.
- Draft Campaign is rejected with `422`.
- Suspended Campaign is rejected with `422`.
- Rejected generation creates no `tracking_links` row.
- Response does not expose `user_id` or unrelated Campaign fields.
- New tracking-link tests and the full existing suite pass.
- Formatting, route inspection, and non-destructive migration status checks pass.

## 10. Approval Gate

This package is planning only. Production implementation, migration execution, dependency installation, staging, commits, pushes, and Jira updates require explicit approval.
