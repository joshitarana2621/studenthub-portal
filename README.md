

--------------------------------------------------
REQUIREMENT ANALYSIS DOCUMENT
--------------------------------------------------

1. PROJECT
Our college currently doesn't have a single place where students can look at all their info like marks, attendance, and campus events. Right now, notices are posted on boards, and we have to ask the admin office for attendance. So, we decided to make "StudentHub Portal". This website will help college students to log in, view details of courses, check their daily attendance, download study materials, and see upcoming notices and CIE (Internal) exam marks.

2. OBJECTIVES OF THE SYSTEM
- To make a simple web portal that works on browsers.
- Students should be able to register and log in securely.
- To display study materials and subject listings.
- To show attendance reports so students know if they are short of attendance.
- To view profile details and update general contact info and upload marksheets.
- To check campus notices and expert lectures.

3. USER ROLES & PERSONAS
Since this is a college portal, we will have:
- Student User: Can log in, register, view dashboard, update profile, see attendance, check assignments/notices, search results, and view department contact details.
- System Admin: (Future scope) to upload attendance files and study material PDFs.

4. FUNCTIONAL REQUIREMENTS (WHAT THE SYSTEM MUST DO)
Here are the pages we planned and designed wireframes for:

A. Welcome Page (index.html)
   - Must have a nice welcome header with "Learn • Connect • Grow".
   - Links to login/register.
   - A sidebar with main links (Home, Dashboard, Course, Attendance, etc.).
   - About College section, social media links, campus photos, and expert sessions.

B. Student Login Page (login.html)
   - Input fields: Enrollment Number, Password.
   - Must validate login and redirect to the student dashboard.
   - Link for new students to register.
   - Button to go back to home page.

C. Registration Page (register.html)
   - Form fields: Full name, Enrollment ID, Email, Password, Confirm Password, College, Branch list (dropdown), Current Semester (dropdown).
   - "Confirm" button to submit data.

D. Student Dashboard (dashboard.html)
   - Sidebar navigation to all portal sections.
   - A nice campus photo gallery (campus view, classroom, event, lab).
   - A short description about the college student portal.
   - Quick access links to Profile, Course, Attendance, Notices, and Results.

E. Course & Subject Page (course.html)
   - A table listing all subjects.
   - Columns: subject code, subject name, lecture type (lecture/lab), material (links to download PDFs), and faculty name.

F. Assignments & Notices Page (assignment.html)
   - Four distinct grids/cards:
     1. Upcoming Exams (CIE list).
     2. CIE Marks (internal marks).
     3. Subject Paperstyle (syllabus/pattern).
     4. Upcoming Expert Sessions (workshops, guest lectures).

G. Attendance Page (attendance.html)
   - Table showing attendance status. Columns: courses, lecture type, present/total sessions, and percentage.
   - An "overall attendance" summary box at the bottom.

I. Profile Page (profile.html)
   - Forms to input and edit student information:
     - General Info: Student ID, Full name, Mother's name, University, Institute, Program, Aadhar card details, DOB, Blood group, Religion, Caste, Date of admission, ACPC merit rank/marks.
     - Academic Info: SSC school/board/marksheet file upload, HSC school/board/marksheet file upload.
     - Contact details: Address, Guardian name, Relation, Occupation, Phone, College/Personal email.

J. Results Search Page (result.html)
   - Dropdown selections for Semester, Branch, and Exam Session.
   - Input field for Enrollment number.
   - A submit button to fetch results.

K. Contact & Department Info Page (contact.html)
   - Contact cards displaying emails/phone numbers for: HOD, Counsellor, Scholarship queries, Fees/Admission office, Hostel warden.
   - A section listing subject-wise faculty contacts.

5. NON-FUNCTIONAL REQUIREMENTS
- Usability: The portal must be easy to navigate using a standard navigation bar.
- Portability: The system must work on both desktop browsers and mobile devices.
- Design: Plain and neat HTML structures (styled later via CSS).
- Path Consistency: Relative paths must be properly managed (using "../" for files in subfolders) so that images and links do not break.

--------------------------------------------------
