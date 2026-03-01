# Validation Matrix

| Area | Field/Rule | What is validated | Why | Where implemented |
|---|---|---|---|---|
| Booking | Bot traps | Honeypot must be empty; form fill time min/max | Reduce scripted spam | `backend/process.php` |
| Booking | reCAPTCHA | Token required; server verification; score threshold | Bot resistance | `backend/process.php` |
| Booking | Rate limit | Per-IP attempts in time window | Abuse throttling | `backend/process.php` |
| Booking | Cooldown | Per-session identity submit cooldown | Prevent rapid duplicates | `backend/process.php` |
| Booking | Idempotency | Duplicate token rejected within TTL | Prevent accidental double submit | `backend/process.php` |
| Booking | Date format | `YYYY-MM-DD` strict input | Consistent scheduling logic | `backend/process.php`, `backend/workflow.php` |
| Booking | Date range | No past date; blocked dates; holiday closure | Operational correctness | `backend/process.php`, `backend/block_date.php` |
| Booking | Capacity | Max daily active bookings | Avoid overbooking | `backend/process.php` |
| Booking | Contact | Name/address length, phone digits, valid email | Data quality | `backend/process.php` |
| Booking | Line items | Max items, qty, dimensions, total qty | Prevent invalid/abusive payloads | `backend/process.php` |
| Booking | Product pricing | Variant existence/availability and server-side pricing | Prevent client tampering | `backend/process.php` |
| Contact | Required fields | Name, email, message, consent | Proper inquiry capture | `backend/contact.php` |
| Contact | Uploads | Max file count, size, extension and MIME | Upload safety | `backend/contact.php` |
| Contact | Rate/cooldown | Request throttling and session cooldown | Spam reduction | `backend/contact.php` |
| Tracking | Reference format | Booking ID regex format | Data integrity | `backend/track_booking.php` |
| Tracking | Optional verify fields | Email/phone format checked when provided | Low-friction + safer lookup | `backend/track_booking.php` |
| Admin Auth | Login lockout | Failed-attempt lock window | Brute-force defense | `backend/auth.php` |
| Admin Auth | Password policy | Reset/recovery new password min length | Better credential strength | `backend/auth.php` |
| Admin Auth | Recovery | TOTP + recovery code both required; lockout | Secure account recovery | `backend/auth.php` |
| Admin | Session + CSRF | Admin session required + CSRF token check | Request forgery protection | `backend/session.php` |
| Admin Workflow | Status enum | Only allowed statuses accepted | State integrity | `backend/update_status.php`, `backend/workflow.php` |
| Admin Workflow | Transition rules | Only valid current->next moves allowed | Workflow correctness | `backend/update_status.php`, `backend/workflow.php` |
| Admin Workflow | Terminal lock | `Completed`/`Void` immutable | Audit integrity | `backend/update_status.php`, `backend/workflow.php` |
| Admin Workflow | Schedule guardrail | `Site Visit`/`Installation` require valid schedule date | Prevent unscheduled execution state | `backend/update_status.php` |
| Admin Workflow | Date guardrail | Changed schedule cannot be in the past | Schedule validity | `backend/update_status.php` |
| Admin Workflow | Reason standards | Category required for `Cancelled`/`Void`; completion note for `Completed` | Better audit quality | `backend/update_status.php`, `admin-dashboard.php` |
| Calendar Admin | Blocked date checks | No past date; no block if active bookings exist | Prevent invalid blocks | `backend/block_date.php` |
| Realtime | Token auth | Signed WS token with scope + expiry | Realtime channel protection | `backend/ws_auth.php`, `backend/ws-server.php`, `backend/ws_token.php` |

