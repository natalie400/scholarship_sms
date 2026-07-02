<?php
$currentAdminPage = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

$isHome = $currentAdminPage === 'tempAdmin.php';
$isReports = $currentAdminPage === 'tempAdminReports.php';

$isPendingStudents = $currentAdminPage === 'tempPendingApp.php';
$isAcceptedStudents = $currentAdminPage === 'tempAcceptedApp.php';
$isRejectedStudents = $currentAdminPage === 'tempRejectedApp.php';

$scholarshipFilter = isset($_GET['scholarship']) ? $_GET['scholarship'] : '';
$isPendingScholarships = $currentAdminPage === 'tempScholarship.php' && $scholarshipFilter === 'Pending';
$isAcceptedScholarships = $currentAdminPage === 'tempScholarship.php' && $scholarshipFilter === 'Approved';
$isRejectedScholarships = $currentAdminPage === 'tempScholarship.php' && $scholarshipFilter === 'Rejected';

$isAdminUsers = $currentAdminPage === 'tempAdminShow.php';
$isSignatoryUsers = $currentAdminPage === 'tempSignatoryShow.php';
$isStudentUsers = $currentAdminPage === 'tempStudentShow.php';

if ($currentAdminPage === 'adminShowUser.php' && isset($_POST['showUser'])) {
  if ($_POST['showUser'] === 'showAdmin') {
    $isAdminUsers = true;
  } elseif ($_POST['showUser'] === 'showSig') {
    $isSignatoryUsers = true;
  } elseif ($_POST['showUser'] === 'showStudent') {
    $isStudentUsers = true;
  }
}
?>

<header class="app-header">
  <h1 class="app-logo"><a href="javascript:history.back()" class="app-btn">Back</a></h1>
  <nav class="app-nav">
    <ul>
      <li class="<?php echo $isHome ? 'current' : ''; ?>"><a href="tempAdmin.php">Home</a></li>
      <li class="submenu">
        <a href="#">Applications</a>
        <ul>
          <li class="<?php echo $isPendingStudents ? 'current' : ''; ?>"><a href="tempPendingApp.php">Pending Students</a></li>
          <li class="<?php echo $isAcceptedStudents ? 'current' : ''; ?>"><a href="tempAcceptedApp.php">Accepted Students</a></li>
          <li class="<?php echo $isRejectedStudents ? 'current' : ''; ?>"><a href="tempRejectedApp.php">Rejected Students</a></li>
        </ul>
      </li>
      <li class="submenu">
        <a href="tempScholarship.php">Scholarships</a>
        <ul>
          <li class="<?php echo $isPendingScholarships ? 'current' : ''; ?>"><a href="tempScholarship.php?scholarship=Pending">Pending Scholarships</a></li>
          <li class="<?php echo $isAcceptedScholarships ? 'current' : ''; ?>"><a href="tempScholarship.php?scholarship=Approved">Accepted Scholarships</a></li>
          <li class="<?php echo $isRejectedScholarships ? 'current' : ''; ?>"><a href="tempScholarship.php?scholarship=Rejected">Rejected Scholarships</a></li>
        </ul>
      </li>
      <li class="submenu">
        <a href="#">Users</a>
        <ul>
          <li class="<?php echo $isAdminUsers ? 'current' : ''; ?>"><a href="tempAdminShow.php">Admin</a></li>
          <li class="<?php echo $isSignatoryUsers ? 'current' : ''; ?>"><a href="tempSignatoryShow.php">Signatory</a></li>
          <li class="<?php echo $isStudentUsers ? 'current' : ''; ?>"><a href="tempStudentShow.php">Students</a></li>
        </ul>
      </li>
      <li class="<?php echo $isReports ? 'current' : ''; ?>"><a href="tempAdminReports.php">Reports</a></li>
      <li><a href="../backend/logout.php" class="app-btn">Logout</a></li>
    </ul>
  </nav>
</header>
