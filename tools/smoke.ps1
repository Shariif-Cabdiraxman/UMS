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
#     . (Join-Path $PSScriptRoot 'tools\smoke.ps1')
#     Login 'admin' 'Admin@123'
#     $r = Post-Form '/faculties/form.php' @{ code='fct-abc'; name='Faculty of Example' }
#     $r.Status
#     Diagnostics $r.Html
#
# Two settings are read from the environment so the suite is not tied to one
# machine. Both have defaults that suit a stock XAMPP install:
#
#     $env:UMS_BASE_URL  = 'http://localhost/university-management-system'
#     $env:UMS_MYSQL     = 'C:\xampp\mysql\bin\mysql.exe'
#     $env:UMS_DB_USER   = 'root'
#     $env:UMS_DB_PASS   = ''
#
# Note: Page returns the HTML string only. Use Http-Get when the status code
# matters, because the .Status property is not there to read.

$Global:Base = if ($env:UMS_BASE_URL) { $env:UMS_BASE_URL } else { "http://localhost/university-management-system" }
$Global:Jar  = Join-Path $env:TEMP "hagmah-cookies.txt"

# --- plumbing ---------------------------------------------------------------

function Find-Mysql {
    # An explicit path wins, then the usual XAMPP homes, then whatever is on
    # PATH. Resolved once and cached, since Db is called per assertion.
    if ($Global:MysqlExe) { return $Global:MysqlExe }

    $candidates = @($env:UMS_MYSQL, "C:\xampp\mysql\bin\mysql.exe", "C:\Program Files\XAMPP\mysql\bin\mysql.exe")
    foreach ($c in $candidates) {
        if ($c -and (Test-Path -LiteralPath $c)) {
            $Global:MysqlExe = $c
            return $c
        }
    }

    $onPath = Get-Command mysql.exe -ErrorAction SilentlyContinue
    if ($onPath) {
        $Global:MysqlExe = $onPath.Source
        return $Global:MysqlExe
    }

    throw "mysql.exe not found. Set `$env:UMS_MYSQL to its full path."
}

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
    # PHP renders a diagnostic as <b>Warning</b>: ... in ... on line 12. The
    # closing tag before the colon is what separates a real diagnostic from body
    # copy that merely contains the word: the sample data includes an
    # announcement titled "Notice: upgrade of the student records system", and a
    # plain 'Notice:' pattern reported that healthy page as broken.
    $pattern = '<b>(Fatal error|Warning|Notice|Deprecated|Parse error)</b>:|Uncaught|Stack trace|on line \d+'
    $hits = $Html -split "`n" | Select-String -Pattern $pattern
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
    $mysql = Find-Mysql
    $args = @("-u", $(if ($env:UMS_DB_USER) { $env:UMS_DB_USER } else { "root" }))
    if ($env:UMS_DB_PASS) { $args += "-p$($env:UMS_DB_PASS)" }
    $args += @("-N", "-e", $Query)
    return (& $mysql @args)
}

function Db-Count {
    param([string]$Table, [string]$Where = "")
    $sql = "SELECT COUNT(*) FROM university_management.$Table"
    if ($Where -ne "") { $sql += " WHERE $Where" }
    return [int](Db $sql)
}
