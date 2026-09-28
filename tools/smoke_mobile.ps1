# Smoke tests for the phone shell.
#
# The mobile version is not a second application: it is the same pages drawn
# through a different shell, chosen by includes/mobile.php from the request. So
# this suite is not a parallel copy of smoke_modules.ps1. It is the smaller set
# of checks that only make sense for a phone:
#
#   - the shell is actually chosen, and the desktop shell is not
#   - the phone's own furniture is present on every kind of screen
#   - a create-and-delete round trip still works, which is the proof that the
#     shell changed nothing about the request
#   - the choice of shell sticks, and can be overridden in both directions
#
# Arrives as a phone: a mobile user agent plus the viewport cookie that
# assets/js/mobile.js writes on a real device, so the detection is exercised
# the way it is in use rather than only through ?view=mobile.
#
# Dot-sources smoke.ps1 for the HTTP + DB harness.

$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'smoke.ps1')

$pass = 0
$fail = 0

function Check {
    param([bool]$Cond, [string]$Msg)
    if ($Cond) { $script:pass++; Write-Host "  ok  $Msg" }
    else { $script:fail++; Write-Host " FAIL $Msg" }
}

function Assert-Status200 {
    param($Result, [string]$Msg)
    Check ($Result.Status -eq 200) "$Msg (status $($Result.Status))"
}

function Assert-Clean {
    param($Result, [string]$Msg)
    $d = Diagnostics $Result.Html
    Check ($null -eq $d -and $Result.Html -ne '') "$Msg (diagnostics: $d)"
}

function Assert-Has {
    param($Result, [string]$Needle, [string]$Msg)
    Check ($Result.Html -match [regex]::Escape($Needle)) "$Msg (looking for: $Needle)"
}

function Assert-Lacks {
    param($Result, [string]$Needle, [string]$Msg)
    Check ($Result.Html -notmatch [regex]::Escape($Needle)) "$Msg (must not contain: $Needle)"
}

function Is-MobileShell {
    param($Result)
    return $Result.Html -match 'class="app mobile"'
}

# The furniture every phone screen is expected to carry.
$shellNeedles = @(
    'assets/css/mobile.css',
    'assets/js/mobile.js',
    'manifest.webmanifest',
    'viewport-fit=cover',
    'class="mbar"',
    'class="mtabbar"',
    'class="mfoot"'
)

$Global:Agent = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 ' +
    '(KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1'

# What assets/js/mobile.js writes on a real device, so the detection is
# exercised the way it is in use rather than only through ?view=mobile.
$Viewport = 'ums_viewport=390'

Write-Host "== Phone identity =="
Check (Login 'admin' 'Admin@123') 'admin signs in from a phone'

$r = Http-Get '/dashboard.php' -Cookie $Viewport
Assert-Status200 $r 'dashboard with a 390px viewport reported'
Check (Is-MobileShell $r) 'a phone-width viewport is given the mobile shell'
Assert-Clean $r 'dashboard renders without diagnostics'
foreach ($needle in $shellNeedles) { Assert-Has $r $needle "dashboard has $needle" }
Assert-Lacks $r 'class="sidebar"' 'dashboard has no navigation rail'
Assert-Has $r 'id="mcontent"' 'dashboard main region is the phone one'

Write-Host "== Screens in the phone shell =="

# A list, a form and a detail screen: the three shapes a page can take.
$listPaths = @('/students/index.php', '/lecturers/index.php', '/courses/index.php',
    '/enrollments/index.php', '/grades/index.php', '/announcements/index.php',
    '/faculties/index.php', '/departments/index.php', '/users/index.php')

foreach ($path in $listPaths) {
    $r = Http-Get $path
    Assert-Status200 $r $path
    Assert-Clean $r "$path renders"
    Check (Is-MobileShell $r) "$path uses the mobile shell"
    Assert-Has $r 'class="msearch"' "$path has the phone search box"
}

$r = Http-Get '/students/index.php'
Assert-Has $r 'class="mfilters"' 'student list folds its filters'
Assert-Has $r 'class="mpager"' 'student list has the phone pager'
Assert-Has $r 'mpager__step--next' 'pager offers next'

$r = Http-Get '/students/index.php?status=active'
Assert-Has $r '<details class="mfilters" open>' 'a set filter opens the fold'
Assert-Has $r 'class="mfilters__count"' 'a set filter is counted'

$formPaths = @('/students/form.php', '/lecturers/form.php', '/courses/form.php',
    '/enrollments/form.php', '/grades/form.php', '/announcements/form.php',
    '/faculties/form.php', '/departments/form.php', '/users/form.php')

foreach ($path in $formPaths) {
    $r = Http-Get $path
    Assert-Status200 $r $path
    Assert-Clean $r "$path renders"
    Check (Is-MobileShell $r) "$path uses the mobile shell"
    Assert-Has $r 'class="formactions"' "$path has the sticky save bar"
}

$sNo = (Db "SELECT student_no FROM university_management.students ORDER BY id LIMIT 1").Trim()
$r = Http-Get "/students/view.php?id=$sNo"
Assert-Status200 $r "student record ($sNo)"
Assert-Clean $r 'student record renders'
Check (Is-MobileShell $r) 'student record uses the mobile shell'

Write-Host "== The module index =="
$r = Http-Get '/m/index.php'
Assert-Status200 $r '/m/index.php'
Assert-Clean $r 'module index renders'
Check (Is-MobileShell $r) 'module index uses the mobile shell'
Assert-Has $r 'class="mgriditem"' 'module index lists modules'
Assert-Has $r 'aria-current="page"' 'the All tab is marked current'
foreach ($module in @('faculties/index.php', 'departments/index.php', 'lecturers/index.php',
    'students/index.php', 'courses/index.php', 'enrollments/index.php', 'grades/index.php',
    'announcements/index.php', 'users/index.php')) {
    Assert-Has $r $module "module index links to $module"
}

Write-Host "== A write still works through the phone shell =="

# The point of the phone suite: the shell changes the drawing, not the request.
$suffix = Get-Random -Minimum 1000 -Maximum 9999
$code = "mob$suffix"
$r = Post-Form '/faculties/form.php' @{ code = $code; name = "Mobile smoke $suffix" }
Assert-Status200 $r 'faculty created from a phone'
Assert-Has $r 'Faculty added.' 'faculty create flash'
Assert-Has $r 'class="mtabbar"' 'the create response is still a phone page'

$id = [int](Db "SELECT id FROM university_management.faculties WHERE code = '$code'").Trim()
Check ($id -gt 0) "faculty row created (id $id)"

$r = Http-Get '/m/index.php'
Assert-Lacks $r $code 'the module index lists modules, not records'

# Deletes post to the module's list screen, which is where the shared delete
# path lives, so the phone reaches the same place the desktop does.
$r = Post-Form '/faculties/index.php' @{ action = 'delete'; delete_id = $id }
Assert-Has $r 'Faculty deleted.' 'faculty delete flash from a phone'
Check (-not (Db "SELECT id FROM university_management.faculties WHERE code = '$code'")) 'faculty row removed'

Write-Host "== Switching shell =="

# ?view=desktop is the escape hatch in the phone footer, and it has to stick so
# that following a link does not bounce the reader back to the phone shell.
$r = Http-Get '/dashboard.php?view=desktop'
Check (-not (Is-MobileShell $r)) '?view=desktop gives the desktop shell'
Assert-Has $r 'class="sidebar"' 'the desktop shell has its navigation rail'
Assert-Lacks $r 'assets/css/mobile.css' 'the desktop shell does not load the phone stylesheet'

$r = Http-Get '/courses/index.php'
Check (-not (Is-MobileShell $r)) 'the desktop choice survives the next page'

$r = Http-Get '/courses/index.php?view=mobile'
Check (Is-MobileShell $r) '?view=mobile gives the phone shell again'
Assert-Has $r 'class="mpager"' 'the phone pager returns with it'

$r = Http-Get '/dashboard.php'
Check (Is-MobileShell $r) 'the phone choice survives the next page'

Write-Host "== A desktop visitor is not shown the phone screen =="

# A new session, because the shell choice above was deliberately remembered in
# this one and would otherwise carry over.
$Global:Agent = $null
Check (Login 'admin' 'Admin@123') 'admin signs in from a desktop'

# The module index is the phone's answer to the navigation rail, so a desktop
# that asks for it is sent to the dashboard rather than shown a layout it has no
# stylesheet for.
$r = Http-Get '/m/index.php'
Check ($r.Status -eq 302) "/m/index.php sends a desktop on (status $($r.Status))"
Assert-Lacks $r 'class="mgriditem"' 'the module index is not drawn for a desktop'

$r = Http-Get '/dashboard.php'
Assert-Has $r 'pagehead__title' 'the dashboard is drawn for a desktop'
Assert-Lacks $r 'class="mtabbar"' 'a desktop dashboard has no bottom bar'
Assert-Lacks $r 'class="mfoot"' 'a desktop dashboard has no phone footer'

Write-Host "== The installable app =="
$manifest = (Http-Get '/manifest.webmanifest').Html
Check ($manifest -match '"display"\s*:\s*"standalone"') 'the manifest asks to be installed as an app'
Check ($manifest -match '"name"\s*:') 'the manifest is named'
Check ($manifest -match 'start_url') 'the manifest has somewhere to start'
$worker = (Http-Get '/sw.js').Html
Check ($worker -match 'self\.addEventListener') 'the service worker is served'
Check ($worker -match "request\.mode === 'navigate'") 'the worker never caches a page'

Write-Host ""
Write-Host "========== $pass passed, $fail failed =========="
if ($fail -gt 0) { exit 1 }
