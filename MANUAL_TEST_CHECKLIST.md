# Manual Test Checklist

Use this as defense demo script. Mark `PASS/FAIL` and add notes.

## A. Booking Submission

1. Valid booking flow (future date, valid line items) -> returns `success` with `BK-...`.
2. Submit with past date -> rejected with `reason=past_date`.
3. Submit with blocked date -> rejected with `reason=blocked_date`.
4. Submit on holiday date -> rejected with `reason=holiday`.
5. Trigger daily limit on same date -> rejected with `reason=fully_booked`.
6. Submit duplicate quickly (same payload/idempotency context) -> rejected with `reason=duplicate_submission`.
7. Missing consent -> rejected.
8. Invalid phone/email/name length -> rejected.
9. Invalid line item (qty/dimensions/product key) -> rejected.

## B. Track Booking

1. Search by booking ID only -> success.
2. Search with wrong booking ID format -> rejected.
3. Search with wrong optional email/phone formats -> rejected.
4. Search with non-existing reference -> generic not found message.
5. Live status changes in admin should appear in tracking page.
6. Stop WS server temporarily -> tracking page keeps updating via polling fallback.

## C. Admin Auth & Recovery

1. Valid login -> dashboard access.
2. Repeated wrong password -> lockout message appears.
3. CSRF missing on protected admin actions -> rejected.
4. Recovery setup (TOTP + recovery code) -> success.
5. Recovery reset with wrong TOTP or recovery code -> rejected.
6. Recovery reset success -> new recovery code returned; old code no longer works.

## D. Admin Workflow

1. Valid transition `Pending -> Site Visit` with schedule date -> success.
2. Invalid transition (e.g., `Pending -> Installation`) -> rejected.
3. Status-only update on old past preferred date -> should now succeed.
4. `Site Visit` without valid schedule date -> rejected.
5. `Installation` without valid schedule date -> rejected.
6. `Completed` without completion note -> rejected.
7. `Cancelled` or `Void` without reason category -> rejected.
8. `Completed`/`Void` record edit attempt -> rejected (locked).

## E. Calendar / Blocking

1. Block future date with no active bookings -> success.
2. Block past date -> rejected.
3. Block date with active booking -> rejected with conflict info.
4. Blocked date appears in calendar view.

## F. Notifications / Reminders

1. Status update sends expected customer email (if email exists).
2. Schedule change sends schedule-specific message.
3. Site Visit reminder endpoint sends only for today/tomorrow and only once per window key.

## G. Realtime & Fallback

1. Admin dashboard: live indicator green when WS connected.
2. Admin dashboard: when WS fails, indicator/text switches to polling fallback.
3. Product price update triggers public estimator refresh.

