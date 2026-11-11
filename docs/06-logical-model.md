# 06 — Logical Database Model: FFBS

**Project:** FFBS — Football Field Booking System  
**Purpose:** Define the logical relational database model, entity relationships, primary and foreign keys, cardinalities, integrity constraints, and normalization through Third Normal Form (3NF).

---

## 1. Purpose and scope

### 1.1. Objective

The objective of this stage is to transform the FFBS domain model into a logical relational model. The design identifies tables, columns, primary keys (PK), foreign keys (FK), unique constraints, relationship cardinalities, and normalization rules.

### 1.2. System overview

FFBS is a web application for finding and booking football fields. Customers browse fields, inspect their details, choose a time interval, and submit booking requests. Owners manage only their own field listings and schedules. Administrators manage platform-wide users and listings.

The proposed logical model contains these core entities:

- users and their platform roles;
- football fields and their photos;
- recurring availability schedules;
- bookings and their lifecycle statuses;
- payment transactions;
- customer reviews;
- user notifications.

### 1.3. From UML class model to relational model

The design has three levels:

1. **Conceptual level:** UML class model from the structure-design stage. It describes domain concepts and their relationships.
2. **Logical level:** relational model described in this document. It defines tables, attributes, PK/FK relationships, cardinalities, and normalization independently of a specific DBMS.
3. **Physical level:** SQL DDL for the selected DBMS, including exact data types, indexes, storage engine, and implementation-specific constraints.

Mapping rules:

- Entity/class → relational table (for example, `User` → `users`).
- Attribute → column.
- Object/instance → row.
- Entity identity → primary key, represented here by a surrogate `id`.
- One-to-many (1:N) relationship → foreign key on the many side.
- Many-to-many (M:N) relationship → junction table. In this model, a separate junction table is not required for the core booking flow because each booking links one customer to one field and one time interval.
- One-to-one (1:1) relationship → foreign key with a `UNIQUE` constraint where applicable.

---

## 2. Initial unnormalized table (UNF)

Before a structured database is introduced, booking information might be kept in a spreadsheet where customer, field, owner, booking, and payment details are placed in a single row.

The following sample `booking_flat` table uses fictional data.

### Table 2.1. Initial unnormalized booking table

| Booking ID | Customer details | Field and owner details | Booking interval and status | Payment details |
|---|---|---|---|---|
| 5001 | Farhod Rahimov; farhod@example.tj; +992900111222 | Arena 1; Khujand; Owner: Karim Saidov; +992900333444 | 2026-11-15 18:00–19:00; CONFIRMED | 120 TJS; CARD; PAID; TX-1001 |
| 5002 | Madina Nazarova; madina@example.tj; +992900555666 | Arena 1; Khujand; Owner: Karim Saidov; +992900333444 | 2026-11-15 19:00–20:00; PENDING | 120 TJS; CASH; PENDING |
| 5003 | Farhod Rahimov; farhod@example.tj; +992900111222 | City Football; Khujand; Owner: Salim Niyozov; +992900777888 | 2026-11-16 17:00–18:30; COMPLETED | 180 TJS; CARD; PAID; TX-1002 |

### Problems in the UNF table

1. **Non-atomic values:** customer details, owner details, booking interval, and payment details are grouped into compound cells.
2. **Repeated data:** the same customer, owner, and field information is repeated across bookings.
3. **Update anomaly:** changing an owner's phone number requires updating every row containing that owner.
4. **Insertion anomaly:** a new field or owner cannot be recorded naturally until a booking row exists.
5. **Deletion anomaly:** deleting the only booking for a field may accidentally remove the only stored copy of that field's details.

---

## 3. Step-by-step normalization: 1NF to 3NF

### 3.1. First Normal Form (1NF)

A table is in First Normal Form when:

1. Every column contains one atomic value.
2. There are no repeating groups or arrays stored in a single cell.
3. Each row can be uniquely identified by a key.

#### Changes required for 1NF

- Split customer name, email, and phone into separate columns.
- Split field name, address, owner name, and owner phone into separate columns.
- Split booking start and end into separate values.
- Split payment amount, method, status, and transaction reference into separate columns.
- Store one booking and one payment record per row in the flattened example.

### Table 3.1. Resulting 1NF relation: `booking_payment_1nf`

| booking_id | payment_id | customer_name | customer_email | customer_phone | field_name | field_address | owner_name | owner_phone | start_at | end_at | booking_status | amount | payment_method | payment_status |
|---:|---:|---|---|---|---|---|---|---|---|---|---|---:|---|---|
| 5001 | 9001 | Farhod Rahimov | farhod@example.tj | +992900111222 | Arena 1 | Khujand | Karim Saidov | +992900333444 | 2026-11-15 18:00 | 2026-11-15 19:00 | CONFIRMED | 120 | CARD | PAID |
| 5002 | 9002 | Madina Nazarova | madina@example.tj | +992900555666 | Arena 1 | Khujand | Karim Saidov | +992900333444 | 2026-11-15 19:00 | 2026-11-15 20:00 | PENDING | 120 | CASH | PENDING |
| 5003 | 9003 | Farhod Rahimov | farhod@example.tj | +992900111222 | City Football | Khujand | Salim Niyozov | +992900777888 | 2026-11-16 17:00 | 2026-11-16 18:30 | COMPLETED | 180 | CARD | PAID |

For this illustrative relation, `(booking_id, payment_id)` is a composite candidate key. In a production design, a booking can have zero, one, or multiple payment attempts, so the final model uses independent surrogate primary keys and a foreign key from `payments` to `bookings`.

**1NF result:** values are atomic, but customer, owner, and field attributes are still repeated. The relation is not yet fully normalized.

### 3.2. Second Normal Form (2NF)

A relation is in Second Normal Form when:

1. It is already in 1NF.
2. Every non-key attribute is fully functionally dependent on the whole candidate key, not only on part of a composite key.

For the illustrative composite key `(booking_id, payment_id)`, the relevant dependencies include:

- `booking_id → customer_id, field_id, start_at, end_at, booking_status`
- `payment_id → booking_id, amount, payment_method, payment_status`
- `customer_id → customer_name, customer_email, customer_phone`
- `field_id → field_name, field_address, owner_id`
- `owner_id → owner_name, owner_phone`

Customer and field details describe independent entities rather than the booking-payment pair. Payment details depend on the payment identifier. These facts should be stored separately.

#### Solution

Separate the data into entity-focused relations:

1. `users` — customer and owner account information.
2. `fields` — football field information and its owner reference.
3. `bookings` — customer, field, interval, and booking status.
4. `payments` — payment attempts belonging to a booking.

The decomposition removes the main partial/repeated dependencies from the flattened relation. Transitive dependencies are reviewed in the next stage.

### 3.3. Third Normal Form (3NF)

A relation is in Third Normal Form when:

1. It is already in 2NF.
2. No non-key attribute depends transitively on a candidate key through another non-key attribute.

#### Transitive dependencies to avoid

If `fields` stored the owner's name and phone directly, the dependency would be:

`field_id → owner_id → owner_name, owner_phone`

Owner information belongs to `users`, not to each field row. Therefore `fields` stores only `owner_id` as a foreign key.

Likewise, if a booking row stored the customer's name and email, the dependency would be:

`booking_id → customer_id → customer_name, customer_email`

Customer information belongs to `users`; `bookings` stores only `customer_id`.

#### Anomalies prevented by the 3NF design

1. **Insertion anomaly:** an owner can register before creating a field; a field can be created before it receives a booking.
2. **Update anomaly:** changing a user's phone number requires updating one `users` row rather than every booking or field row.
3. **Deletion anomaly:** deleting a booking does not delete the customer, owner, or field records.

#### Final normalized relations

- `users` (`id`, `name`, `email`, `phone`, `password_hash`, `role`, ...)
- `fields` (`id`, `owner_id`, `name`, `address`, `capacity`, `hourly_price`, `status`, ...)
- `field_images` (`id`, `field_id`, `image_path`, `sort_order`)
- `field_schedules` (`id`, `field_id`, `day_of_week`, `start_time`, `end_time`, ...)
- `bookings` (`id`, `customer_id`, `field_id`, `start_at`, `end_at`, `total_price`, `status`, ...)
- `payments` (`id`, `booking_id`, `amount`, `method`, `status`, ...)
- `reviews` (`id`, `booking_id`, `customer_id`, `field_id`, `rating`, `comment`, ...)
- `notifications` (`id`, `user_id`, `booking_id`, `title`, `message`, `is_read`, ...)

The final relations keep descriptive facts with the entity they describe and use foreign keys to represent relationships.

---

## 4. Detailed description of the final tables

The proposed logical model contains **8 core tables**.

### 4.1. `users` — Users

Stores customer, field-owner, and administrator accounts.

| Attribute | Suggested type | Key | NULL | Description |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK | No | Unique user identifier |
| `name` | VARCHAR(255) | — | No | Full name |
| `email` | VARCHAR(255) | UQ | No | Unique login email |
| `phone` | VARCHAR(30) | — | Yes | Contact phone |
| `password_hash` | VARCHAR(255) | — | No | Hashed password |
| `role` | VARCHAR(20) | — | No | `CUSTOMER`, `OWNER`, or `ADMIN` |
| `is_active` | BOOLEAN | — | No | Account active flag |
| `created_at` | TIMESTAMP | — | Yes | Account creation time |
| `updated_at` | TIMESTAMP | — | Yes | Last update time |

### 4.2. `fields` — Football fields

Stores field listings managed by owners.

| Attribute | Suggested type | Key | NULL | Description |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK | No | Unique field identifier |
| `owner_id` | BIGINT UNSIGNED | FK → users.id | No | Field owner |
| `name` | VARCHAR(255) | — | No | Field name |
| `description` | TEXT | — | Yes | Field description |
| `address` | VARCHAR(500) | — | No | Physical address |
| `city` | VARCHAR(100) | — | No | City |
| `capacity` | SMALLINT UNSIGNED | — | No | Maximum player capacity |
| `hourly_price` | DECIMAL(10,2) | — | No | Price per hour |
| `status` | VARCHAR(20) | — | No | `DRAFT`, `PUBLISHED`, `INACTIVE`, `REJECTED` |
| `created_at` | TIMESTAMP | — | Yes | Creation time |
| `updated_at` | TIMESTAMP | — | Yes | Last update time |

### 4.3. `field_images` — Field photos

Stores image paths separately because one field can have multiple photos.

| Attribute | Suggested type | Key | NULL | Description |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK | No | Image identifier |
| `field_id` | BIGINT UNSIGNED | FK → fields.id | No | Related field |
| `image_path` | VARCHAR(500) | — | No | Stored file path |
| `sort_order` | SMALLINT UNSIGNED | — | No | Display order |
| `created_at` | TIMESTAMP | — | Yes | Upload time |

### 4.4. `field_schedules` — Field availability schedules

Stores recurring weekly opening intervals. Actual date-specific booking conflicts are checked against `bookings`.

| Attribute | Suggested type | Key | NULL | Description |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK | No | Schedule identifier |
| `field_id` | BIGINT UNSIGNED | FK → fields.id | No | Related field |
| `day_of_week` | TINYINT UNSIGNED | — | No | Day of week, 1–7 |
| `start_time` | TIME | — | No | Opening time |
| `end_time` | TIME | — | No | Closing time |
| `is_active` | BOOLEAN | — | No | Schedule active flag |
| `created_at` | TIMESTAMP | — | Yes | Creation time |
| `updated_at` | TIMESTAMP | — | Yes | Last update time |

### 4.5. `bookings` — Bookings

Stores booking requests and their lifecycle.

| Attribute | Suggested type | Key | NULL | Description |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK | No | Booking identifier |
| `customer_id` | BIGINT UNSIGNED | FK → users.id | No | Customer who created booking |
| `field_id` | BIGINT UNSIGNED | FK → fields.id | No | Booked field |
| `start_at` | DATETIME | — | No | Booking start |
| `end_at` | DATETIME | — | No | Booking end |
| `total_price` | DECIMAL(10,2) | — | No | Calculated booking price |
| `status` | VARCHAR(20) | — | No | `PENDING`, `CONFIRMED`, `CANCELLED`, `REJECTED`, `COMPLETED` |
| `customer_note` | TEXT | — | Yes | Optional customer note |
| `created_at` | TIMESTAMP | — | Yes | Booking creation time |
| `updated_at` | TIMESTAMP | — | Yes | Last status/update time |

### 4.6. `payments` — Payments

Stores payment transactions and attempts. A booking may have no payment record, one payment, or multiple attempts depending on the payment workflow.

| Attribute | Suggested type | Key | NULL | Description |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK | No | Payment identifier |
| `booking_id` | BIGINT UNSIGNED | FK → bookings.id | No | Related booking |
| `amount` | DECIMAL(10,2) | — | No | Payment amount |
| `method` | VARCHAR(20) | — | No | `CASH`, `CARD`, `ONLINE`, etc. |
| `status` | VARCHAR(20) | — | No | `PENDING`, `PAID`, `FAILED`, `REFUNDED` |
| `provider_reference` | VARCHAR(255) | UQ | Yes | External provider transaction reference |
| `paid_at` | DATETIME | — | Yes | Successful payment time |
| `created_at` | TIMESTAMP | — | Yes | Payment attempt creation time |
| `updated_at` | TIMESTAMP | — | Yes | Last update time |

### 4.7. `reviews` — Reviews

Stores customer feedback for completed bookings.

| Attribute | Suggested type | Key | NULL | Description |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK | No | Review identifier |
| `booking_id` | BIGINT UNSIGNED | FK → bookings.id, UQ | No | Reviewed booking; one review per booking |
| `customer_id` | BIGINT UNSIGNED | FK → users.id | No | Review author |
| `field_id` | BIGINT UNSIGNED | FK → fields.id | No | Reviewed field |
| `rating` | TINYINT UNSIGNED | — | No | Rating from 1 to 5 |
| `comment` | TEXT | — | Yes | Written feedback |
| `created_at` | TIMESTAMP | — | Yes | Review creation time |
| `updated_at` | TIMESTAMP | — | Yes | Last update time |

The application must verify that the booking belongs to the customer, is completed, and references the same field before accepting a review.

### 4.8. `notifications` — Notifications

Stores notifications delivered to users.

| Attribute | Suggested type | Key | NULL | Description |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK | No | Notification identifier |
| `user_id` | BIGINT UNSIGNED | FK → users.id | No | Recipient |
| `booking_id` | BIGINT UNSIGNED | FK → bookings.id | Yes | Related booking, if applicable |
| `title` | VARCHAR(255) | — | No | Notification title |
| `message` | TEXT | — | No | Notification content |
| `is_read` | BOOLEAN | — | No | Read flag |
| `created_at` | TIMESTAMP | — | Yes | Creation time |

---

## 5. Summary of entities

| Table | Purpose | Primary and foreign keys |
|---|---|---|
| `users` | Customer, owner, and administrator accounts | PK: `id`; UQ: `email` |
| `fields` | Football field listings | PK: `id`; FK: `owner_id → users.id` |
| `field_images` | Multiple photos for each field | PK: `id`; FK: `field_id → fields.id` |
| `field_schedules` | Weekly availability intervals | PK: `id`; FK: `field_id → fields.id` |
| `bookings` | Customer reservations and statuses | PK: `id`; FK: `customer_id → users.id`, `field_id → fields.id` |
| `payments` | Payment attempts and results | PK: `id`; FK: `booking_id → bookings.id` |
| `reviews` | Customer feedback | PK: `id`; UQ: `booking_id`; FKs to bookings, users, and fields |
| `notifications` | User notifications | PK: `id`; FKs to users and optionally bookings |

---

## 6. Relationships and cardinality

### 6.1. Relationship list

| Related tables | Relationship | Foreign key | Explanation |
|---|---|---|---|
| `users` — `fields` | 1:N | `fields.owner_id` | One owner can manage multiple fields |
| `fields` — `field_images` | 1:N | `field_images.field_id` | One field can have multiple photos |
| `fields` — `field_schedules` | 1:N | `field_schedules.field_id` | One field can have multiple weekly schedule intervals |
| `users` — `bookings` | 1:N | `bookings.customer_id` | One customer can create multiple bookings |
| `fields` — `bookings` | 1:N | `bookings.field_id` | One field can receive multiple bookings over time |
| `bookings` — `payments` | 1:N | `payments.booking_id` | One booking can have multiple payment attempts |
| `bookings` — `reviews` | 1:0..1 | `reviews.booking_id` | A booking can have at most one review |
| `users` — `reviews` | 1:N | `reviews.customer_id` | One customer can write multiple reviews |
| `fields` — `reviews` | 1:N | `reviews.field_id` | One field can receive multiple reviews |
| `users` — `notifications` | 1:N | `notifications.user_id` | One user can receive multiple notifications |
| `bookings` — `notifications` | 1:N optional | `notifications.booking_id` | A booking can be referenced by multiple notifications |

### 6.2. Booking relationship

A booking links one customer to one field for a specific interval. A customer can create many bookings, and a field can have many bookings at different times.

The database foreign keys guarantee that the customer and field exist. The application and transactional database logic must additionally ensure that the time interval is valid and does not overlap another active booking for the same field.

### 6.3. Why `field_images` and `field_schedules` are separate

A field may have several images and several weekly availability intervals. Storing these as comma-separated values or repeated columns in `fields` would violate 1NF. Separate child tables preserve atomic values and allow independent additions, updates, and deletions.

### 6.4. Optional one-to-one review relationship

`reviews.booking_id` has a `UNIQUE` constraint. This means one booking can have zero or one review. Every review must reference exactly one booking.

---

## 7. Data integrity constraints

### 7.1. Unique constraints

Recommended uniqueness rules:

1. `users.email` — prevents duplicate accounts with the same email.
2. `reviews.booking_id` — limits each booking to one review.
3. `payments.provider_reference` — unique when a provider reference is present.
4. `field_schedules(field_id, day_of_week, start_time, end_time)` — prevents exact duplicate schedule intervals.
5. Additional application-specific uniqueness may be added for external identifiers.

### 7.2. Check constraints

Recommended logical checks:

```sql
CHECK (capacity > 0)
CHECK (hourly_price >= 0)
CHECK (end_at > start_at)
CHECK (rating BETWEEN 1 AND 5)
CHECK (amount >= 0)
CHECK (end_time > start_time)
CHECK (day_of_week BETWEEN 1 AND 7)
```

Actual support and enforcement depend on the selected DBMS and version.

### 7.3. Preventing overlapping active bookings

The following booking statuses are considered active for conflict checking:

- `PENDING`
- `CONFIRMED`

Two intervals overlap when:

`new_start < existing_end AND new_end > existing_start`

The server must perform the conflict check inside a transaction and use an appropriate locking or serialization strategy. A simple pre-check outside a transaction is not sufficient because two simultaneous requests could both pass the check.

The database implementation should also define how long a `PENDING` booking blocks a time interval and how expired pending requests are released.

### 7.4. Foreign-key deletion rules

| Foreign key | Recommended rule | Reason |
|---|---|---|
| `fields.owner_id → users.id` | RESTRICT | Prevent deleting an owner while their fields exist; use account deactivation instead |
| `field_images.field_id → fields.id` | CASCADE | Images belong to the field |
| `field_schedules.field_id → fields.id` | CASCADE | Schedules belong to the field |
| `bookings.customer_id → users.id` | RESTRICT | Preserve booking history |
| `bookings.field_id → fields.id` | RESTRICT | Preserve booking history |
| `payments.booking_id → bookings.id` | RESTRICT | Preserve financial records |
| `reviews.booking_id → bookings.id` | RESTRICT | Preserve review integrity |
| `reviews.customer_id → users.id` | RESTRICT | Preserve review authorship |
| `reviews.field_id → fields.id` | RESTRICT | Preserve reviewed field reference |
| `notifications.user_id → users.id` | CASCADE or RESTRICT | Choose according to notification-retention policy |
| `notifications.booking_id → bookings.id` | SET NULL or CASCADE | Choose whether notifications must remain after booking deletion |

For production systems, soft deletion or account deactivation is generally safer than physically deleting users, fields, and booking history.

### 7.5. Database-level and application-level validation

- **Database level:** primary keys, foreign keys, unique constraints, check constraints, and transactions protect stored data even when requests originate outside the web interface.
- **Application level:** request validation, authorization policies, ownership checks, booking rules, review eligibility, and user-friendly error messages prevent invalid operations before they reach the database.

Both levels are necessary and complement each other.

---

## 8. Consistency with the UML class model

The logical model maps the principal FFBS domain classes to relational tables:

| UML class | Relational table | Mapping |
|---|---|---|
| `User` | `users` | Account, role, and contact data |
| `Field` | `fields` | Field listing and owner FK |
| `FieldImage` | `field_images` | One-to-many field images |
| `AvailabilitySlot` / schedule | `field_schedules` | Recurring weekly availability |
| `Booking` | `bookings` | Customer, field, interval, and status |
| `Payment` | `payments` | Payment attempts and status |
| `Review` | `reviews` | Review linked to a completed booking |
| `Notification` | `notifications` | Notification recipient and optional booking reference |

**Modeling note:** the earlier UML concept `AvailabilitySlot` is represented here as a recurring `field_schedules` table. If the implementation instead creates concrete date-specific slots, replace or extend this table with a date-specific availability entity. Do not mix recurring weekly hours and actual booking instances in one ambiguous table.

---

## 9. Conclusion

The FFBS logical database model defines eight core relational tables and their relationships. The design:

1. Identifies the initial data-redundancy problems in a flattened booking table.
2. Demonstrates the normalization process from 1NF through 2NF to 3NF.
3. Separates user, field, schedule, booking, payment, review, image, and notification data.
4. Defines primary keys, foreign keys, unique constraints, and relationship cardinalities.
5. Establishes integrity rules for booking intervals and overlapping active reservations.
6. Provides a foundation for the next stage: physical database design and SQL DDL implementation.

The model is a proposed logical design. Before implementation, confirm the exact booking approval workflow, payment policy, schedule model, cancellation policy, and DBMS-specific enforcement strategy.
