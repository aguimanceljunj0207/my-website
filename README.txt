IM 101a GRADEBOOK – XAMPP + PHP + MySQL
========================================

REQUIREMENTS
- XAMPP (Apache + MySQL)
- A code editor such as Visual Studio Code. Visual Studio can also edit the PHP files.
- Web browser

INSTALLATION
1. Install XAMPP.
2. Start Apache and MySQL in the XAMPP Control Panel.
3. Copy the entire "IM101a" folder to:
   C:\xampp\htdocs\
4. Open phpMyAdmin:
   http://localhost/phpmyadmin
5. Choose Import and select:
   database/im101a_gradebook.sql
   Then click Go.
6. Open:
   http://localhost/IM101a/

DATABASE
Database name: im101a_gradebook

Tables:
- students
- terms
- assessment_categories
- scores

The design is normalized: student information is stored once, assessment categories
and weights are stored once, and score records reference the student, term, and category.

GRADING
Term Grade =
  Exam 40%
+ Quiz/Assignment/Homework 30%
+ Attendance/Class Participation 10%
+ Laboratory/Activities/Group Project 20%

Final Grade = 1/3 Midterm + 2/3 Final Term

The sample student and scores are included only to demonstrate the system and are
based on the first populated row of the uploaded workbook.

FILES
- index.php       Dashboard
- students.php    Add/delete students
- grades.php      Enter/update midterm/final scores
- summary.php     Automatic final-grade summary
- config/db.php   MySQL connection
- database/*.sql  Database setup

IF MYSQL HAS A PASSWORD
Edit config/db.php and change:
$pass = '';

to your MySQL password.

IMPORTANT
This is a local school/project starter system. For production deployment, add
authentication, CSRF protection, server-side authorization, audit logs, and backups.
