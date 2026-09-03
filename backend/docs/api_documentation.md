# SIAKAD API Documentation (Phase 1, 2 & 3)

Base URL: `http://localhost:8000/api/v1`

All responses follow the Centralized API Response Standard.

---

## Centralized Response Schema

### 1. Success Response
```json
{
  "success": true,
  "message": "Data retrieved successfully.",
  "data": {}
}
```

### 2. Paginated Success Response
```json
{
  "success": true,
  "message": "Data retrieved successfully.",
  "data": [],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 50,
    "last_page": 3,
    "from": 1,
    "to": 20
  }
}
```

### 3. Error Response
```json
{
  "success": false,
  "message": "Validation failed.",
  "errors": {
    "class_id": ["Class is full (capacity: 30/30)."]
  }
}
```

---

## 1. Authentication & RBAC (`/api/v1/auth`, `/api/v1/roles`, `/api/v1/permissions`)
* `POST /api/v1/auth/login`: Authenticate and obtain API Bearer Token.
* `POST /api/v1/auth/logout`: Revoke token.
* `GET /api/v1/auth/me`: Get current user profile.
* `POST /api/v1/auth/change-password`: Change user password.
* `GET|POST /api/v1/roles`: List / Create roles.
* `GET|PUT|DELETE /api/v1/roles/{id}`: Show, update, delete role.
* `GET /api/v1/permissions`: List all system permissions.
* `POST /api/v1/users/{user_id}/roles`: Assign roles to user.

---

## 2. Academic Foundation (`/api/v1/academic`)
* `GET|POST /api/v1/academic/institutions`
* `GET|PUT|DELETE /api/v1/academic/institutions/{id}`
* `GET|POST /api/v1/academic/faculties`
* `GET|PUT|DELETE /api/v1/academic/faculties/{id}`
* `GET|POST /api/v1/academic/study-programs`
* `GET|PUT|DELETE /api/v1/academic/study-programs/{id}`
* `GET|POST /api/v1/academic/academic-years`
* `GET|PUT|DELETE /api/v1/academic/academic-years/{id}`
* `GET|POST /api/v1/academic/semesters`
* `GET|PUT|DELETE /api/v1/academic/semesters/{id}`

---

## 3. Student & Lecturer Master Data (`/api/v1/students`, `/api/v1/lecturers`)
* `GET|POST /api/v1/students`
* `GET|PUT|DELETE /api/v1/students/{id}`
* `PATCH /api/v1/students/{id}/status`
* `POST /api/v1/students/{id}/families`
* `POST /api/v1/students/{id}/educations`
* `GET|POST /api/v1/lecturers`
* `GET|PUT|DELETE /api/v1/lecturers/{id}`
* `PATCH /api/v1/lecturers/{id}/status`

---

## 4. Course & Curriculum (`/api/v1/courses`, `/api/v1/curricula`)
* `GET|POST /api/v1/courses`
* `GET|PUT|DELETE /api/v1/courses/{id}`
* `GET|POST /api/v1/courses/{id}/prerequisites`
* `GET|POST /api/v1/curricula`
* `GET|PUT|DELETE /api/v1/curricula/{id}`
* `PATCH /api/v1/curricula/{id}/activate`
* `PATCH /api/v1/curricula/{id}/archive`
* `GET|POST /api/v1/curricula/{id}/semesters`
* `GET|POST /api/v1/curriculum-semesters/{semester_id}/subjects`
* `DELETE /api/v1/curriculum-semesters/{semester_id}/subjects/{subject_id}`

---

## 5. Academic Class Management (`/api/v1/classes`)

* **Permissions**: `classes.view`, `classes.create`, `classes.update`, `classes.delete`, `classes.open`, `classes.close`, `classes.cancel`, `classes.assign_lecturer`

### 5.1. List & Filter Classes
* **GET `/api/v1/classes`**
* **Query Params**: `?semester_id=1&course_id=5&study_program_id=1&lecturer_id=2&status=open&search=PAI`

### 5.2. Create Class
* **POST `/api/v1/classes`**
```json
{
  "semester_id": 1,
  "course_id": 10,
  "study_program_id": 1,
  "code": "PAI201-A",
  "name": "Ilmu Pendidikan Islam - A",
  "section": "A",
  "capacity": 35,
  "status": "draft",
  "lecturers": [
    {
      "lecturer_id": 1,
      "role": "primary"
    }
  ]
}
```

### 5.3. Status Transitions & Lecturer Assignment
* **PATCH `/api/v1/classes/{id}/open`** (Opens class for KRS enrollment)
* **PATCH `/api/v1/classes/{id}/close`** (Closes class)
* **PATCH `/api/v1/classes/{id}/cancel`** (Cancels class)
* **GET `/api/v1/classes/{id}/lecturers`**
* **POST `/api/v1/classes/{id}/lecturers`** (`{"lecturer_id": 2, "role": "co_lecturer"}`)
* **DELETE `/api/v1/classes/{id}/lecturers/{lecturerId}`**

---

## 6. Room & Schedule Management (`/api/v1/rooms`, `/api/v1/schedules`)

* **Permissions**: `rooms.*`, `schedules.*`

### 6.1. Rooms
* **GET|POST `/api/v1/rooms`** (`?building=Tarbiyah&status=active`)
* **GET|PUT|DELETE `/api/v1/rooms/{id}`**
* **PATCH `/api/v1/rooms/{id}/status`** (`{"status": "active"}`)

### 6.2. Schedules & Conflict Detection
* **GET `/api/v1/schedules`** (`?semester_id=1&lecturer_id=1&room_id=2&day_of_week=monday`)
* **POST `/api/v1/schedules`**:
```json
{
  "class_id": 1,
  "room_id": 1,
  "day_of_week": "monday",
  "start_time": "08:00",
  "end_time": "10:30"
}
```
* **GET|PUT|DELETE `/api/v1/schedules/{id}`**
* **GET|POST `/api/v1/classes/{classId}/schedules`**

---

## 7. Enrollment & KRS Management (`/api/v1/enrollments`)

* **Permissions**: `enrollments.view`, `enrollments.create`, `enrollments.update`, `enrollments.submit`, `enrollments.approve`, `enrollments.reject`, `enrollments.revise`, `enrollments.lock`
* **Ownership Rule**: Students automatically query and create only their own KRS based on authenticated session.

### 7.1. Create KRS Draft
* **POST `/api/v1/enrollments`**
```json
{
  "semester_id": 1
}
```

### 7.2. Add / Remove Class in KRS
* **GET `/api/v1/enrollments/{id}/items`**
* **POST `/api/v1/enrollments/{id}/items`**:
```json
{
  "class_id": 1
}
```
*(Automatically validates Student status, Class capacity, duplicate course check, prerequisite rules, schedule overlaps, and SKS credit limit).*
* **DELETE `/api/v1/enrollments/{id}/items/{itemId}`**

### 7.3. KRS Workflow Actions
* **POST `/api/v1/enrollments/{id}/submit`** (Transitions `draft`/`revision_required` -> `submitted`)
* **POST `/api/v1/enrollments/{id}/approve`** (Transitions `submitted` -> `approved`)
```json
{
  "notes": "Disetujui dosen pembimbing akademik"
}
```
* **POST `/api/v1/enrollments/{id}/reject`** (`{"reason": "Mata kuliah tidak sesuai rencana studi"}`)
* **POST `/api/v1/enrollments/{id}/request-revision`** (`{"notes": "Harap ganti kelas pilihan hari Kamis"}`)
* **POST `/api/v1/enrollments/{id}/lock`** (Transitions `approved` -> `locked`)

---

## 8. Academic Advising (`/api/v1/advisors`, `/api/v1/advising-sessions`)

* **Permissions**: `advising.view`, `advising.assign`, `advising.create_session`, `advising.update_session`

### 8.1. Academic Advisor Assignment
* **GET `/api/v1/advisors`**
* **POST `/api/v1/advisors`**:
```json
{
  "student_id": 1,
  "lecturer_id": 2,
  "start_date": "2025-09-01",
  "notes": "Pembimbing akademik reguler"
}
```
* **GET `/api/v1/students/{id}/advisor`** (Get active advisor)
* **GET `/api/v1/students/{id}/advisor-history`** (Get all historical advisor assignments)
* **POST `/api/v1/students/{id}/advisor`** (Change advisor)
* **PATCH `/api/v1/advisors/{id}/end`** (End assignment)

### 8.2. Advising Consultation Sessions
* **GET `/api/v1/advising-sessions`** (`?student_id=1&lecturer_id=2`)
* **POST `/api/v1/advising-sessions`**:
```json
{
  "student_id": 1,
  "lecturer_id": 2,
  "enrollment_id": 1,
  "session_date": "2025-09-02",
  "topic": "Konsultasi Rencana Studi",
  "notes": "Diarahkan mengambil 20 SKS sesuai kurikulum semester 1.",
  "status": "completed"
}
```
* **GET `/api/v1/advising-sessions/{id}`**
* **PUT `/api/v1/advising-sessions/{id}`**
* **DELETE `/api/v1/advising-sessions/{id}`**

---

## 9. Audit & Settings Endpoints
* `GET /api/v1/audit/logs`
* `GET /api/v1/audit/logs/{id}`
* `GET /api/v1/settings`
* `GET /api/v1/settings/{key}`
* `PUT /api/v1/settings/{key}`
* `PUT /api/v1/settings/batch`

---

## 10. Academic Attendance & BAP (`/api/v1/teaching-sessions`, `/api/v1/student-attendances`)

* **Permissions**: `attendance.view`, `attendance.manage`, `attendance.record`, `attendance.self_checkin`

### 10.1. Teaching Sessions & BAP
* **GET `/api/v1/classes/{class}/teaching-sessions`** (List all sessions 1-16)
* **POST `/api/v1/classes/{class}/teaching-sessions`** (Create meeting & initialize attendance)
* **GET `/api/v1/teaching-sessions/{id}`**
* **PUT `/api/v1/teaching-sessions/{id}`** (Update meeting notes, topic, method)
* **DELETE `/api/v1/teaching-sessions/{id}`**
* **POST `/api/v1/teaching-sessions/{id}/generate-token`** (Generate 6-character live check-in token)
* **POST `/api/v1/teaching-sessions/{id}/close`** (Close/lock meeting)

### 10.2. Attendance Recording & Statistics
* **GET `/api/v1/classes/{class}/attendance-matrix`** (Full matrix 1-16 & exam eligibility)
* **POST `/api/v1/teaching-sessions/{id}/attendances/batch`** (Batch attendance marking)
* **PUT `/api/v1/student-attendances/{id}`** (Single attendance update)
* **POST `/api/v1/student-attendances/self-checkin`** (Student token submit)
* **GET `/api/v1/student-attendances/my-summary`** (Student attendance dashboard & progress)

---

## 11. Academic Assessment & Grading (`/api/v1/assessment-schemes`, `/api/v1/assessment-components`, `/api/v1/student-grades`)

* **Permissions**:
  - `assessment.view`, `assessment.create`, `assessment.update`, `assessment.delete`, `assessment.scheme.manage`
  - `grade.view`, `grade.input`, `grade.update`, `grade.submit`, `grade.finalize`, `grade.revise`, `grade.history`

### 11.1. Assessment Schemes
* **GET `/api/v1/classes/{class}/assessment-scheme`**
  Get active or primary assessment scheme for class with components and weight items.
* **POST `/api/v1/classes/{class}/assessment-scheme`**
  Create an assessment scheme with components and percentage weights.
  ```json
  {
    "name": "Skema Penilaian Gasal 2025/2026",
    "description": "Bobot penilaian standar",
    "items": [
      { "assessment_component_id": 1, "weight": 20.00 },
      { "assessment_component_id": 2, "weight": 30.00 },
      { "assessment_component_id": 3, "weight": 50.00 }
    ]
  }
  ```
* **GET `/api/v1/assessment-schemes/{id}`**
* **PUT `/api/v1/assessment-schemes/{id}`**
* **DELETE `/api/v1/assessment-schemes/{id}`**
* **POST `/api/v1/assessment-schemes/{id}/activate`**
  Activate assessment scheme (Validates total weight == 100%, archives other active schemes).
* **POST `/api/v1/assessment-schemes/{id}/archive`**

### 11.2. Assessment Components
* **GET `/api/v1/classes/{class}/components`**
* **POST `/api/v1/assessment-components`**:
  ```json
  {
    "academic_class_id": 1,
    "name": "Tugas Mandiri 1",
    "code": "TUGAS_1",
    "type": "assignment",
    "max_score": 100.00,
    "is_required": true,
    "sequence": 1
  }
  ```
* **GET `/api/v1/assessment-components/{id}`**
* **PUT `/api/v1/assessment-components/{id}`**
* **DELETE `/api/v1/assessment-components/{id}`**
* **GET `/api/v1/assessment-schemes/{id}/components`**
* **POST `/api/v1/assessment-schemes/{id}/components`** (Attach component with weight)

### 11.3. Student Grades & Calculations
* **GET `/api/v1/classes/{class}/grades`**
  Calculates class-wide recap including final scores, letter grades (A, B+, B, C+, C, D, E), grade points (4.0 - 0.0), and component breakdown.
* **GET `/api/v1/classes/{class}/grades/{student}`**
  Calculates single student grade recap.
* **POST `/api/v1/classes/{class}/grades`** (Batch grade input/update)
  ```json
  {
    "grades": [
      { "student_id": 1, "assessment_component_id": 1, "score": 85.50, "notes": "Sangat baik" },
      { "student_id": 1, "assessment_component_id": 2, "score": 90.00 }
    ]
  }
  ```
* **PUT `/api/v1/student-grades/{id}`** (Update single grade draft)

### 11.4. Grade Workflow & Revisions
* **POST `/api/v1/classes/{class}/grades/submit`**
  Submit all draft or revision-required grades of class for review (`draft` -> `submitted`).
* **POST `/api/v1/classes/{class}/grades/finalize`**
  Finalize and lock all grades transaction-safely with audit log (`submitted` -> `final`).
* **POST `/api/v1/student-grades/{id}/request-revision`**
  Request revision on a submitted grade (`submitted` -> `revision_required`).
  ```json
  {
    "reason": "Harap periksa kembali hasil kuis nomor 3."
  }
  ```
* **POST `/api/v1/student-grades/{id}/revise`**
  Formal revision on a final/locked grade with immutable revision history.
  ```json
  {
    "new_score": 92.50,
    "reason": "Koreksi salah hitung nilai ujian akhir semester setelah sanggah."
  }
  ```
* **GET `/api/v1/student-grades/{id}/revisions`**
  Retrieve full audit revision history for a grade item.

