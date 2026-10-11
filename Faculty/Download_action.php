<?php
// Download_action.php
session_start();
include '../db_connect.php';

if (!isset($_SESSION['user_id'])) {
    die("Unauthorized access.");
}

$currentUserId = $_SESSION['user_id'];
$userPosition  = $_SESSION['position'] ?? 'Staff';
$userProgram   = $_SESSION['program'] ?? '';
$isProgramHead = ($userPosition === 'Program Head');
$isFaculty     = ($userPosition === 'Faculty');

$reportType = $_POST['report_type'] ?? 'general';
$dept = $_POST['department'] ?? ''; 
$ay   = $_POST['academic_year'] ?? '';
$sem  = $_POST['semester'] ?? '1st Semester';
$format = $_POST['format'] ?? 'pdf'; 

if (empty($ay) || empty($dept)) {
    die("Error: Missing required parameters.");
}

// ==========================================
// LOGIC FOR FACULTY WORKLOAD REPORT (URS-AA-F-05)
// ==========================================
if ($reportType === 'workload') {

    $facultyId      = $_POST['faculty_id'] ?? 0;
    $facultyNameRaw = trim($_POST['faculty_name'] ?? 'Unknown');

    // SECURITY OVERRIDE: Prevent Faculty from downloading other users' workload
    if ($isFaculty) {
        $facultyId = $currentUserId; 
    } else if ($isProgramHead) {
        // Ensure Program Head is downloading for someone in their own program
        $checkQuery = $conn->prepare("SELECT id FROM users WHERE id = ? AND program = ?");
        $checkQuery->bind_param("is", $facultyId, $userProgram);
        $checkQuery->execute();
        if ($checkQuery->get_result()->num_rows === 0) {
            die("Security Alert: You are not authorized to download workload forms for faculty outside of your program.");
        }
        $checkQuery->close();
    }

    $safeFilename = 'Workload_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $facultyNameRaw) . '_' . str_replace(' ', '_', $ay);

    // ── Parse name parts ──────────────────────────────
    $nameParts = explode(' ', $facultyNameRaw);
    $surname   = count($nameParts) > 1 ? strtoupper(array_pop($nameParts)) : strtoupper($nameParts[0] ?? '');
    $firstName = strtoupper(implode(' ', $nameParts) ?: '');
    $mi        = strtoupper($_POST['mi'] ?? '');

    // ── Collect optional online-fill fields ──────────
    $sex = $_POST['sex'] ?? ''; $empId = $_POST['emp_id'] ?? ''; $acadRank = $_POST['acad_rank'] ?? '';
    
    // Checkbox parsing for VSL and TL
    $vsl_mark = isset($_POST['vsl']) && $_POST['vsl'] === 'yes' ? 'X' : '  ';
    $tl_mark  = isset($_POST['tl']) && $_POST['tl'] === 'yes' ? 'X' : '  ';

    $natureAppt = $_POST['nature_appt'] ?? '';
    $degUndergrad = $_POST['deg_undergrad'] ?? ''; $ugMajor = $_POST['ug_major'] ?? ''; $ugMinor = $_POST['ug_minor'] ?? '';
    $degMasteral = $_POST['deg_masteral'] ?? ''; $maMajor = $_POST['ma_major'] ?? ''; $maMinor = $_POST['ma_minor'] ?? '';
    $degDoctoral = $_POST['deg_doctoral'] ?? ''; $eligibility = $_POST['eligibility'] ?? ''; $eligYear = $_POST['eligibility_year'] ?? '';
    $salMonthly = $_POST['salary_monthly'] ?? ''; $salAnnual = $_POST['salary_annual'] ?? ''; $salGradeStep = $_POST['salary_grade_step'] ?? '';
    $yrsUrs = $_POST['years_urs'] ?? ''; $yrsTeach = $_POST['years_teaching'] ?? ''; $yrsOther = $_POST['years_other'] ?? '';
    $specialTrain = $_POST['special_training'] ?? '';
    $collegeDept = !empty($_POST['college_dept']) ? trim($_POST['college_dept']) : $dept;
    $deanName = strtoupper($_POST['dean_name'] ?? ''); $dirName = strtoupper($_POST['director_name'] ?? '');
    $campus = 'Morong Campus';

    // ── Fetch schedule from DB ────────────────────────
    $sql = "SELECT s.*, c.course_code, c.descriptive_title, s.units, c.program AS course_program,
                   s.lecture_hours, s.laboratory_hours, r.room_name 
            FROM class_schedules s 
            JOIN courses c ON s.course_id = c.id 
            LEFT JOIN rooms r ON s.room_id = r.id
            WHERE s.academic_year = ? AND s.semester = ?
              AND s.faculty_id = ? AND s.department = ?";
    
    // Ensure Faculty only get Approved schedules
    if ($isFaculty) {
        $sql .= " AND s.approval_status = 'Approved'";
    }
              
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $ay, $sem, $facultyId, $dept);
    $stmt->execute();
    $schedules = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Dynamically retrieve user inputs arrays
    function getPostArr($key) { return isset($_POST[$key]) && is_array($_POST[$key]) ? $_POST[$key] : []; }
    $A2 = ['t'=>getPostArr('a2_time'), 'd'=>getPostArr('a2_day'), 'act'=>getPostArr('a2_act'), 'tgt'=>getPostArr('a2_target'), 'h'=>getPostArr('a2_hrs')];
    $B = ['t'=>getPostArr('b_time'), 'd'=>getPostArr('b_day'), 'des'=>getPostArr('b_desig'), 'plc'=>getPostArr('b_place'), 'h'=>getPostArr('b_hrs')];
    $C = ['t'=>getPostArr('c_time'), 'd'=>getPostArr('c_day'), 'top'=>getPostArr('c_topic'), 'tgt'=>getPostArr('c_target'), 'h'=>getPostArr('c_hrs')];
    $D = ['t'=>getPostArr('d_time'), 'd'=>getPostArr('d_day'), 'typ'=>getPostArr('d_type'), 'tgt'=>getPostArr('d_target'), 'h'=>getPostArr('d_hrs')];
    $E = ['t'=>getPostArr('e_time'), 'd'=>getPostArr('e_day'), 'typ'=>getPostArr('e_type'), 'tgt'=>getPostArr('e_target'), 'h'=>getPostArr('e_hrs')];
    $F = ['t'=>getPostArr('f_time'), 'd'=>getPostArr('f_day'), 'cod'=>getPostArr('f_code'), 'ttl'=>getPostArr('f_title'), 'un'=>getPostArr('f_units'), 'rm'=>getPostArr('f_room'), 'sec'=>getPostArr('f_sec'), 'sz'=>getPostArr('f_size')];
    $G = ['t'=>getPostArr('g_time'), 'd'=>getPostArr('g_day'), 'ttl'=>getPostArr('g_title'), 'sch'=>getPostArr('g_school'), 'h'=>getPostArr('g_hrs')];
    $H = ['t'=>getPostArr('h_time'), 'd'=>getPostArr('h_day'), 'loc'=>getPostArr('h_loc'), 'nat'=>getPostArr('h_nature')];
    $otAm=getPostArr('ot_am'); $otBrk=getPostArr('ot_brk'); $otPm=getPostArr('ot_pm'); $otOnH=getPostArr('ot_onsite_hrs'); $otOnS=getPostArr('ot_onsite_sched'); $otOff=getPostArr('ot_offsite_hrs');

    // ── PDF FORMAT (TCPDF) ──────────────────────────────────────
    require_once('../TCPDF-main/tcpdf.php');
    define('PG_W', 215.9); define('PG_H', 355.6); define('MG', 11.43); define('FOOT_H', 8.89); define('CW', PG_W - 2 * MG);
    class WorkloadPDF extends TCPDF {
        public function Footer() {
            $y = $this->getPageHeight() - MG * 0.55 - 3.5; $this->SetLineWidth(0.3); $this->SetDrawColor(0, 0, 0); $this->Line(MG, $y, $this->getPageWidth() - MG, $y); $this->SetFont('helvetica', '', 9); $this->SetTextColor(0, 0, 0); $colW = CW / 3.0; $this->SetXY(MG, $y + 1); $this->Cell($colW, 4, 'URS-AA-F-05', 0, 0, 'L'); $this->SetX(MG + $colW); $this->Cell($colW, 4, 'Rev. 01', 0, 0, 'C'); $this->SetX(MG + 2 * $colW); $this->Cell($colW, 4, 'Effectivity Date: June 28, 2024', 0, 0, 'R');
        }
    }
    $pdf = new WorkloadPDF('P', 'mm', [PG_W, PG_H]); $pdf->SetMargins(MG, MG, MG); $pdf->SetAutoPageBreak(true, MG + FOOT_H + 8); $pdf->SetFont('helvetica', '', 7.5); $pdf->SetCreator('COSMOS – URS'); $pdf->SetTitle($safeFilename); $pdf->setPrintHeader(false); $pdf->setPrintFooter(true);
    function mmH(float $pts): float { return $pts * 0.352778; }
    function sHdr(WorkloadPDF $pdf, string $text): void { if ($pdf->GetY() + 25 > $pdf->getPageHeight() - MG - FOOT_H - 8) { $pdf->AddPage(); } $pdf->SetFont('helvetica', 'B', 8); $pdf->Cell(CW, 4.5, $text, 0, 1, 'L'); }
    function drawRow(WorkloadPDF $pdf, array $cells, float $rowH, array $spans = []): void {
        if ($pdf->GetY() + $rowH > $pdf->getPageHeight() - MG - FOOT_H - 8) { $pdf->AddPage(); }
        $startY = $pdf->GetY(); $skipCols = []; $spanW = [];
        foreach ($spans as $sp) { $w = 0; for ($i = $sp['from']; $i <= $sp['to']; $i++) { $w += (float)($cells[$i][1] ?? 0); if ($i > $sp['from']) $skipCols[$i] = true; } $spanW[$sp['from']] = $w; }
        $x = (float)MG;
        foreach ($cells as $idx => $cell) {
            $colW = (float)($cell[1] ?? 0);
            if (isset($skipCols[$idx])) { $x += $colW; continue; }
            $text = (string)($cell[0] ?? ''); $drawW = isset($spanW[$idx]) ? (float)$spanW[$idx] : $colW; $align = (string)($cell[2] ?? 'L'); $bold = (bool)($cell[3] ?? false); $fontSize = (float)($cell[4] ?? 7.5); $fill = (bool)($cell[5] ?? false);
            $pdf->SetFont('helvetica', $bold ? 'B' : '', $fontSize); $pdf->SetFillColor($fill ? 240 : 255, $fill ? 240 : 255, $fill ? 240 : 255); $pdf->SetXY($x, $startY);
            $pdf->MultiCell($drawW, $rowH, $text, 1, $align, $fill, 0, $x, $startY, true, 0, false, true, $rowH, 'M'); $x += $colW;
        }
        $pdf->SetXY(MG, $startY + $rowH);
    }
    function hdrRow(WorkloadPDF $pdf, array $labels, array $widths, float $rowH, array $spans = []): void { $cells = []; foreach ($labels as $i => $lbl) { $cells[] = [(string)$lbl, (float)$widths[$i], 'C', true, 7.5, true]; } drawRow($pdf, $cells, $rowH, $spans); }
    
    $pdf->AddPage();
    $logoSize = 20; $textCol = 81.28; $sideCol = (CW - $textCol) / 2.0; $hY = $pdf->GetY();
    $ursLogo = __DIR__ . '/../LOGO_URS.jpg'; if (file_exists($ursLogo)) { $pdf->Image($ursLogo, MG + $sideCol - $logoSize - -3, $hY + 1, $logoSize, $logoSize); }
    
    $deptLogoMap = ['College of Science'=>'Logo COS.jpg', 'College of Education'=>'Logo COE.jpg', 'College of Industrial Technology'=>'Logo CIT.jpg', 'College of Engineering'=>'Logo COENG.jpg'];
    $deptLogoFile = $deptLogoMap[$collegeDept] ?? null; 
    if ($deptLogoFile && file_exists(__DIR__ . '/../' . $deptLogoFile)) { 
        $pdf->Image(__DIR__ . '/../' . $deptLogoFile, MG + $sideCol + $textCol + -3, $hY + 1, $logoSize, $logoSize); 
    }
    
    $cx = MG + $sideCol; $lh = 4.5;
    $pdf->SetXY($cx, $hY + 1); $pdf->SetFont('helvetica', '', 9); $pdf->Cell($textCol, $lh, 'Republic of the Philippines', 0, 2, 'C'); $pdf->SetFont('helvetica', 'B', 10); $pdf->Cell($textCol, $lh, 'UNIVERSITY OF RIZAL SYSTEM', 0, 2, 'C'); $pdf->SetFont('helvetica', '', 9); $pdf->Cell($textCol, $lh, 'Province of Rizal', 0, 2, 'C'); $pdf->Ln(1); $pdf->SetX($cx); $pdf->Cell($textCol, $lh, $collegeDept, 0, 2, 'C'); $pdf->SetX($cx); $pdf->Cell($textCol, $lh, $campus, 0, 2, 'C'); $pdf->SetXY(MG, $hY + $logoSize + 3);
    $pdf->SetFont('helvetica', 'B', 12); $pdf->Cell(CW, 6, 'FACULTY WORKLOAD', 0, 1, 'C'); $pdf->SetFont('helvetica', '', 10); $pdf->Cell(CW, 5, "$sem, Academic Year $ay", 0, 1, 'C'); $pdf->Ln(2);

    $c1=CW*0.06; $c2=CW*0.18; $c3=CW*0.20; $c4=CW*0.08; $c5=CW*0.12; $c6=CW*0.36;
    drawRow($pdf, [ ['Name:', $c1, 'L', false, 7.5], [$surname, $c2, 'C', true, 7.5], [$firstName, $c3, 'C', true, 7.5], [$mi, $c4, 'C', true, 7.5], ['Sex:', $c5, 'L', false, 7.5], [$sex, $c6, 'C', true, 7.5] ], mmH(18));
    drawRow($pdf, [ ['', $c1, 'L', false, 7.0], ['Surname', $c2, 'C', false, 7.0], ['First Name', $c3, 'C', false, 7.0], ['M.I.', $c4, 'C', false, 7.0], ['Employee ID No:', $c5, 'L', false, 7.0], [$empId, $c6, 'C', true, 7.5] ], mmH(14));
    
    drawRow($pdf, [ 
        ['Academic Rank', CW*0.14, 'L', false, 7.5], 
        [$acadRank, CW*0.24, 'C', false, 7.5], 
        ["[ $vsl_mark ] VSL     [ $tl_mark ] TL", CW*0.30, 'C', false, 7.5], 
        ["Nature of Appt:  $natureAppt", CW*0.32, 'L', false, 7.5] 
    ], mmH(18));

    $dg1=CW*0.16; $dg2=CW*0.40; $dg3=CW*0.22; $dg4=CW*0.22;
    drawRow($pdf, [ ['Degree(s) Earned:', $dg1, 'L', true, 7.5], ['', $dg2, 'L', false, 7.5], ['Major', $dg3, 'C', false, 7.5], ['Minor', $dg4, 'C', false, 7.5] ], mmH(16), [['from'=>0,'to'=>1]]);
    drawRow($pdf, [ ['  Undergraduate:', $dg1, 'L', false, 7.5], [$degUndergrad, $dg2, 'L', false, 7.5], [$ugMajor, $dg3, 'C', false, 7.5], [$ugMinor, $dg4, 'C', false, 7.5] ], mmH(16));
    drawRow($pdf, [ ['  Masteral:', $dg1, 'L', false, 7.5], [$degMasteral, $dg2, 'L', false, 7.5], [$maMajor, $dg3, 'C', false, 7.5], [$maMinor, $dg4, 'C', false, 7.5] ], mmH(16));
    drawRow($pdf, [ ['  Doctoral:', $dg1, 'L', false, 7.5], [$degDoctoral, $dg2, 'L', false, 7.5], ['', $dg3, 'C', false, 7.5], ['', $dg4, 'C', false, 7.5] ], mmH(16), [['from'=>2,'to'=>3]]);
    drawRow($pdf, [ ['Eligibility:', CW*0.10, 'L', false, 7.5], [$eligibility, CW*0.46, 'L', false, 7.5], ['Year Taken:', CW*0.12, 'L', false, 7.5], [$eligYear, CW*0.32, 'L', false, 7.5] ], mmH(16));
    drawRow($pdf, [ ['Monthly Salary:', CW*0.14, 'L', false, 7.5], [$salMonthly, CW*0.18, 'L', false, 7.5], ['Annual Salary:', CW*0.12, 'L', false, 7.5], [$salAnnual, CW*0.18, 'L', false, 7.5], ['Salary Grade:', CW*0.12, 'L', false, 7.5], [$salGradeStep, CW*0.14, 'L', false, 7.5], ['Salary Step:', CW*0.12, 'L', false, 7.5] ], mmH(16));
    $pdf->Ln(1);

    $ex1=CW*0.35; $ex2=CW*0.65;
    drawRow($pdf, [ ['Years of experience in URS:', $ex1, 'L', false, 7.5], [$yrsUrs, $ex2, 'L', false, 7.5] ], mmH(16));
    drawRow($pdf, [ ['Years of experience in Teaching at URS:', $ex1, 'L', false, 7.5], ["$yrsTeach          Other School:  $yrsOther", $ex2, 'L', false, 7.5] ], mmH(16));
    drawRow($pdf, [ ['Special Training/ Scholarship/ Study Grant:', $ex1, 'L', false, 7.5], [$specialTrain, $ex2, 'L', false, 7.5] ], mmH(16));
    $pdf->Ln(2);

    sHdr($pdf, 'A.1 INSTRUCTION');
    $a = [CW*0.12, CW*0.05, CW*0.11, CW*0.25, CW*0.07, CW*0.07, CW*0.12, CW*0.14, CW*0.07];
    hdrRow($pdf, ['TIME','DAY','COURSE CODE','DESCRIPTIVE TITLE','NO. OF UNITS/HRS','','ROOM','COURSE/YEAR & SEC','CLASS SIZE'], $a, mmH(28), [['from'=>4,'to'=>5]]);
    hdrRow($pdf, ['','','','','Lec','Lab','','',''], $a, mmH(14));

    $totalLec = 0.0; $totalLab = 0.0; $nData = max(count($schedules), 4);
    for ($i = 0; $i < $nData; $i++) {
        $s = $schedules[$i] ?? []; $lec = (float)($s['lecture_hours'] ?? 0); $lab = (float)($s['laboratory_hours'] ?? 0); $totalLec += $lec; $totalLab += $lab;
        $timeStr = $s ? date("g:i A", strtotime($s['start_time'])) . ' - ' . date("g:i A", strtotime($s['end_time'])) : '';
        $roomName = $s['room_name'] ?? '';
        drawRow($pdf, [[$timeStr, $a[0], 'C'], [strtoupper($s['days'] ?? ''), $a[1], 'C'], [$s['course_code'] ?? '', $a[2], 'C'], [$s['descriptive_title'] ?? '', $a[3], 'L'], [$lec > 0 ? (string)$lec : '', $a[4], 'C'], [$lab > 0 ? (string)$lab : '', $a[5], 'C'], [$roomName, $a[6], 'C'], [trim(($s['course_program'] ?? '') . ' ' . ($s['section'] ?? '')), $a[7], 'C'], ['', $a[8], 'C']], mmH(16));
    }
    drawRow($pdf, [ ['', $a[0], 'C', false, 7.5, true], ['', $a[1], 'C', false, 7.5, true], ['', $a[2], 'C', false, 7.5, true], ['TOTAL', $a[3], 'C', true, 7.5, true], [$totalLec > 0 ? (string)$totalLec : '', $a[4], 'C', true, 7.5, true], [$totalLab > 0 ? (string)$totalLab : '', $a[5], 'C', true, 7.5, true], ['15 units/ 23 hrs.', $a[6], 'L', true, 7.5, true], ['', $a[7], 'L', false, 7.5, true], ['', $a[8], 'L', false, 7.5, true] ], mmH(16), [['from'=>6,'to'=>8]]);
    $pdf->Ln(1.5);

    function drawDynRows($pdf, $widths, $arr, $keys, $min) {
        $c = max(count($arr[$keys[0]]), $min);
        for($i=0; $i<$c; $i++) {
            $cells = []; foreach($keys as $j=>$k) { $cells[] = [($arr[$k][$i]??''), $widths[$j], ($j<2?'C':'L')]; } drawRow($pdf, $cells, mmH(16));
        }
    }

    sHdr($pdf, 'A.2 INSTRUCTION'); $a2 = [CW*0.10, CW*0.08, CW*0.47, CW*0.22, CW*0.13];
    hdrRow($pdf, ['TIME','DAY','TYPE OF ACTIVITY','TARGET FOR THE SEMESTER','NO. OF HOURS/WK'], $a2, mmH(24));
    drawDynRows($pdf, $a2, $A2, ['t','d','act','tgt','h'], 2); $pdf->Ln(1.5);

    sHdr($pdf, 'B. ADMIN / OFFICE WORK DESIGNATION'); $b = [CW*0.07, CW*0.09, CW*0.42, CW*0.28, CW*0.14];
    hdrRow($pdf, ['TIME','DAY','CAMPUS / UNIVERSITY DESIGNATION','PLACE OF ASSIGNMENT','NO. OF HOURS/WK'], $b, mmH(24));
    drawDynRows($pdf, $b, $B, ['t','d','des','plc','h'], 3); $pdf->Ln(1.5);

    sHdr($pdf, 'C. RESEARCH / INVENTION / CREATIVE WORK'); $c = [CW*0.07, CW*0.09, CW*0.37, CW*0.33, CW*0.14];
    hdrRow($pdf, ['TIME','DAY','TYPE OF ACTIVITY / TOPIC','RESEARCH ASSIGNMENT FOR THE SEMESTER','NO. OF HOURS/WK'], $c, mmH(24));
    drawDynRows($pdf, $c, $C, ['t','d','top','tgt','h'], 3); $pdf->Ln(1.5);

    sHdr($pdf, 'D. EXTENSION / PRODUCTION'); $d2 = [CW*0.07, CW*0.09, CW*0.30, CW*0.40, CW*0.14];
    hdrRow($pdf, ['TIME','DAY','TYPE OF EXTENSION ACTIVITY',"EXTENSION / PRODUCTION\nACTIVITY TARGET FOR THE\nSEMESTER",'NO. OF HOURS/WK'], $d2, mmH(32));
    drawDynRows($pdf, $d2, $D, ['t','d','typ','tgt','h'], 2); $pdf->Ln(2);

    sHdr($pdf, 'E. PROFESSIONAL DEVELOPMENT'); $e = [CW*0.10, CW*0.08, CW*0.37, CW*0.33, CW*0.12];
    hdrRow($pdf, ['TIME','DAY',"TYPE OF PROFESSIONAL\nDEVELOPMENT ACTIVITY", "PROFESSIONAL\nDEVELOPMENT ACTIVITY\nTARGET FOR THE SEMESTER",'NO. OF HOURS/WK'], $e, mmH(32));
    drawDynRows($pdf, $e, $E, ['t','d','typ','tgt','h'], 2); $pdf->Ln(2);

    sHdr($pdf, 'OFFICIAL TIME (ON-SITE and OFF-SITE: 40 hours)'); $ot = [CW*0.14, CW*0.12, CW*0.12, CW*0.12, CW*0.14, CW*0.18, CW*0.18];
    hdrRow($pdf, ['','AM','Break Time','PM',"No. of Hours\nOn-site","Schedule of\nOn-site\nReporting","No. of Hours\nOff-site"], $ot, mmH(32));
    $otDays = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday', "Adjustment due to No. of\nPreparations"];
    foreach ($otDays as $i => $day) { drawRow($pdf, [ [$day, $ot[0], 'L', $day === 'TOTAL', 7.5, false], [$otAm[$i]??'', $ot[1], 'C'], [$otBrk[$i]??'', $ot[2], 'C'], [$otPm[$i]??'', $ot[3], 'C'], [$otOnH[$i]??'', $ot[4], 'C'], [$otOnS[$i]??'', $ot[5], 'C'], [$otOff[$i]??'', $ot[6], 'C'] ], mmH(16)); }
    drawRow($pdf, [ ['TOTAL', $ot[0], 'L', true], ['', $ot[1], 'C'], ['', $ot[2], 'C'], ['', $ot[3], 'C'], ['', $ot[4], 'C'], ['', $ot[5], 'C'], ['', $ot[6], 'C'] ], mmH(16));
    $pdf->Ln(2);

    sHdr($pdf, 'F. URS GRADUATE SCHOOL TEACHING LOAD (beyond Official Time of 40 hrs.)'); $f = [CW*0.08, CW*0.07, CW*0.11, CW*0.28, CW*0.12, CW*0.09, CW*0.14, CW*0.11];
    hdrRow($pdf, ['TIME','DAY','COURSE CODE','DESCRIPTIVE TITLE','NO. OF UNITS/HRS','ROOM','COURSE/YEAR & SEC','CLASS SIZE'], $f, mmH(28));
    drawDynRows($pdf, $f, $F, ['t','d','cod','ttl','un','rm','sec','sz'], 3);
    drawRow($pdf, [ ['', $f[0]], ['', $f[1]], ['', $f[2]], ['', $f[3]], ['TOTAL', $f[4], 'C', true], ['', $f[5]], ['', $f[6]], ['', $f[7]] ], mmH(16)); $pdf->Ln(1.5);

    sHdr($pdf, 'G. TEACHING LOAD OUTSIDE URS (beyond Official Time of 40 hrs.; not to exceed 9 hrs.)'); $g = [CW*0.08, CW*0.07, CW*0.45, CW*0.25, CW*0.15];
    hdrRow($pdf, ['TIME','DAY','SUBJECT & DESCRIPTIVE TITLE','SCHOOL','NO. OF HOURS/WK'], $g, mmH(24));
    drawDynRows($pdf, $g, $G, ['t','d','ttl','sch','h'], 3);
    drawRow($pdf, [ ['', $g[0]], ['', $g[1]], ['', $g[2]], ['TOTAL', $g[3], 'C', true], ['', $g[4]] ], mmH(16)); $pdf->Ln(1.5);

    sHdr($pdf, 'H. PRACTICE OF PROFESSION/CONDUCT OF BUSINESS OUTSIDE URS (beyond Official Time of 40 hrs.)'); $h = [CW*0.10, CW*0.08, CW*0.47, CW*0.35];
    hdrRow($pdf, ['TIME','DAY',"LOCATION OF PRACTICE OF PROFESSION /\nCONDUCT OF BUSINESS","NATURE OF PROFESSION /\nBUSINESS"], $h, mmH(24));
    drawDynRows($pdf, $h, $H, ['t','d','loc','nat'], 2); $pdf->Ln(3);

    $pdf->SetFont('helvetica', 'B', 8); $pdf->Cell(CW, 5, 'I hereby certify that the information indicated herein is true and correct.', 0, 1, 'C'); $pdf->Ln(6);

    $lw = CW * 0.50; $cw2 = CW * 0.05; $rw = CW * 0.45; $lh2 = 4.5;
    $sigRows = [
        ['Verified True and Correct:', '', strtoupper($facultyNameRaw), false, 'B'], 
        ['','','Signature over Printed Name', false, 'T'], 
        ['','','', false, 0],
        [$deanName, 'cc:', 'VPAA', true, 0], 
        ["Dean, $collegeDept", '', 'HRMO', false, 0], 
        ['','','Accounting', false, 0], 
        ['','','Dean', false, 0], 
        [$dirName, '', 'Faculty', true, 0], 
        ["Campus Director, $campus", '', '', false, 0],
    ];

    foreach ($sigRows as $i => $row) {
        [$left, $cc, $right, $lBold, $rBorder] = $row; $y = $pdf->GetY();
        $pdf->SetFont('helvetica', $lBold ? 'B' : '', 7.5); $pdf->SetXY(MG, $y); 
        $pdf->Cell($lw, $lh2, $left, 0, 0, 'L'); 
        $pdf->SetFont('helvetica', '', 7.5); $pdf->SetX(MG + $lw); $pdf->Cell($cw2, $lh2, $cc, 0, 0, 'R');
        $pdf->SetFont('helvetica', $i===0?'B':'', $i===0?8.5:7.5); $pdf->SetX(MG + $lw + $cw2); $pdf->Cell($rw, $lh2, $right, $rBorder, 1, $i<=1?'C':'L');
    }

    $pdf->Output($safeFilename . '.pdf', 'I');
    exit();
} 

// ==========================================
// LOGIC FOR GENERAL SCHEDULE REPORTS
// ==========================================
$sortBy   = $_POST['sort_by'] ?? 'Faculty'; // Always override to Faculty for this view.

$filename = "Schedule_" . $sortBy . "_" . $ay;

if ($format === 'excel') {
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=\"$filename.xls\"");
    header("Pragma: no-cache");
    header("Expires: 0");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($filename); ?></title>
    <?php if ($format === 'pdf'): ?>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
        <style>
            #loading-screen {
                display: flex; position: fixed; top: 0; left: 0;
                width: 100%; height: 100%; background: #f4f7fb;
                align-items: center; justify-content: center;
                flex-direction: column; z-index: 9999; font-family: sans-serif;
            }
        </style>
    <?php endif; ?>
    <style>
        body { font-family: Arial, sans-serif; font-size: 10pt; background: #fff; }
        #report-content { padding: 20px; background: white; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 30px; table-layout: fixed; }
        .data-table th, .data-table td { border: 1px solid #000; padding: 6px; vertical-align: top; text-align: center; word-wrap: break-word; }
        .header-row td { border: none; text-align: center; font-size: 11pt; padding: 2px; }
        .header-title { font-weight: bold; font-size: 12pt; }
        .room-header { background-color: #d9e1f2; font-weight: bold; height: 40px; }
        .vacant-cell { color: #888; font-size: 9pt; vertical-align: middle; background-color: #fcfcfc; }
        .vacant-tag { color: #cc0000; font-size: 8pt; font-weight: bold; display: block; margin-top: 5px; }
        .time-label { font-size: 8pt; color: #555; }
        .restricted-text { color: #ef4444; font-weight: bold; }
    </style>
</head>
<body>

<?php if ($format === 'pdf'): ?>
<div id="loading-screen"><h2 style="color: #1b5088;">Generating your PDF... Please wait.</h2></div>
<?php endif; ?>

<div id="report-content">
<?php
// === FACULTY PERSONAL SCHEDULE ONLY ===
$orderBy = 's.start_time ASC';

$sql = "SELECT s.*, c.course_code, c.descriptive_title, c.units, c.program AS course_program,
               s.lecture_hours, s.laboratory_hours, 
               TRIM(CONCAT(u.first_name, ' ', COALESCE(u.middle_initial, ''), ' ', u.surname)) AS full_name,
               r.room_name 
        FROM class_schedules s 
        JOIN courses c ON s.course_id = c.id
        LEFT JOIN users u ON s.faculty_id = u.id
        LEFT JOIN rooms r ON s.room_id = r.id
        WHERE s.academic_year = '$ay' AND s.semester = '$sem' 
          AND s.faculty_id = " . intval($currentUserId) . " 
          AND s.approval_status = 'Approved'
        ORDER BY $orderBy";

$data        = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
$groupedData = [];

foreach ($data as $row) {
    if (empty($row['full_name']) || $row['faculty_id'] == 0)
        $row['full_name'] = 'TBA';
    elseif ($row['faculty_id'] == -1)
        $row['full_name'] = 'General Education';
    
    $key = $row['full_name'];
    $groupedData[$key][] = $row;
}

if (empty($groupedData))
    echo "<h3 style='text-align:center;'>No schedules found for this selection.</h3>";

foreach ($groupedData as $groupName => $rows) {
    $sumLec = 0; $sumLab = 0; $sumUnits = 0;

    echo "<div style='page-break-after: always;'><table style='width:100%; border:none; margin-bottom:10px;'>";
    echo "<tr class='header-row'><td colspan='10'>Republic of the Philippines</td></tr>";
    echo "<tr class='header-row'><td colspan='10' class='header-title'>UNIVERSITY OF RIZAL SYSTEM</td></tr>";
    echo "<tr class='header-row'><td colspan='10'>Morong, Rizal</td></tr>";
    echo "<tr class='header-row'><td colspan='10' style='height:10px;'></td></tr>";
    echo "<tr class='header-row'><td colspan='10' class='header-title'>" . strtoupper($dept) . "</td></tr>";
    echo "<tr class='header-row'><td colspan='10'>AY $ay, $sem</td></tr>";
    echo "<tr class='header-row'><td colspan='10' style='height:10px;'></td></tr>";
    echo "<tr class='header-row'><td colspan='10' style='text-align:center; font-weight:bold;'>FACULTY: " . strtoupper($groupName) . "</td></tr>";
    echo "</table>";

    echo "<table class='data-table'><thead>
          <tr>
            <th rowspan='2'>TIME</th><th rowspan='2'>DAY</th>
            <th rowspan='2'>COURSE CODE</th><th rowspan='2'>DESCRIPTIVE TITLE</th>
            <th colspan='2'>NO. OF HOURS/WEEK</th>
            <th rowspan='2'>UNITS</th><th rowspan='2'>PROFESSOR</th>
            <th rowspan='2'>ROOM</th><th rowspan='2'>SECTION</th>
          </tr>
          <tr><th>LEC</th><th>LAB</th></tr>
          </thead><tbody>";

    foreach ($rows as $row) {
        $timeStr  = (new DateTime($row['start_time']))->format('g:i A') . ' - ' . (new DateTime($row['end_time']))->format('g:i A');
        $lecHour  = (float)$row['lecture_hours'];
        $labHour  = (float)$row['laboratory_hours'];
        $sumLec  += $lecHour;
        $sumLab  += $labHour;
        $sumUnits+= $row['units'];
        $roomDisp = $row['room_name'] ?? 'TBA';

        echo "<tr>
                <td>{$timeStr}</td>
                <td>" . strtoupper($row['days']) . "</td>
                <td>{$row['course_code']}</td>
                <td>{$row['descriptive_title']}</td>
                <td>" . ($lecHour > 0 ? $lecHour : '') . "</td>
                <td>" . ($labHour > 0 ? $labHour : '') . "</td>
                <td>{$row['units']}</td>
                <td>{$row['full_name']}</td>
                <td>{$roomDisp}</td>
                <td>{$row['section']}</td>
              </tr>";
    }

    echo "<tr style='font-weight:bold; background-color:#f0f0f0;'>
            <td colspan='4' style='text-align:right; padding-right:10px;'>TOTAL</td>
            <td>" . ($sumLec > 0 ? $sumLec : '-') . "</td>
            <td>" . ($sumLab > 0 ? $sumLab : '-') . "</td>
            <td>{$sumUnits}</td>
            <td colspan='3' style='background-color:#ccc;'></td>
          </tr>";
    echo "</tbody></table></div>";
}

?>
</div>

<?php if ($format === 'pdf' && $reportType === 'general'): ?>
<script>
    // Automate the HTML to PDF conversion when the page loads
    window.onload = function() {
        const element = document.getElementById('report-content');
        html2pdf().set({
            margin: [0.5, 0.5, 0.5, 0.5],
            filename: '<?php echo addslashes($filename); ?>.pdf',
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2, useCORS: true },
            jsPDF: { unit: 'in', format: 'legal', orientation: 'landscape' }
        }).from(element).save().then(() => {
            document.getElementById('loading-screen').innerHTML = "<h2 style='color: #10b981;'>Download Complete!</h2><p>You can now close this tab.</p>";
            setTimeout(() => { window.close(); }, 2500); 
        });
    };
</script>
<?php endif; ?>

</body>
</html>