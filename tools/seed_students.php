<?php
/**
 * Seed additional student records.
 *
 * Everything is generated locally from fixed name pools, so the script never
 * calls an outside service and cannot run into an API quota. It is safe to
 * re-run: it only adds the difference between the current count and the target.
 *
 * Usage:
 *     php tools/seed_students.php 2000
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/database.php';

$target = isset($argv[1]) ? max(0, (int) $argv[1]) : 2000;

$current = (int) db()->query('SELECT COUNT(*) AS n FROM students')->fetch_assoc()['n'];

if ($current >= $target) {
    fwrite(STDOUT, "Already at {$current} students; nothing to add.\n");
    exit(0);
}

$needed = $target - $current;
$nextId = (int) db()->query('SELECT COALESCE(MAX(id), 0) + 1 AS n FROM students')->fetch_assoc()['n'];

$maleFirst = [
    'Abdul', 'Abdulla', 'Abdulrahman', 'Adnan', 'Ahmed', 'Ali', 'Fahad', 'Faisal',
    'Hameed', 'Hassan', 'Hudhaifa', 'Hussain', 'Ibrahim', 'Jaleel', 'Latheef',
    'Mohamed', 'Musa', 'Naseem', 'Nashid', 'Rasheed', 'Sattar', 'Shiyam',
    'Thasmeen', 'Waheed', 'Yaasin', 'Yusuf', 'Zubair',
];

$femaleFirst = [
    'Aishath', 'Amina', 'Aneesa', 'Dhiyana', 'Fathima', 'Fathimath', 'Hafsa',
    'Halima', 'Hawwa', 'Iman', 'Khadheeja', 'Laila', 'Liyana', 'Maha', 'Mariyam',
    'Maryam', 'Muna', 'Naima', 'Naseera', 'Nuriya', 'Rawdha', 'Reena', 'Rifaa',
    'Ruhaina', 'Saida', 'Sana', 'Shahula', 'Shatha', 'Shifana', 'Sofiya',
    'Sufiya', 'Thasmeen', 'Thuhufa', 'Yumna', 'Zahra', 'Zaina', 'Zainab', 'Zara',
];

$lastNames = [
    'Abbas', 'Abdulla', 'Abdulrahman', 'Ahmed', 'Ali', 'Areef', 'Dheema',
    'Dhiyana', 'Didi', 'Faihaad', 'Faisal', 'Fazeel', 'Hameed', 'Hameez',
    'Hassan', 'Hudhaifa', 'Husain', 'Hussain', 'Hussein', 'Ibrahim', 'Ismail',
    'Jaleel', 'Kausar', 'Latheef', 'Latif', 'Lutfi', 'Mahamed', 'Mahud',
    'Mausoom', 'Mohamed', 'Moosa', 'Munthir', 'Naseem', 'Naseer', 'Nizam',
    'Nizar', 'Rasheed', 'Rashid', 'Rilwan', 'Saad', 'Saeed', 'Saeeda', 'Saleem',
    'Sattar', 'Shafeeu', 'Shareef', 'Shiyam', 'Usman', 'Waajid', 'Waheed',
    'Yaqoob', 'Yousuf', 'Zaki',
];

$departmentsByFaculty = [];
$departmentRows = db()->query('SELECT id, faculty_id FROM departments ORDER BY id');

while ($row = $departmentRows->fetch_assoc()) {
    $departmentsByFaculty[(int) $row['faculty_id']][] = (int) $row['id'];
}

if ($departmentsByFaculty === []) {
    fwrite(STDERR, "No departments exist, so students cannot be attached to one.\n");
    exit(1);
}

// Weight each faculty by its current share of students so the new records
// land where the register already has a presence.
$facultyWeights = [];
$facultyRows = db()->query('SELECT faculty_id, COUNT(*) AS n FROM students GROUP BY faculty_id');

while ($row = $facultyRows->fetch_assoc()) {
    $facultyWeights[(int) $row['faculty_id']] = max(1, (int) $row['n']);
}

foreach (array_keys($departmentsByFaculty) as $facultyId) {
    if (!isset($facultyWeights[$facultyId])) {
        $facultyWeights[$facultyId] = 1;
    }
}

$weightTotal = array_sum($facultyWeights);

$pickFaculty = static function (int $seed) use ($facultyWeights, $weightTotal): int {
    $position = ($seed * 37) % $weightTotal;

    foreach ($facultyWeights as $facultyId => $weight) {
        if ($position < $weight) {
            return $facultyId;
        }

        $position -= $weight;
    }

    return (int) array_key_first($facultyWeights);
};

$sql = 'INSERT INTO students
        (student_no, first_name, last_name, gender, date_of_birth, email, phone,
         faculty_id, department_id, enrollment_year, enrollment_date, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

$statement = db()->prepare($sql);
$types = 'sssssssiiiss';

db()->begin_transaction();

try {
    for ($i = 0; $i < $needed; $i++) {
        $id = $nextId + $i;

        $female = ($id % 2) === 0;
        $firstName = $female
            ? $femaleFirst[($id * 5) % count($femaleFirst)]
            : $maleFirst[($id * 5) % count($maleFirst)];
        $lastName = $lastNames[($id * 11) % count($lastNames)];

        $year = 2023 + ($id % 4);

        if ($year === 2023) {
            $status = ($id % 20) < 8 ? 'graduated' : (($id % 20) < 19 ? 'active' : 'suspended');
        } elseif ($year === 2024) {
            $status = ($id % 20) < 4 ? 'graduated' : (($id % 20) < 19 ? 'active' : 'suspended');
        } else {
            $status = ($id % 20) < 18 ? 'active' : 'suspended';
        }

        $facultyId = $pickFaculty($id);
        $departments = $departmentsByFaculty[$facultyId];
        $departmentId = $departments[$id % count($departments)];

        $studentNo = 'HU' . substr((string) $year, -2) . str_pad((string) $id, 3, '0', STR_PAD_LEFT);
        $gender = $female ? 'female' : 'male';
        $dob = sprintf('%04d-%02d-%02d', 2000 + (($id * 7) % 8), 1 + (($id * 3) % 12), 1 + (($id * 11) % 27));
        $email = strtolower(substr($firstName, 0, 1)) . '.' . strtolower($lastName) . $id . '@hagmah.edu';
        $phone = sprintf('+960 7%03d %04d', ($id * 17) % 1000, ($id * 29) % 10000);
        $enrollmentDate = sprintf('%04d-%02d-%02d', $year, 8 + (($id * 3) % 3), 1 + (($id * 7) % 27));

        $statement->bind_param(
            $types,
            $studentNo,
            $firstName,
            $lastName,
            $gender,
            $dob,
            $email,
            $phone,
            $facultyId,
            $departmentId,
            $year,
            $enrollmentDate,
            $status
        );

        $statement->execute();
    }

    db()->commit();
} catch (Throwable $exception) {
    db()->rollback();
    fwrite(STDERR, 'Seeding failed: ' . $exception->getMessage() . "\n");
    exit(1);
}

$total = (int) db()->query('SELECT COUNT(*) AS n FROM students')->fetch_assoc()['n'];

fwrite(STDOUT, "Added {$needed} students; the register now holds {$total}.\n");
