# Smoke tests for all nine modules: faculties, departments, lecturers, courses,
# students, enrollments, grades, announcements and users. Exercises each list
# page, one create-plus-delete round trip per module, and the handful of
# validation paths unique to these modules.
#
# Dot-sources smoke.ps1 for the HTTP + DB harness. Resolved relative to this
# file so the suite runs from any checkout.

$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'smoke.ps1')

$pass = 0
$fail = 0

function Check {
    param([bool]$Cond, [string]$Msg)
    if ($Cond) { $script:pass++; Write-Host "  ok  $Msg" }
    else { $script:fail++; Write-Host " FAIL $Msg" }
}

function Assert-Clean {
    param([string]$Html, [string]$Msg)
    $d = Diagnostics $Html
    Check ($null -eq $d -and $Html -ne '') "$Msg (diagnostics: $d)"
}

function Assert-Status200 {
    param($Result, [string]$Msg)
    Check ($Result.Status -eq 200) "$Msg (status $($Result.Status))"
}

function Assert-Flash {
    param([string]$Html, [string]$Needle, [string]$Msg)
    $f = Flash-Text $Html
    Check ($f -match [regex]::Escape($Needle)) "$Msg (flash: $f)"
}

function Assert-FieldError {
    param([string]$Html, [string]$Needle, [string]$Msg)
    $e = Error-Text $Html
    Check ($e -match [regex]::Escape($Needle)) "$Msg (errors: $e)"
}

$suffix = Get-Random -Minimum 1000 -Maximum 9999

Write-Host "== Login =="
Check (Login 'admin' 'Admin@123') 'admin login'

Write-Host "== List pages reachable =="
foreach ($m in @('lecturers', 'courses', 'students', 'enrollments', 'grades', 'announcements', 'users')) {
    $r = Http-Get "/$m/index.php"
    Assert-Status200 $r "$m/index.php"
    Assert-Clean $r.Html "$m/index.php renders"
}

Write-Host "== Dashboard deep links =="
$sNo = (Db "SELECT student_no FROM university_management.students ORDER BY id LIMIT 1").Trim()
$r = Http-Get "/students/view.php?id=$sNo"
Assert-Status200 $r "students/view by student_no ($sNo)"
$r = Http-Get "/enrollments/index.php?student=$sNo"
Assert-Status200 $r "enrollments/index?student=<student_no>"
$r = Http-Get "/grades/index.php?student=$sNo"
Assert-Status200 $r "grades/index?student=<student_no>"

Write-Host "== Lecturers =="
$staffNo = "SMOKE$suffix"
$r = Post-Form "/lecturers/form.php" @{
    staff_no = $staffNo; first_name = 'Smoke'; last_name = "Lect$suffix";
    email = "smoke$suffix@hagmah.edu"; department_id = (Db "SELECT id FROM university_management.departments ORDER BY id LIMIT 1").Trim();
    status = 'active'
}
Assert-Status200 $r "lecturer create"
Assert-Flash $r.Html 'Lecturer added' 'lecturer create flash'
$lid = (Db "SELECT id FROM university_management.lecturers WHERE staff_no='$staffNo'").Trim()
Check ([int]$lid -eq [int]$lid) "lecturer row created (id $lid)"
$r = Http-Get "/lecturers/form.php?id=$lid"
Assert-Status200 $r "lecturer edit page"
$r = Post-Form "/lecturers/index.php" @{ action='delete'; delete_id=$lid }
Assert-Flash $r.Html 'Lecturer deleted' 'lecturer delete'
Check ((Db "SELECT COUNT(*) FROM university_management.lecturers WHERE staff_no='$staffNo'") -eq 0) 'lecturer row removed'

Write-Host "== Courses =="
$code = "SM-$suffix"
$r = Post-Form "/courses/form.php" @{
    course_code = $code; course_name = "Smoke Course $suffix";
    department_id = (Db "SELECT id FROM university_management.departments ORDER BY id LIMIT 1").Trim();
    credit_hours = 3; semester = 'Semester 1'; description = 'Created by smoke tests.'
}
Assert-Status200 $r "course create"
Assert-Flash $r.Html 'Course added' 'course create flash'
$cid = (Db "SELECT id FROM university_management.courses WHERE course_code='$code'").Trim()
$r = Http-Get "/courses/form.php?id=$cid"
Assert-Status200 $r "course edit page"
$r = Post-Form "/courses/index.php" @{ action='delete'; delete_id=$cid }
Assert-Flash $r.Html 'Course deleted' 'course delete'
Check ((Db "SELECT COUNT(*) FROM university_management.courses WHERE course_code='$code'") -eq 0) 'course row removed'

Write-Host "== Students =="
$stuNo = "HU9$($suffix)"
$dept = (Db "SELECT CONCAT(id, '|', faculty_id) FROM university_management.departments ORDER BY id LIMIT 1").Trim().Split('|')
$r = Post-Form "/students/form.php" @{
    student_no = $stuNo; first_name = 'Smoke'; last_name = "Student$suffix";
    gender = 'other'; date_of_birth = '2004-05-10';
    email = "stu$suffix@hagmah.edu"; phone = '+960 7000000';
    faculty_id = $dept[1]; department_id = $dept[0];
    enrollment_year = '2026'; enrollment_date = '2026-09-01'; status = 'active'
}
Assert-Status200 $r "student create"
Assert-Flash $r.Html 'Student added' 'student create flash'
$sid = (Db "SELECT id FROM university_management.students WHERE student_no='$stuNo'").Trim()
Check ((Db "SELECT COUNT(*) FROM university_management.students WHERE student_no='$stuNo'") -eq 1) 'student row created'

Write-Host "   -- duplicate student_no is rejected --"
$r = Post-Form "/students/form.php" @{
    student_no = $stuNo; first_name = 'Smoke'; last_name = "Two$suffix";
    gender = 'other'; date_of_birth = '2004-05-10';
    email = "stu2$suffix@hagmah.edu";
    faculty_id = $dept[1]; department_id = $dept[0];
    enrollment_year = '2026'; enrollment_date = '2026-09-01'; status = 'active'
}
Assert-FieldError $r.Html 'already in use' 'duplicate student_no error'

Write-Host "   -- student edit round trip --"
$r = Http-Get "/students/form.php?id=$stuNo"
Assert-Status200 $r "student edit by student_no ($stuNo)"
$r = Post-Form "/students/form.php?id=$stuNo" @{
    id = $sid; student_no = $stuNo; first_name = 'Smoke'; last_name = "Student$suffix edited";
    gender = 'other'; date_of_birth = '2004-05-10';
    email = "stu$suffix@hagmah.edu"; phone = '+960 7000000';
    faculty_id = $dept[1]; department_id = $dept[0];
    enrollment_year = '2026'; enrollment_date = '2026-09-01'; status = 'active'
}
Assert-Status200 $r "student update"
Assert-Flash $r.Html 'Student updated' 'student update flash'

Write-Host "== Enrollments =="
$cid = (Db "SELECT id FROM university_management.courses WHERE department_id=$($dept[0]) ORDER BY id LIMIT 1").Trim()
$r = Post-Form "/enrollments/form.php?student=$stuNo&course=$cid" @{
    student_id = $sid; course_id = $cid;
    academic_year = '2026/2027'; semester = 'Semester 1';
    enrollment_date = '2026-09-02'; status = 'enrolled'
}
Assert-Status200 $r "enrollment create"
Assert-Flash $r.Html 'Enrollment added' 'enrollment create flash'
$eid = (Db "SELECT id FROM university_management.enrollments WHERE student_id=$sid AND course_id=$cid AND academic_year='2026/2027' LIMIT 1").Trim()

Write-Host "   -- duplicate enrollment is rejected --"
$r = Post-Form "/enrollments/form.php?student=$stuNo&course=$cid" @{
    student_id = $sid; course_id = $cid;
    academic_year = '2026/2027'; semester = 'Semester 1';
    enrollment_date = '2026-09-03'; status = 'enrolled'
}
Assert-FieldError $r.Html 'already been enrolled' 'duplicate enrollment error'

Write-Host "   -- mismatched department is rejected --"
$otherDept = (Db "SELECT id FROM university_management.departments WHERE id <> $($dept[0]) ORDER BY id LIMIT 1").Trim()
$otherCourse = (Db "SELECT id FROM university_management.courses WHERE department_id=$otherDept ORDER BY id LIMIT 1").Trim()
$r = Post-Form "/enrollments/form.php" @{
    student_id = $sid; course_id = $otherCourse;
    academic_year = '2026/2027'; semester = 'Semester 1';
    enrollment_date = '2026-09-04'; status = 'enrolled'
}
Assert-FieldError $r.Html 'different department' 'cross-department enrollment error'

$r = Http-Get "/enrollments/view.php?id=$eid"
Assert-Status200 $r "enrollment view"

Write-Host "== Grades =="
$r = Post-Form "/grades/form.php?enrollment=$eid" @{
    enrollment_id = $eid; assessment = 'Midterm Examination'; marks = '87.5'
}
Assert-Status200 $r "grade create"
Assert-Flash $r.Html 'Grade recorded' 'grade create flash'
$gid = (Db "SELECT id FROM university_management.grades WHERE enrollment_id=$eid AND assessment='Midterm Examination' LIMIT 1").Trim()
$letter = (Db "SELECT letter_grade FROM university_management.grades WHERE id=$gid").Trim()
Check ($letter -eq 'B') "grade letter computed from marks (got $letter)"
$r = Http-Get "/grades/view.php?id=$gid"
Assert-Status200 $r "grade view"
$r = Http-Get "/grades/index.php?student=$stuNo"
Assert-Status200 $r "grades filtered by student_no"

Write-Host "   -- duplicate assessment is rejected --"
$r = Post-Form "/grades/form.php?enrollment=$eid" @{
    enrollment_id = $eid; assessment = 'Midterm Examination'; marks = '91'
}
Assert-FieldError $r.Html 'already has a mark' 'duplicate assessment error'

Write-Host "   -- cleanup grades/enrollment/student --"
$r = Post-Form "/grades/index.php" @{ action='delete'; delete_id=$gid }
Assert-Flash $r.Html 'Grade deleted' 'grade delete'
$r = Post-Form "/enrollments/index.php" @{ action='delete'; delete_id=$eid }
Assert-Flash $r.Html 'Enrollment deleted' 'enrollment delete'
$r = Post-Form "/students/index.php" @{ action='delete'; delete_id=$sid }
Assert-Flash $r.Html 'Student deleted' 'student delete'
Check ((Db "SELECT COUNT(*) FROM university_management.students WHERE student_no='$stuNo'") -eq 0) 'student removed from ledger'

Write-Host "== Announcements =="
$r = Post-Form "/announcements/form.php" @{
    title = "Smoke announcement $suffix"; content = 'Brought to you by the smoke tests.'; status = 'published'
}
Assert-Status200 $r "announcement create"
Assert-Flash $r.Html 'Announcement saved' 'announcement create flash'
$aid = (Db "SELECT id FROM university_management.announcements WHERE title='Smoke announcement $suffix' LIMIT 1").Trim()
$pub = (Db "SELECT IFNULL(published_at,'') FROM university_management.announcements WHERE id=$aid").Trim()
Check ($pub -ne '') "published_at stamped on publish (got $pub)"
$r = Http-Get "/announcements/view.php?id=$aid"
Assert-Status200 $r "announcement view"
$r = Post-Form "/announcements/index.php" @{ action='delete'; delete_id=$aid }
Assert-Flash $r.Html 'Announcement deleted' 'announcement delete'

Write-Host "== Users =="
$uname = "smoke$suffix"
$r = Post-Form "/users/form.php" @{
    full_name = "Smoke User $suffix"; username = $uname; email = "smokeuser$suffix@hagmah.edu";
    role = 'registrar'; status = 'active'; password = 'Passw0rd123!'; password_confirm = 'Passw0rd123!'
}
Assert-Status200 $r "user create"
Assert-Flash $r.Html 'Account created' 'user create flash'
$uid = (Db "SELECT id FROM university_management.users WHERE username='$uname' LIMIT 1").Trim()

Write-Host "   -- duplicate username is rejected --"
$r = Post-Form "/users/form.php" @{
    full_name = "Smoke Two $suffix"; username = $uname; email = "smokeuser2$suffix@hagmah.edu";
    role = 'registrar'; status = 'active'; password = 'Passw0rd123!'; password_confirm = 'Passw0rd123!'
}
Assert-FieldError $r.Html 'already in use' 'duplicate username error'

Write-Host "   -- password mismatch is rejected --"
$r = Post-Form "/users/form.php" @{
    full_name = "Smoke Three $suffix"; username = "smoke3$suffix"; email = "smokeuser3$suffix@hagmah.edu";
    role = 'registrar'; status = 'active'; password = 'Passw0rd123!'; password_confirm = 'Different!'
}
Assert-FieldError $r.Html 'does not match' 'password mismatch error'

Write-Host "   -- new user can sign in --"
Reset-Session
Check (Login $uname 'Passw0rd123!') "created user signs in"
Check (Login 'admin' 'Admin@123') 'admin signs back in'

$r = Http-Get "/users/view.php?id=$uid"
Assert-Status200 $r "user view"
$r = Http-Get "/users/form.php?id=$uid"
Assert-Status200 $r "user edit page"
$r = Post-Form "/users/form.php?id=$uid" @{
    id = $uid; full_name = "Smoke User $suffix"; username = $uname; email = "smokeuser$suffix@hagmah.edu";
    role = 'registrar'; status = 'inactive'
}
Assert-Flash $r.Html 'Account updated' 'user update flash'
$r = Post-Form "/users/index.php" @{ action='delete'; delete_id=$uid }
Assert-Flash $r.Html 'User deleted' 'user delete'
Check ((Db "SELECT COUNT(*) FROM university_management.users WHERE username='$uname'") -eq 0) 'user removed from ledger'

Write-Host "== Faculties and departments =="

# The head-of-department picker is the one query here that reaches across
# tables, and it is fed by a list of lecturers in the owning faculty. A
# regression in it renders an empty <select> on every existing department while
# leaving the list screens green, so it gets an explicit check.
$hd = Http-Get '/departments/form.php?id=1'
Assert-Status200 $hd 'department edit page'
Assert-Clean $hd.Html 'department edit page renders'
Check ($hd.Html -match 'Mohamed Abdirahman') 'head-of-department picker lists the faculty head'

$facCode = "SMK$suffix"
$r = Post-Form '/faculties/form.php' @{ code = $facCode; name = "Smoke Faculty $suffix" }
Assert-Status200 $r 'faculty create'
Assert-Flash $r.Html 'Faculty added' 'faculty create flash'
$facId = (Db "SELECT id FROM university_management.faculties WHERE code='$facCode' LIMIT 1").Trim()
Check ($facId -ne '') "faculty row created (id $facId)"

$depCode = "SMK$suffix"
$r = Post-Form '/departments/form.php' @{ code = $depCode; name = "Smoke Department $suffix"; faculty_id = $facId }
Assert-Status200 $r 'department create'
Assert-Flash $r.Html 'Department added' 'department create flash'
$depId = (Db "SELECT id FROM university_management.departments WHERE code='$depCode' LIMIT 1").Trim()
Check ($depId -ne '') "department row created (id $depId)"

Write-Host "   -- department validation --"
$r = Post-Form '/departments/form.php' @{ code = $depCode; name = "Duplicate $suffix"; faculty_id = $facId }
Assert-FieldError $r.Html 'already in use' 'duplicate department code error'

$r = Post-Form '/departments/form.php' @{ code = "bad code!$suffix"; name = "Bad $suffix"; faculty_id = $facId }
Assert-FieldError $r.Html 'letters, numbers, hyphens or slashes' 'malformed department code error'

# A head has to sit in the faculty that owns the department. The smoke faculty
# has no staff yet, so any existing lecturer is guaranteed to be a mismatch.
$otherLec = (Db 'SELECT id FROM university_management.lecturers LIMIT 1').Trim()
$r = Post-Form '/departments/form.php' @{ code = "SMH$suffix"; name = "Smoke Department $suffix"; faculty_id = $facId; head_id = $otherLec }
Assert-FieldError $r.Html 'different faculty' 'cross-faculty head rejected'

Write-Host "   -- delete guards --"
$r = Post-Form '/faculties/index.php' @{ action = 'delete'; delete_id = $facId }
Assert-Flash $r.Html 'cannot be deleted' 'faculty delete blocked by department'
Check ((Db "SELECT COUNT(*) FROM university_management.faculties WHERE id='$facId'") -eq 1) 'blocked faculty still present'

$r = Post-Form '/departments/index.php' @{ action = 'delete'; delete_id = $depId }
Assert-Flash $r.Html 'Department deleted' 'department delete'
$r = Post-Form '/faculties/index.php' @{ action = 'delete'; delete_id = $facId }
Assert-Flash $r.Html 'Faculty deleted' 'faculty delete'
Check ((Db "SELECT COUNT(*) FROM university_management.faculties WHERE code='$facCode'") -eq 0) 'smoke faculty removed from ledger'

$r = Http-Get '/faculties/index.php'
Assert-Status200 $r 'faculties list'
$r = Http-Get '/departments/index.php'
Assert-Status200 $r 'departments list'

Write-Host ""
Write-Host "========== $pass passed, $fail failed =========="
if ($fail -gt 0) { exit 1 }