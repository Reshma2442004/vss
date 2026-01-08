# 🛠 SMART System API Documentation

## Base URL: `http://localhost:5000/api`

## Student APIs

### 1. Student Login
**POST** `/students/login`
```json
{
  "grn": "GRN001",
  "password": "pass123"
}
```
**Response:**
```json
{
  "message": "Login successful",
  "student": {
    "id": 1,
    "grn": "GRN001",
    "first_name": "John",
    "last_name": "Doe",
    "email": "john@email.com",
    "class": "10",
    "section": "A",
    "hostel_room": "H1-201"
  }
}
```

### 2. Bulk Upload Students (Rector Only)
**POST** `/students/bulk-upload`
- Headers: `Authorization: Bearer <token>`
- Body: Form-data with `file` (CSV)
- CSV Format: grn,password,first_name,last_name,email,phone,father_name,mother_name,class,section,roll_number,hostel_room,is_hostel_student

**Response:**
```json
{
  "message": "Bulk upload completed",
  "totalRecords": 5,
  "successfulImports": 5,
  "students": [
    {
      "grn": "GRN001",
      "password": "pass123",
      "name": "John Doe"
    }
  ]
}
```

### 3. Get All Students (Rector Only)
**GET** `/students/all`
- Headers: `Authorization: Bearer <token>`

## Hostel Staff APIs

### 1. Add Hostel Staff (Rector Only)
**POST** `/hostel-staff/add`
```json
{
  "student_id": 1,
  "position": "warden"
}
```

### 2. Get All Hostel Staff
**GET** `/hostel-staff/`

### 3. Get Available Students for Staff Assignment
**GET** `/hostel-staff/available-students`

### 4. Remove Hostel Staff
**DELETE** `/hostel-staff/:staff_id`

## Setup Instructions

1. **Database Setup:**
   ```bash
   mysql -u root -p < database/schema.sql
   ```

2. **Backend Setup:**
   ```bash
   cd backend
   npm install
   npm run dev
   ```

3. **Test Student Login:**
   - Use sample data from `database/sample_students.csv`
   - Login with GRN: `GRN001`, Password: `pass123`

4. **Test Bulk Upload:**
   - Login as rector first
   - Upload `database/sample_students.csv` file