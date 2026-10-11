<#
.SYNOPSIS
    Sroor ERP - wipe the LOCAL test environment (sroor_local_* databases only).

.DESCRIPTION
    Lists every database whose name starts with sroor_local_ on the local MySQL server
    (127.0.0.1) and drops them after you type RESET. No other database is ever touched:
    each name is re-checked against ^sroor_local_[a-z0-9_]+$ before its DROP.

    Optionally restores backend/.env from a backup written by setup-local.ps1, which puts
    you back on your previous configuration (e.g. the sqlite dev setup).

    Afterwards run setup-local.ps1 again for a fresh environment.

.PARAMETER DryRun
    Only list what would be dropped / restored.

.PARAMETER RestoreEnv
    Path of a backend/.env.backup-<timestamp> file to copy back over backend/.env
    (the current .env is first saved as .env.backup-<timestamp> too).

.PARAMETER KeepDatabases
    Do not drop anything (use with -RestoreEnv to only switch .env back).

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File D:\projects\sroor\scripts\local\reset-local.ps1 -DryRun
    powershell -ExecutionPolicy Bypass -File D:\projects\sroor\scripts\local\reset-local.ps1
    powershell -ExecutionPolicy Bypass -File D:\projects\sroor\scripts\local\reset-local.ps1 -KeepDatabases -RestoreEnv D:\projects\sroor\backend\.env.backup-20261010-120000
#>
[CmdletBinding()]
param(
    [switch]$DryRun,
    [string]$RestoreEnv = '',
    [switch]$KeepDatabases
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$RepoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$Backend = Join-Path $RepoRoot 'backend'
$EnvFile = Join-Path $Backend '.env'
$SafeName = '^sroor_local_[a-z0-9_]+$'

function Find-Mysql {
    $cmd = Get-Command mysql.exe -ErrorAction SilentlyContinue
    if ($null -ne $cmd) { return $cmd.Source }
    $candidate = Get-ChildItem 'C:\laragon\bin\mysql\*\bin\mysql.exe' -ErrorAction SilentlyContinue | Sort-Object FullName -Descending | Select-Object -First 1
    if ($null -eq $candidate) { throw 'mysql.exe not found (PATH or C:\laragon\bin\mysql).' }
    return $candidate.FullName
}

$MysqlExe = Find-Mysql
$DbUser = 'root'
$DbPassword = ''

function Invoke-Sql([string]$sql) {
    $previousPreference = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try {
        if ($script:DbPassword -ne '') { $env:MYSQL_PWD = $script:DbPassword }
        $out = & $MysqlExe --protocol=TCP -h 127.0.0.1 -P 3306 -u $script:DbUser -N -B -e $sql 2>$null
        if ($LASTEXITCODE -ne 0) { return $null }
        $lines = @($out | Where-Object { $null -ne $_ } | ForEach-Object { "$_".Trim() })
        return ,([string[]]$lines)
    } finally {
        $ErrorActionPreference = $previousPreference
        Remove-Item Env:MYSQL_PWD -ErrorAction SilentlyContinue
    }
}

if ($DryRun) { Write-Host 'DRY RUN: nothing will be dropped or restored.' -ForegroundColor DarkYellow }

# ---------------------------------------------------------------- databases
if (-not $KeepDatabases) {
    if ($null -eq (Invoke-Sql 'SELECT 1')) {
        Write-Host 'root without password refused; enter the LOCAL MySQL account (input hidden).'
        $DbUser = Read-Host 'Local MySQL user'
        $secure = Read-Host -AsSecureString 'Local MySQL password'
        $bstr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure)
        try { $DbPassword = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($bstr) } finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($bstr) }
        if ($null -eq (Invoke-Sql 'SELECT 1')) { throw 'cannot connect to the local MySQL server.' }
    }

    $names = Invoke-Sql "SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME LIKE 'sroor\_local\_%' ORDER BY SCHEMA_NAME"
    if ($null -eq $names) { throw 'could not list databases.' }
    $names = @($names | Where-Object { $_ -match $SafeName })

    if ($names.Count -eq 0) {
        Write-Host 'No sroor_local_* database found.'
    } else {
        Write-Host 'Databases that will be DROPPED:'
        foreach ($n in $names) { Write-Host "  - $n" }
        if ($DryRun) {
            Write-Host "[dry-run] would drop $($names.Count) database(s)."
        } else {
            $answer = Read-Host 'Type RESET to drop them'
            if ($answer -cne 'RESET') { Write-Host 'Aborted. Nothing dropped.'; exit 1 }
            foreach ($n in $names) {
                if ($n -notmatch $SafeName) { throw "refusing to drop '$n'." }
                if ($null -eq (Invoke-Sql "DROP DATABASE IF EXISTS $n")) { throw "failed to drop $n." }
                Write-Host "  dropped $n"
            }
        }
    }
}

# ---------------------------------------------------------------- .env restore
if ($RestoreEnv -ne '') {
    $source = (Resolve-Path -LiteralPath $RestoreEnv).Path
    if ((Split-Path $source -Leaf) -notlike '.env.backup-*') { throw 'RestoreEnv must point to a .env.backup-<timestamp> file.' }
    if ($DryRun) {
        Write-Host "[dry-run] would save the current .env as a new backup, then restore $source"
    } else {
        if (Test-Path $EnvFile) {
            $save = Join-Path $Backend (".env.backup-" + (Get-Date -Format 'yyyyMMdd-HHmmss'))
            Copy-Item -LiteralPath $EnvFile -Destination $save
            Write-Host "current .env saved as $save"
        }
        Copy-Item -LiteralPath $source -Destination $EnvFile -Force
        Push-Location $Backend
        try { & php artisan config:clear } finally { Pop-Location }
        Write-Host "restored backend/.env from $source"
    }
}

Write-Host ''
Write-Host 'Tenant files live in backend/storage/tenant<slug>/ (delete them by hand if you want a clean slate).'
Write-Host 'Run setup-local.ps1 again to rebuild the environment.'
