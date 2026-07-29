# StudentHub Portal - Sitemap

This document outlines the site structure and navigation hierarchy of the StudentHub Portal project.

---

## 🗺️ Sitemap Diagram (Text Representation)

```
                            [ Home ]
                                |
  +----------------+------------+-----+-------------+--------------+--------------+
  |                |                  |             |              |              |
[ About College ] [ Login ]      [ Register ]  [ Dashboard ]   [ Courses ]   [ Attendance ] [ Contact ]
  |                |                  |             |              |              |              |
  |-- College Info |-- Student Login  |-- New Stud  |-- Profile    |-- Subject    |-- View       |-- Email
  |-- Campus Photos                   |   Register  |-- Course Det |   List       |   Attendance |-- Phone
  |-- Placement Data                                |-- Attendance |-- Study      |-- Attendance |-- Location
  |-- College Events                                |-- Notices    |   Material   |   Report
  
                                            [ Footer ]
                                                |
                             +------------------+------------------+
                             |                  |                  |
                      [ Privacy Policy ] [ Terms & Conditions ] [ Social Media ]
```

---

## 📂 Navigation & Page Breakdown

### 1. Home (`index.html`)
The main entry point of the portal. It introduces the student hub, displays announcements, and provides quick navigation links to all other sections.

### 2. About College
Detailed info about the institution, including:
* **College Information**: History, vision, and mission.
* **Campus Photos**: Visual gallery of campus grounds, classrooms, labs, and activities.
* **Placement Data**: Statistics on student placements and recruiters.
* **College Events**: Technical clubs, hackathons, and cultural events.

### 3. Login (`pages/login.html`)
The student portal authentication page, allowing students to access their dashboards securely using their **Enrollment Number** and **Password**.

### 4. Register (`pages/register.html`)
Registration page for new student enrollment, capturing full name, email, credentials, branch, and current semester.

### 5. Dashboard (`pages/dashboard.html`)
A landing dashboard for logged-in students offering quick-access links to:
* **Student Profile**
* **Course Details**
* **Attendance Records**
* **Notices / CIE Exam Updates**

### 6. Courses (`pages/course.html`)
Academic details page, providing:
* **Subject List**: Overview of subjects taught in the semester.
* **Study Material**: Download links for lecture notes, slides, and lab manuals.

### 7. Attendance (`pages/attendance.html`)
A portal page for checking:
* **View Attendance**: Course-wise lecture presence/total count.
* **Attendance Report**: Overall attendance percentage calculation.

### 8. Contact (`pages/contact.html`)
A page containing emergency and general query contact details for HODs, counselors, admissions, fee query desk, hostels, and subject-wise faculty phone/email.

---

## 🏢 Site Footer
The footer is accessible across all pages and links to:
* **Privacy Policy**
* **Terms & Conditions**
* **Social Media Links** (Instagram, Facebook, LinkedIn)
