# HTTP smoke tests for the University Management System.
#
# A small harness rather than a test framework: the application is a set of
# server-rendered pages, so the things worth checking are status codes, PHP
# diagnostics in the response, validation messages, flash messages and the rows
# in the database afterwards. All of that is easy to assert from the outside.
#
# curl.exe is used with a cookie jar because it follows a POST -> 302 -> GET the
# same way a browser does, which is exactly the round trip every form makes.
#
# Usage, from any test script:
#
#     . "C:\xampp\htdocs\university-management-system\tools\smoke.ps1"
#     Login 'admin' 'Admin@123'
#     $r = Post-Form '/faculties/form.php' @{ code='fct-abc'; name='Faculty of Example' }
#     $r.Status
#     Diagnostics $r.Html
#
# Note: Page returns the HTML string only. Use Http-Get when the status code
# matters, because the .Status property is not there to read.

$Global:Base = "http://localhost/university-management-system"
$Global:Jar  = Join-Path $env:TEMP "hagmah-cookies.txt"

# --- plumbing ---------------------------------------------------------------

function Reset-Session {
    param([string]$Jar = $Global:Jar)
    if (Test-Path $Jar) { Remove-Item -LiteralPath $Jar -Force }
}

function Http-Get {
    param([string]$Path, [switch]$Raw)
    $url = if ($Path -like "http*") { $Path } else { $Global:Base + $Path }
    $args = @("-s", "-b", $Global:Jar, "-c", $Global:Jar, "-w", "`n__STATUS__%{http_code}")
    if ($Raw) { $args += "--raw" }
    $args += $url
    $out = & curl.exe @args
    return (Split-Http $out)
}

function Http-Post {
    param([string]$Path, [hashtable]$Body, [switch]$NoFollow)
    $url = if ($Path -like "http*") { $Path } else { $Global:Base + $Path }
    $args = @("-s", "-b", $Global:Jar, "-c", $Global:Jar, "-L", "-w", "`n__STATUS__%{http_code}")
    if ($NoFollow) { $args = $args -replace "-L", "" }
    $args += "-e"
    $args += $url
    foreach ($key in $Body.Keys) {
        $args += "--data-urlencode"
        $args += ("{0}={1}" -f $key, $Body[$key])
    }
    $args += $url
    $out = & curl.exe @args
    return (Split-Http $out)
}

function Split-Http {
    param($Lines)
    $status = 0
    $html = ($Lines -join "`n")
    if ($html -match "__STATUS__(\d{3})\s*$") {
        $status = [int]$Matches[1]
        $html = $html.Substring(0, $html.LastIndexOf("__STATUS__")).TrimEnd()
    }
    return [pscustomobject]@{ Status = $status; Html = $html }
}

# --- session ----------------------------------------------------------------

function Get-Csrf {
    param([string]$Path)
    $page = Http-Get $Path
    return [regex]::Match($page.Html, 'name="csrf_token" value="([^"]+)"').Groups[1].Value
}

function Login {
    param([string]$User, [string]$Password)
    Reset-Session
    $token = Get-Csrf '/auth/login.php'
    $r = Http-Post '/auth/login.php' @{ csrf_token = $token; identifier = $User; password = $Password }
    return ($r.Html -match 'pagehead__title')
}

function Page {
    param([string]$Path)
    return (Http-Get $Path).Html
}

# --- forms ------------------------------------------------------------------

function Post-Form {
    param([string]$Path, [hashtable]$Extra = @{})
    $token = Get-Csrf $Path
    $body = @{ csrf_token = $token }
    foreach ($key in $Extra.Keys) { $body[$key] = $Extra[$key] }
    return (Http-Post $Path $body)
}

# --- assertions -------------------------------------------------------------

function Diagnostics {
    param([string]$Html)
    if (-not $Html) { return "NO OUTPUT" }
    $hits = $Html -split "`n" | Select-String -Pattern 'Fatal error|Warning:|Notice:|Deprecated:|Uncaught|Parse error|Stack trace'
    if ($hits) { return (($hits | ForEach-Object { ($_.Line -replace '\s+', ' ').Trim() }) -join ' || ') }
    return $null
}

function Error-Text {
    param([string]$Html)
    $texts = $Html -split "`n" | Select-String -Pattern 'class="field__error"|flash--(error|warning)'
    if ($texts) { return ((($texts | ForEach-Object { ($_.Line -replace '<[^>]+>', ' ') -replace '\s+', ' ' }).Trim()) -join ' | ') }
    return ''
}

function Flash-Text {
    param([string]$Html)
    $m = [regex]::Match($Html, '(?s)class="flash__text">(.*?)</p>')
    if ($m.Success) { return (($m.Groups[1].Value -replace '<[^>]+>', '') -replace '\s+', ' ').Trim() }
    return ''
}

function Table-Row-Count {
    param([string]$Html)
    $i = $Html.IndexOf('<tbody>')
    if ($i -lt 0) { return 0 }
    $j = $Html.IndexOf('</tbody>', $i)
    $body = $Html.Substring($i, $j - $i)
    return ([regex]::Matches($body, '<tr')).Count
}

# --- database ---------------------------------------------------------------

function Db {
    param([string]$Query)
    $mysql = "C:\xampp\mysql\bin\mysql.exe"
    if (-not (Test-Path $mysql)) { throw "mysql.exe not found" }
    return (& $mysql -u root -N -e $Query)
}

function Db-Count {
    param([string]$Table, [string]$Where = "")
    $sql = "SELECT COUNT(*) FROM university_management.$Table"
    if ($Where -ne "") { $sql += " WHERE $Where" }
    return [int](Db $sql)
}
