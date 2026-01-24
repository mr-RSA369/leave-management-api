# ASSUMPTIONS AND DESIGN DECISIONS

**Project:** Leave Management API  
**Document Version:** 1.0  
**Date:** January 15, 2026  
**Technology Stack:** Laravel 10.x, MySQL, Laravel Sanctum

---

## 1. BUSINESS RULES & ASSUMPTIONS

### 1.1 User Roles & Hierarchy

**Decision:** Three-tier role system (Admin, HR, General)

**Assumptions:**
- Users are assigned a single role (no multiple roles per user)
- Role assignment is done during registration or by administrators
- Role hierarchy is strictly enforced: General → HR → Admin
- Users cannot change their own roles

**Rationale:** Simple role-based access control is sufficient for the scope of this project. Complex role permissions would be over-engineering.

---

### 1.2 Leave Approval Workflow

**Decision:** Hierarchical approval chain with auto-approval for Admin

**Assumptions:**
- **General users** submit leave requests that require **HR approval**
- **HR users** submit leave requests that require **Admin approval**
- **Admin users** get **auto-approved** (no approval needed)
- Once approved/rejected, a leave request cannot be modified
- No leave cancellation feature (out of scope)
- No re-submission of rejected leaves (must create new request)

**Rationale:** This creates a clear chain of command and prevents circular dependencies. Auto-approval for Admin is a business decision to prevent deadlock (Admin has no superior).

---

### 1.3 Leave Types & Day Calculation

**Decision:** Three leave types - Full Day (1.0), Half Day (0.5), Multi-Day (calculated)

**Assumptions:**
- **Full Day:** Exactly 1.0 day, start_date = end_date
- **Half Day:** Exactly 0.5 day, requires `half_day_period` (first_half/second_half)
- **Multi-Day:** Calculated as `(end_date - start_date) + 1` calendar days
- **Calendar days** are used for calculation (weekends/holidays counted)
- Half-day periods cannot span across multiple days
- No fractional days except 0.5 for half-day

**Rationale:** Assignment states "business days only" but implementing weekend/holiday exclusion requires:
1. Holiday calendar configuration (not in scope)
2. Regional holiday differences (complex)
3. Weekend definition varies by country

**Trade-off:** Using calendar days keeps the system simple and predictable. In production, a holiday management module would be required.

---

### 1.4 Leave Balance & Entitlement

**Decision:** Fixed 30-day annual entitlement with real-time calculation

**Assumptions:**
- All users get **30 days** annual leave entitlement (no proration)
- No distinction between leave types (all deduct from same balance)
- Only **approved** leaves deduct from balance
- **Pending** leaves are tracked separately (not deducted)
- **Rejected** leaves do not affect balance
- No carry-forward to next year (annual cycle not defined)
- No negative balance allowed (validation enforced)
- Balance is calculated on-the-fly (no caching)

**Rationale:** Real-time calculation ensures accuracy. Fixed entitlement simplifies the MVP scope.

---

### 1.5 Date & Time Constraints

**Decision:** Date-based leave system with future-only requests

**Assumptions:**
- Leave requests can only be for **today or future dates**
- Past dates are rejected by validation
- No time-of-day tracking (only dates)
- Start date must be ≤ end date
- For full_day and half_day, end_date defaults to start_date
- No maximum advance booking limit

**Rationale:** Prevents retroactive leave requests which would complicate payroll integration.

---

## 2. VALIDATION RULES

### 2.1 Leave Request Validation

**Implemented Validations:**
1. **Reason length:** Minimum 10 characters, maximum 1000 characters
2. **Date validation:** Start date must be today or future
3. **End date validation:** For multi_day, end_date must be after start_date
4. **Half-day period:** Required for half_day leave type
5. **Overlapping leaves:** Cannot have overlapping approved/pending leaves
6. **Duplicate detection:** Cannot request same date twice (any type)
7. **Balance check:** Must have sufficient remaining balance

**Assumptions:**
- Two half-day leaves on the same day are considered overlapping (rejected)
- Overlapping check considers both pending and approved leaves
- Balance validation happens before submission (not at approval time)

---

### 2.2 Approval/Rejection Validation

**Implemented Validations:**
1. **Authorization:** Only HR/Admin can approve/reject
2. **Hierarchy check:** HR can only approve General, Admin can only approve HR
3. **Status check:** Can only approve/reject pending leaves
4. **Rejection reason:** Minimum 10 characters, maximum 500 characters required

**Assumptions:**
- Cannot approve own leave request (enforced by hierarchy)
- Cannot re-process already approved/rejected leaves
- Rejection reason is mandatory (business policy)

---

## 3. SECURITY DECISIONS

### 3.1 Authentication

**Decision:** Laravel Sanctum with Bearer token authentication

**Assumptions:**
- Token-based authentication (no session-based)
- Single token per login (old tokens revoked on new login)
- No token expiration implemented (infinite validity)
- No refresh token mechanism
- HTTPS is required in production (not enforced in code)

**Rationale:** Sanctum is Laravel's recommended solution for API authentication. Simple and sufficient for this scope.

---

### 3.2 Authorization

**Decision:** Custom middleware for role-based access control

**Assumptions:**
- Role is stored as enum in database ('admin', 'hr', 'general')
- Middleware checks role on protected routes
- No fine-grained permissions (role is sufficient)
- Cannot escalate privileges without database modification

**Rationale:** Simple RBAC meets all requirements without complexity of permission systems like Spatie.

---

### 3.3 Password Security

**Decision:** Bcrypt hashing via Laravel defaults

**Assumptions:**
- Passwords are hashed using bcrypt (Laravel default)
- Minimum password length: 8 characters
- Password confirmation required on registration
- No password complexity rules (uppercase, numbers, symbols)
- No password reset functionality (out of scope)

---

## 4. API DESIGN DECISIONS

### 4.1 RESTful Principles

**Endpoints Design:**
```
POST   /api/auth/register
POST   /api/auth/login
POST   /api/auth/logout
GET    /api/auth/me

GET    /api/leave-requests
POST   /api/leave-requests
GET    /api/leave-requests/{id}
POST   /api/leave-requests/{id}/approve
POST   /api/leave-requests/{id}/reject

GET    /api/leave-balance
GET    /api/leave-balance/all
```

**Assumptions:**
- POST for create/actions (login, approve, reject)
- GET for read operations
- No PUT/PATCH (no update functionality)
- No DELETE (no cancellation)
- Consistent `/api` prefix

**Rationale:** Standard REST conventions for predictability. Actions (approve/reject) use POST on sub-resources.

---

### 4.2 Response Format

**Decision:** Consistent JSON structure

**Standard Response:**
```json
{
  "success": true|false,
  "message": "Human-readable message",
  "data": { ... } | null,
  "errors": { ... } // Only on validation errors
}
```

**HTTP Status Codes:**
- 200: Success (GET, POST actions)
- 201: Created (POST new resources)
- 400: Bad Request (business logic errors)
- 401: Unauthenticated
- 403: Unauthorized (insufficient permissions)
- 404: Not Found
- 422: Validation Error

**Assumptions:**
- Always return JSON (never HTML)
- `success` flag for programmatic checking
- `message` for UI display
- `data` contains response payload
- Errors follow Laravel validation structure

---

### 4.3 Pagination

**Decision:** Laravel's default pagination

**Assumptions:**
- Default 15 items per page
- Accepts `per_page` query parameter (max configurable in code)
- Accepts `page` query parameter
- Returns standard Laravel pagination meta (current_page, total, etc.)
- Applied only on list endpoints (GET /api/leave-requests)

---

## 5. DATABASE DESIGN DECISIONS

### 5.1 Schema Design

**Key Tables:**
- `users`: Core user data with role and entitlement
- `leave_requests`: All leave request data including approval status
- `personal_access_tokens`: Sanctum tokens (managed by package)

**Assumptions:**
- Soft deletes **not implemented** (permanent deletion)
- No audit trail table (change history not tracked)
- No separate approvals table (denormalized into leave_requests)
- Foreign key constraints with cascade delete on users
- Indexes on frequently queried columns (user_id, status, dates)

---

### 5.2 Data Types

**Decisions:**
- Dates stored as `DATE` (not DATETIME)
- `days_count` as `DECIMAL(5,1)` (max 9999.9 days, 0.5 precision)
- `status` and `role` as `ENUM` (not separate lookup tables)
- `reason` and `rejection_reason` as `TEXT` (no length limit at DB level)

**Rationale:** ENUMs are performant for fixed sets. TEXT allows flexibility despite validation limits.

---

## 6. KNOWN LIMITATIONS

### 6.1 Out of Scope (Per Assignment)

✅ **NOT IMPLEMENTED (Intentional):**
- Email/SMS notifications
- Frontend/Dashboard
- Document attachments
- Leave cancellation
- Leave withdrawal
- Manager assignment
- Department/team structure
- Public holidays configuration
- Weekend exclusion
- Proration of leave entitlement
- Leave carry forward
- Multiple approval steps
- Delegation of authority
- Reports/Analytics
- Export functionality

---

### 6.2 Technical Limitations

**Current Implementation:**
1. **No caching:** All calculations are real-time (can be slow at scale)
2. **No job queues:** All operations are synchronous
3. **No rate limiting:** No protection against API abuse
4. **No API versioning:** Breaking changes would affect all clients
5. **No CORS configuration:** Assumes same-origin or pre-configured CORS

**Rationale:** These are performance/production concerns beyond MVP scope.

---

### 6.3 Business Logic Gaps

**Potential Production Requirements:**
1. **Overlapping dates across different users:** Not checked (could cause staffing issues)
2. **Blackout periods:** Cannot block certain dates (e.g., year-end)
3. **Minimum notice period:** Can request leave for today (no 24-hour rule)
4. **Maximum consecutive days:** No limit on how many days in a row
5. **Leave approval deadline:** Pending leaves can stay pending forever

---

## 7. TESTING STRATEGY

### 7.1 Test Coverage

**Implemented Tests:**
- **Feature Tests:** Full API endpoint testing with authentication
- **Unit Tests:** Model methods and business logic
- **Test Database:** In-memory SQLite for speed

**Assumptions:**
- Tests use `RefreshDatabase` trait (fresh DB each test)
- Factories for test data generation
- No browser tests (no frontend)
- No integration tests with external services

---

### 7.2 Test Scenarios Covered

✅ **Authentication:** Registration, login, logout, validation errors  
✅ **Leave Requests:** Create (all types), list, show, filters, pagination  
✅ **Approvals:** Role-based approval hierarchy, rejection with reasons  
✅ **Authorization:** Role-based access control for all endpoints  
✅ **Validation:** All input validation rules, edge cases  
✅ **Leave Balance:** Calculation accuracy, different leave types  
✅ **Model Methods:** All helper methods in User and LeaveRequest models

---

## 8. DEPLOYMENT ASSUMPTIONS

### 8.1 Environment Requirements

**Assumed Production Setup:**
- PHP 8.1+
- MySQL 5.7+ or 8.0+
- HTTPS enabled (SSL certificate)
- Environment variables configured (`.env`)
- Composer dependencies installed
- Database migrations run
- API documentation published (Swagger UI)

### 8.2 Not Configured

**Requires Manual Setup:**
- Web server configuration (Nginx/Apache)
- SSL/TLS certificates
- Firewall rules
- Database backups
- Log rotation
- Monitoring/alerting
- CDN for static assets

---

## 9. FUTURE ENHANCEMENTS (Not in Scope)

If this project were to be extended:

1. **Email Notifications:** On request submission, approval, rejection
2. **Manager Hierarchy:** Direct manager approval before HR
3. **Leave Categories:** Sick, vacation, personal, etc. with separate quotas
4. **Half-day timing:** Actual hours (9am-1pm, 1pm-5pm)
5. **Public Holidays API:** Integration with holiday calendar services
6. **Team Calendar:** View team availability
7. **Reporting:** Analytics dashboard for HR/Admin
8. **Audit Logs:** Track all changes to leave requests
9. **Mobile App:** Native iOS/Android clients
10. **Two-factor Authentication:** Enhanced security

---

## 10. CONTACT & CLARIFICATIONS

**Developer Notes:**
- This document reflects decisions made during implementation
- All assumptions are based on assignment requirements interpretation
- Ambiguous requirements were resolved using industry best practices
- Open to modifications based on stakeholder feedback

**Key Principle:** When in doubt, chose simplicity over complexity for MVP delivery.

---

**Document End**
