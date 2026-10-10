<#
.SYNOPSIS
    Sroor ERP - set up (or update) the LOCAL multi-tenant test environment on Laragon.

.DESCRIPTION
    Idempotent. Safe to re-run: every step checks the current state first.
      1. Preflight: php >= 8.3, Laragon mysql client, npm, vendor/, the env template.
      2. Detects local MySQL credentials (Laragon default: root without password).
      3. Backs up backend/.env to backend/.env.backup-<timestamp>, then merges
         backend/.env.local-test.example into it (APP_KEY and existing secrets untouched).
      4. Creates ONLY the database sroor_local_central (tenant DBs sroor_local_tenant_<slug>
         are created by the tenant provisioner). Never drops or alters other databases.
      5. php artisan migrate (central) + tenants:migrate for existing tenants.
      6. Seeds central permissions + plan catalog (both idempotent seeders).
      7. Creates the central super-admin (central:create-super-admin, hidden password prompt).
      8. Provisions tenants demo + shop2 through ProvisionTenantAction
         (scripts/local/provision-local-tenant.php), domains <slug>.sroor.test + <slug>.localhost.
      9. Fills tenant demo with the realistic 1-year dataset (tenant:populate-realistic-data),
         only when it has no invoices yet.
     10. storage:link, frontend build (npm run build), smoke checks, next steps.

    Never connects to any remote server. Never prints secret values.

.PARAMETER DryRun
    Read-only: run the checks and print what would change. No file, DB or build change.

.PARAMETER SuperAdminEmail
    Email of the local platform operator (asked when omitted).

.PARAMETER SkipSuperAdmin
    Do not create the central super-admin.

.PARAMETER SkipDemoData
    Do not generate the realistic dataset for any tenant.

.PARAMETER Shop2DemoData
    Also generate the realistic dataset for shop2 (default: shop2 stays empty, which makes
    tenant-isolation checks obvious).

.PARAMETER SkipBuild
    Do not run npm run build.

.PARAMETER DevServer
    You will use "npm run dev" (Vite HMR): skip the build and keep public/hot.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File D:\projects\sroor\scripts\local\setup-local.ps1 -DryRun
    powershell -ExecutionPolicy Bypass -File D:\projects\sroor\scripts\local\setup-local.ps1 -SuperAdminEmail owner@sroor.test
#>
[CmdletBinding()]
param(
    [switch]$DryRun,
    [string]$SuperAdminEmail = '',
    [string]$SuperAdminName = 'Local Super Admin',
    [switch]$SkipSuperAdmin,
    [switch]$SkipDemoData,
    [switch]$Shop2DemoData,
    [switch]$SkipBuild,
    [switch]$DevServer
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

# ----------------------------------------------------------------------------- constants
$RepoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$Backend = Join-Path $RepoRoot 'backend'
$Artisan = Join-Path $Backend 'artisan'
$EnvFile = Join-Path $Backend '.env'
$EnvExample = Join-Path $Backend '.env.example'
$TemplateFile = Join-Path $Backend '.env.local-test.example'
$ProvisionScript = Join-Path $PSScriptRoot 'provision-local-tenant.php'

$CentralDb = 'sroor_local_central'
$TenantPrefix = 'sroor_local_tenant_'
$DbHost = '127.0.0.1'
$DbPort = '3306'

$Tenants = @(
    @{ Slug = 'demo';  Name = 'Demo Store (local)'; Email = 'admin@demo.sroor.test';  Phone = '01000000201'; DemoData = (-not $SkipDemoData) },
    @{ Slug = 'shop2'; Name = 'Shop 2 (local)';     Email = 'admin@shop2.sroor.test'; Phone = '01000000202'; DemoData = ($Shop2DemoData -and -not $SkipDemoData) }
)

# Keys whose existing non-empty value is never replaced by the template.
$SecretKeyPattern = '(PASSWORD|SECRET|TOKEN|_KEY$|_KEY_|DSN|^APP_KEY$)'

# ----------------------------------------------------------------------------- helpers
function Write-Step([string]$text) { Write-Host ''; Write-Host "==> $text" -ForegroundColor Cyan }
function Write-Ok([string]$text) { Write-Host "    [ok] $text" -ForegroundColor Green }
function Write-Info([string]$text) { Write-Host "    $text" }
function Write-Warn2([string]$text) { Write-Host "    [warn] $text" -ForegroundColor Yellow }
function Write-Plan([string]$text) { Write-Host "    [dry-run] would $text" -ForegroundColor DarkYellow }

function Stop-Setup([string]$text) {
    Write-Host ''
    Write-Host "[setup-local] ERROR: $text" -ForegroundColor Red
    exit 1
}

function Assert-SafeDbName([string]$name) {
    if ($name -notmatch '^sroor_local_[a-z0-9_]+$') { Stop-Setup "refusing database name '$name' (must match sroor_local_*)." }
}

function Find-Php {
    $cmd = Get-Command php.exe -ErrorAction SilentlyContinue
    if ($null -eq $cmd) {
        $candidate = Get-ChildItem 'C:\laragon\bin\php\php-8.4*\php.exe' -ErrorAction SilentlyContinue | Sort-Object FullName -Descending | Select-Object -First 1
        if ($null -eq $candidate) { Stop-Setup 'php.exe not found (PATH or C:\laragon\bin\php).' }
        return $candidate.FullName
    }
    return $cmd.Source
}

function Find-Mysql {
    $cmd = Get-Command mysql.exe -ErrorAction SilentlyContinue
    if ($null -ne $cmd) { return $cmd.Source }
    $candidate = Get-ChildItem 'C:\laragon\bin\mysql\*\bin\mysql.exe' -ErrorAction SilentlyContinue | Sort-Object FullName -Descending | Select-Object -First 1
    if ($null -eq $candidate) { Stop-Setup 'mysql.exe not found (PATH or C:\laragon\bin\mysql).' }
    return $candidate.FullName
}

function Find-Npm {
    $cmd = Get-Command npm.cmd -ErrorAction SilentlyContinue
    if ($null -eq $cmd) { return $null }
    return $cmd.Source
}

$script:MysqlExe = $null
$script:DbUser = 'root'
$script:DbPassword = ''

# Runs one SQL statement on the local server; returns trimmed stdout lines, or $null on error.
# SQL passed here must only contain single quotes (Windows PowerShell 5.1 argument quoting).
function Invoke-Sql([string]$sql) {
    $previous = $env:MYSQL_PWD
    $previousPreference = $ErrorActionPreference
    # PS 5.1 turns redirected native stderr into errors; never let that abort the script.
    $ErrorActionPreference = 'Continue'
    try {
        if ($script:DbPassword -ne '') { $env:MYSQL_PWD = $script:DbPassword } else { Remove-Item Env:MYSQL_PWD -ErrorAction SilentlyContinue }
        $out = & $script:MysqlExe --protocol=TCP -h $DbHost -P $DbPort -u $script:DbUser -N -B -e $sql 2>$null
        if ($LASTEXITCODE -ne 0) { return $null }
        $lines = @($out | Where-Object { $null -ne $_ } | ForEach-Object { "$_".Trim() })
        return ,([string[]]$lines)   # the comma keeps a 1-element result an array
    } catch {
        return $null
    } finally {
        $ErrorActionPreference = $previousPreference
        if ($null -ne $previous) { $env:MYSQL_PWD = $previous } else { Remove-Item Env:MYSQL_PWD -ErrorAction SilentlyContinue }
    }
}

function Test-DatabaseExists([string]$name) {
    Assert-SafeDbName $name
    $rows = Invoke-Sql "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '$name'"
    return ($null -ne $rows -and $rows.Count -gt 0 -and $rows[0] -eq '1')
}

function Get-SqlCount([string]$sql) {
    $rows = Invoke-Sql $sql
    if ($null -eq $rows -or $rows.Count -eq 0) { return -1 }
    $n = 0
    if ([int]::TryParse($rows[0], [ref]$n)) { return $n }
    return -1
}

# Call as a plain statement (never assign or pipe it): the artisan process then keeps the
# console, so interactive prompts (hidden password input) work. Exit code: $script:ArtisanExit.
$script:ArtisanExit = 0
function Invoke-Artisan([string[]]$arguments, [switch]$AllowFail) {
    Push-Location $Backend
    try {
        & $script:PhpExe $Artisan @arguments
        $script:ArtisanExit = $LASTEXITCODE
    } finally {
        Pop-Location
    }
    if ($script:ArtisanExit -ne 0 -and -not $AllowFail) { Stop-Setup "php artisan $($arguments -join ' ') failed (exit $($script:ArtisanExit))." }
}

function ConvertFrom-SecureToPlain([Security.SecureString]$secure) {
    $bstr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure)
    try { return [Runtime.InteropServices.Marshal]::PtrToStringBSTR($bstr) }
    finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($bstr) }
}

function Read-NewPassword([string]$prompt, [int]$minLength) {
    for ($i = 0; $i -lt 3; $i++) {
        $a = ConvertFrom-SecureToPlain (Read-Host -AsSecureString "$prompt ($minLength+ chars)")
        $b = ConvertFrom-SecureToPlain (Read-Host -AsSecureString 'Repeat it')
        if ($a.Length -lt $minLength) { Write-Warn2 "too short (minimum $minLength)."; continue }
        if ($a -cne $b) { Write-Warn2 'the two entries differ.'; continue }
        return $a
    }
    Stop-Setup 'no valid password entered.'
}

# --- .env parsing / merge (values are never printed for secret keys) ---------------
function Get-TemplateEntries([string]$path) {
    $entries = New-Object System.Collections.Generic.List[object]
    foreach ($line in [System.IO.File]::ReadAllLines($path)) {
        if ($line -match '^\s*([A-Z][A-Z0-9_]*)\s*=(.*)$') {
            $entries.Add([pscustomobject]@{ Key = $Matches[1]; Value = $Matches[2].Trim(); Line = "$($Matches[1])=$($Matches[2].Trim())" })
        }
    }
    return $entries
}

function Get-EnvValue([string[]]$lines, [string]$key) {
    foreach ($line in $lines) {
        if ($line -match "^\s*$key\s*=(.*)$") { return $Matches[1].Trim().Trim('"').Trim("'") }
    }
    return $null
}

function Test-SecretKey([string]$key) {
    if ($key -match '_URL$') { return $false }   # e.g. CENTRAL_PASSWORD_RESET_URL is a public URL
    return ($key -match $SecretKeyPattern)
}

# Returns @{ Lines = merged lines; Changes = list of human-readable changes (no secret values) }
function Merge-EnvLines([string[]]$current, $template, [hashtable]$forced) {
    $lines = New-Object System.Collections.Generic.List[string]
    foreach ($l in $current) { $lines.Add($l) }
    $changes = New-Object System.Collections.Generic.List[string]
    $appended = New-Object System.Collections.Generic.List[string]

    foreach ($entry in $template) {
        $key = $entry.Key
        if ($key -eq 'APP_KEY') { continue }
        $newValue = $entry.Value
        $isForced = $forced.ContainsKey($key)
        if ($isForced) { $newValue = $forced[$key] }
        $newLine = "$key=$newValue"
        $secret = Test-SecretKey $key

        $found = $false
        for ($i = 0; $i -lt $lines.Count; $i++) {
            if ($lines[$i] -match "^\s*$key\s*=(.*)$") {
                $found = $true
                $old = $Matches[1].Trim()
                if ($secret -and -not $isForced -and $old -ne '') { continue }   # keep existing secret
                if ($lines[$i] -ne $newLine) {
                    $lines[$i] = $newLine
                    if ($secret) { $changes.Add("set $key (secret, value hidden)") } else { $changes.Add("set $key=$newValue") }
                }
            }
        }
        if (-not $found) {
            $appended.Add($newLine)
            if ($secret) { $changes.Add("add $key (secret, value hidden)") } else { $changes.Add("add $key=$newValue") }
        }
    }

    if ($appended.Count -gt 0) {
        $lines.Add('')
        $lines.Add('# --- sroor local-test keys added by scripts/local/setup-local.ps1 ---')
        foreach ($l in $appended) { $lines.Add($l) }
    }
    return @{ Lines = $lines.ToArray(); Changes = $changes.ToArray() }
}

# ============================================================================ 1. preflight
Write-Host "Sroor ERP local setup  (repo: $RepoRoot)" -ForegroundColor White
if ($DryRun) { Write-Host 'DRY RUN: nothing will be written, created, migrated or built.' -ForegroundColor DarkYellow }

Write-Step 'Preflight'
$script:PhpExe = Find-Php
$phpVersion = (& $script:PhpExe -r 'echo PHP_VERSION;')
if ([version]($phpVersion -replace '[^0-9.].*$', '') -lt [version]'8.3') { Stop-Setup "PHP $phpVersion found; 8.3+ required." }
Write-Ok "php $phpVersion ($($script:PhpExe))"

foreach ($ext in @('pdo_mysql', 'bcmath', 'intl', 'mbstring', 'zip')) {
    $loaded = (& $script:PhpExe -r "echo extension_loaded('$ext') ? 'yes' : 'no';")
    if ($loaded -ne 'yes') { Write-Warn2 "PHP CLI extension '$ext' is not loaded (enable it in Laragon > PHP > Extensions)." }
}

$script:MysqlExe = Find-Mysql
Write-Ok "mysql client $($script:MysqlExe)"

$NpmExe = Find-Npm
if ($null -eq $NpmExe) { Write-Warn2 'npm.cmd not found: the frontend build step will be skipped.' } else { Write-Ok "npm $NpmExe" }

if (-not (Test-Path (Join-Path $Backend 'vendor\autoload.php'))) { Stop-Setup 'backend/vendor missing: run "composer install" in backend/ first.' }
if (-not (Test-Path $TemplateFile)) { Stop-Setup "template not found: $TemplateFile" }
Write-Ok 'backend/vendor and env template present'

$overridden = @('APP_ENV', 'APP_URL', 'DB_CONNECTION', 'DB_DATABASE', 'DB_HOST', 'DB_USERNAME', 'TENANT_DB_PREFIX', 'CENTRAL_DOMAIN', 'CENTRAL_ADMIN_DOMAINS') |
    Where-Object { -not [string]::IsNullOrEmpty([Environment]::GetEnvironmentVariable($_)) }
if (@($overridden).Count -gt 0) {
    Write-Warn2 "these variables are set in your shell and override .env: $($overridden -join ', '). Remove them before testing."
}

# ============================================================================ 2. mysql
Write-Step 'Local MySQL'
$version = Invoke-Sql 'SELECT VERSION()'
if ($null -ne $version) {
    Write-Ok "connected as root without password (Laragon default), server $($version[0])"
} else {
    Write-Warn2 'root without password was refused. Enter the LOCAL MySQL account to use (input hidden).'
    if ($DryRun) { Stop-Setup 'cannot continue the dry run without database access.' }
    $script:DbUser = Read-Host 'Local MySQL user (e.g. root)'
    $script:DbPassword = ConvertFrom-SecureToPlain (Read-Host -AsSecureString 'Local MySQL password')
    $version = Invoke-Sql 'SELECT VERSION()'
    if ($null -eq $version) { Stop-Setup 'could not connect to the local MySQL server with those credentials.' }
    Write-Ok "connected as $($script:DbUser), server $($version[0])"
}

$existingLocal = Invoke-Sql "SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME LIKE 'sroor\_local\_%' ORDER BY SCHEMA_NAME"
if ($null -ne $existingLocal -and $existingLocal.Count -gt 0) {
    Write-Info "existing sroor_local_* databases: $($existingLocal -join ', ')"
} else {
    Write-Info 'no sroor_local_* database yet'
}

# ============================================================================ 3. .env
Write-Step 'backend/.env'
$template = Get-TemplateEntries $TemplateFile
$forced = @{ DB_USERNAME = $script:DbUser; DB_PASSWORD = $script:DbPassword; DB_DATABASE = $CentralDb; TENANT_DB_PREFIX = $TenantPrefix }

$envExists = Test-Path $EnvFile
if ($envExists) {
    $raw = [System.IO.File]::ReadAllText($EnvFile)
} elseif (Test-Path $EnvExample) {
    Write-Warn2 '.env missing: it will be created from .env.example (then key:generate).'
    $raw = [System.IO.File]::ReadAllText($EnvExample)
} else {
    Stop-Setup 'neither backend/.env nor backend/.env.example exists.'
}
$newline = "`n"
if ($raw.Contains("`r`n")) { $newline = "`r`n" }
$currentLines = $raw -split "\r?\n"
if ($currentLines.Count -gt 0 -and $currentLines[-1] -eq '') { $currentLines = $currentLines[0..($currentLines.Count - 2)] }

$merge = Merge-EnvLines $currentLines $template $forced
$appKey = Get-EnvValue $merge.Lines 'APP_KEY'
$needsKey = [string]::IsNullOrEmpty($appKey)

if ($merge.Changes.Count -eq 0 -and $envExists) {
    Write-Ok '.env already matches the local-test topology'
} elseif ($DryRun) {
    foreach ($c in $merge.Changes) { Write-Plan $c }
    if ($envExists) { Write-Plan 'back up .env to backend/.env.backup-<timestamp> first' }
} else {
    if ($envExists) {
        $backup = Join-Path $Backend (".env.backup-" + (Get-Date -Format 'yyyyMMdd-HHmmss'))
        if (Test-Path $backup) { Stop-Setup "backup target already exists: $backup" }
        Copy-Item -LiteralPath $EnvFile -Destination $backup
        Write-Ok "backup: $backup  (restore: Copy-Item it back over backend\.env)"
        $git = Get-Command git.exe -ErrorAction SilentlyContinue
        if ($null -ne $git) {
            & $git.Source -C $RepoRoot check-ignore -q -- ("backend/" + (Split-Path $backup -Leaf))
            if ($LASTEXITCODE -ne 0) { Write-Warn2 'the .env backup is NOT git-ignored: never stage it (add ".env.backup-*" to .gitignore).' }
        }
    }
    $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllText($EnvFile, (($merge.Lines -join $newline) + $newline), $utf8NoBom)
    foreach ($c in $merge.Changes) { Write-Info $c }
    Write-Ok ".env updated ($($merge.Changes.Count) change(s))"
}

if ($needsKey) {
    if ($DryRun) { Write-Plan 'run php artisan key:generate (APP_KEY is empty)' } else { Invoke-Artisan @('key:generate', '--force') }
}

if ($DryRun) { Write-Plan 'run php artisan config:clear + route:clear' } else {
    Invoke-Artisan @('config:clear')
    Invoke-Artisan @('route:clear')
}

# ---------------------------------------------------------------- 3b. .env.testing (CI parity)
# With APP_ENV=testing (phpunit.xml) Laravel loads backend/.env.testing instead of .env when it
# exists. CI runs the suite with ".env.example copied to .env"; mirroring that here keeps the
# local-test keys (CENTRAL_ADMIN_DOMAINS, TENANT_DB_PREFIX, QUICK_LOGIN_ENABLED, ...) out of
# PHPUnit. Only a file carrying our marker is ever (re)written; a hand-made one is left alone.
Write-Step 'backend/.env.testing (PHPUnit = CI parity)'
$EnvTesting = Join-Path $Backend '.env.testing'
$TestingMarker = '# GENERATED by scripts/local/setup-local.ps1 from .env.example (CI parity). Safe to delete.'
if (-not (Test-Path $EnvExample)) {
    Write-Warn2 '.env.example missing: .env.testing not generated; PHPUnit will read the local-test .env.'
} else {
    $mine = $true
    if (Test-Path $EnvTesting) {
        $first = @(Get-Content -LiteralPath $EnvTesting -TotalCount 1)
        $mine = ($first.Count -gt 0 -and $first[0] -eq $TestingMarker)
    }
    if (-not $mine) {
        Write-Info 'a hand-made .env.testing exists: left untouched'
    } else {
        $content = $TestingMarker + "`n" + [System.IO.File]::ReadAllText($EnvExample)
        $same = (Test-Path $EnvTesting) -and ([System.IO.File]::ReadAllText($EnvTesting) -eq $content)
        if ($same) { Write-Ok 'up to date' }
        elseif ($DryRun) { Write-Plan 'write backend/.env.testing = marker + .env.example (no secrets; git-ignored)' }
        else {
            [System.IO.File]::WriteAllText($EnvTesting, $content, (New-Object System.Text.UTF8Encoding($false)))
            Write-Ok 'written (php artisan test now ignores the local-test .env, like CI)'
        }
    }
}

# ============================================================================ 4. central DB
Write-Step "Central database $CentralDb"
Assert-SafeDbName $CentralDb
if (Test-DatabaseExists $CentralDb) {
    Write-Ok 'exists'
} elseif ($DryRun) {
    Write-Plan "CREATE DATABASE IF NOT EXISTS $CentralDb (utf8mb4 / utf8mb4_unicode_ci)"
} else {
    $r = Invoke-Sql "CREATE DATABASE IF NOT EXISTS $CentralDb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
    if ($null -eq $r) { Stop-Setup "could not create $CentralDb." }
    Write-Ok 'created'
}

# ============================================================================ 5-6. migrate + seed
Write-Step 'Central migrations and seeders'
if ($DryRun) {
    Write-Plan 'php artisan migrate --force --no-interaction'
    Write-Plan 'php artisan db:seed --class=Database\Seeders\CentralPermissionsSeeder --force'
    Write-Plan 'php artisan db:seed --class=Database\Seeders\PlansAndFeaturesSeeder --force'
} else {
    Invoke-Artisan @('migrate', '--force', '--no-interaction')
    Invoke-Artisan @('db:seed', '--class=Database\Seeders\CentralPermissionsSeeder', '--force', '--no-interaction')
    Invoke-Artisan @('db:seed', '--class=Database\Seeders\PlansAndFeaturesSeeder', '--force', '--no-interaction')
    Write-Ok 'central schema migrated, permissions and plans seeded'
}

$centralReady = Test-DatabaseExists $CentralDb
$tenantCount = -1
if ($centralReady) { $tenantCount = Get-SqlCount "SELECT COUNT(*) FROM $CentralDb.tenants" }
if ($tenantCount -gt 0) {
    if ($DryRun) { Write-Plan "php artisan tenants:migrate --force ($tenantCount tenant(s))" }
    else { Invoke-Artisan @('tenants:migrate', '--force'); Write-Ok 'tenant databases migrated' }
}

# ============================================================================ 7. super-admin
Write-Step 'Central super-admin (platform console)'
if ($SkipSuperAdmin) {
    Write-Info 'skipped (-SkipSuperAdmin)'
} else {
    if ($SuperAdminEmail -eq '' -and -not $DryRun) { $SuperAdminEmail = Read-Host 'Super-admin email (e.g. owner@sroor.test)' }
    $SuperAdminEmail = $SuperAdminEmail.Trim().ToLowerInvariant()
    if ($SuperAdminEmail -eq '') {
        Write-Plan 'ask for the super-admin email, then run central:create-super-admin <email> (hidden password prompt, 12+ chars)'
    } else {
        if ($SuperAdminEmail -notmatch '^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$') { Stop-Setup 'invalid super-admin email.' }
        $exists = -1
        if ($centralReady) { $exists = Get-SqlCount "SELECT COUNT(*) FROM $CentralDb.central_users WHERE email = '$SuperAdminEmail'" }
        if ($exists -gt 0) {
            Write-Ok "$SuperAdminEmail already exists"
        } elseif ($DryRun) {
            Write-Plan "php artisan central:create-super-admin $SuperAdminEmail --name=`"$SuperAdminName`" (hidden password prompt, 12+ chars)"
        } else {
            Write-Info 'Choose the operator password (12+ chars). It is asked twice and never shown.'
            Invoke-Artisan @('central:create-super-admin', $SuperAdminEmail, "--name=$SuperAdminName")
        }
    }
}

# ============================================================================ 8. tenants
Write-Step 'Tenants demo + shop2'
$passwordSet = $false
try {
    foreach ($t in $Tenants) {
        $slug = $t.Slug
        Assert-SafeDbName ($TenantPrefix + $slug)
        $known = -1
        if ($centralReady) { $known = Get-SqlCount "SELECT COUNT(*) FROM $CentralDb.tenants WHERE id = '$slug'" }

        if ($DryRun) {
            if ($known -gt 0) { Write-Ok "$slug exists (would only add missing local domains)" }
            else { Write-Plan "provision tenant $slug -> DB $TenantPrefix$slug, domains $slug.sroor.test + $slug.localhost, admin $($t.Email) / $($t.Phone)" }
            continue
        }

        if ($known -le 0 -and -not $passwordSet) {
            Write-Info 'Choose ONE password for the local tenant admins (admin@demo.sroor.test, admin@shop2.sroor.test).'
            $env:SROOR_LOCAL_TENANT_PASSWORD = Read-NewPassword 'Tenant admin password' 8
            $passwordSet = $true
        }

        & $script:PhpExe $ProvisionScript $slug $t.Name $t.Email $t.Phone
        if ($LASTEXITCODE -ne 0) { Stop-Setup "provisioning tenant $slug failed." }
    }
} finally {
    Remove-Item Env:SROOR_LOCAL_TENANT_PASSWORD -ErrorAction SilentlyContinue
}

# ============================================================================ 9. demo data
Write-Step 'Realistic demo data'
foreach ($t in $Tenants) {
    $slug = $t.Slug
    if (-not $t.DemoData) { Write-Info "$slug : skipped"; continue }
    $db = $TenantPrefix + $slug
    Assert-SafeDbName $db
    $invoices = -1
    if (Test-DatabaseExists $db) { $invoices = Get-SqlCount "SELECT COUNT(*) FROM $db.invoices" }
    if ($invoices -gt 0) { Write-Ok "$slug already has $invoices invoice(s); not regenerated"; continue }
    if ($DryRun) { Write-Plan "php artisan tenant:populate-realistic-data $slug (no --fresh; it refuses if data exists)"; continue }
    Write-Info "generating 1 year of data for $slug (takes a few minutes; it prints the generated staff password once)..."
    Invoke-Artisan @('tenant:populate-realistic-data', $slug) -AllowFail
    if ($script:ArtisanExit -ne 0) { Write-Warn2 "populate-realistic-data for $slug exited with $($script:ArtisanExit) (see output above)." } else { Write-Ok "$slug populated" }
}

# ============================================================================ 10. storage link
Write-Step 'Storage link'
if (Test-Path (Join-Path $Backend 'public\storage')) { Write-Ok 'public/storage exists' }
elseif ($DryRun) { Write-Plan 'php artisan storage:link' }
else { Invoke-Artisan @('storage:link') }

# ============================================================================ 11. frontend
Write-Step 'Frontend assets'
$hotFile = Join-Path $Backend 'public\hot'
$viteListening = $null -ne (Get-NetTCPConnection -LocalPort 5173 -State Listen -ErrorAction SilentlyContinue)
if ($DevServer) {
    Write-Info 'DevServer mode: run "npm run dev" in backend/ and keep it open (HMR on localhost:5173).'
} else {
    if ((Test-Path $hotFile) -and -not $viteListening) {
        if ($DryRun) { Write-Plan 'delete the stale backend/public/hot (no Vite dev server is running; pages would load assets from it and stay blank)' }
        else {
            Remove-Item -LiteralPath $hotFile
            Write-Warn2 'deleted stale backend/public/hot (git-ignored Vite marker; npm run dev recreates it).'
        }
    }
    if ($SkipBuild -or $null -eq $NpmExe) {
        Write-Info 'build skipped'
    } elseif ($DryRun) {
        Write-Plan 'npm run build in backend/ (runs lang:export first)'
    } else {
        Push-Location $Backend
        try {
            if (-not (Test-Path 'node_modules')) { & $NpmExe ci; if ($LASTEXITCODE -ne 0) { Stop-Setup 'npm ci failed.' } }
            & $NpmExe run build
            if ($LASTEXITCODE -ne 0) { Stop-Setup 'npm run build failed.' }
        } finally { Pop-Location }
        Write-Ok 'assets built into public/build'
    }
}

# ============================================================================ 12. smoke checks
Write-Step 'Smoke checks (local HTTP only)'
$checks = @(
    @{ Url = 'http://sroor.test/up'; Expect = 200 },
    @{ Url = 'http://admin.sroor.test/api/v1/ping'; Expect = 200 },
    @{ Url = 'http://demo.sroor.test/api/v1/ping'; Expect = 200 },
    @{ Url = 'http://shop2.sroor.test/api/v1/ping'; Expect = 200 },
    @{ Url = 'http://demo.sroor.test/super-admin/login'; Expect = 404 },
    @{ Url = 'http://sroor.test/api/v1/central/tenants/resolve?code=demo'; Expect = 200 }
)
foreach ($c in $checks) {
    $hostName = ([uri]$c.Url).Host
    $resolved = $null
    try { $resolved = [System.Net.Dns]::GetHostAddresses($hostName) | Where-Object { $_.ToString() -in @('127.0.0.1', '::1') } } catch { $resolved = $null }
    if ($null -eq $resolved) { Write-Warn2 "$hostName does not resolve to this machine yet: run add-hosts.ps1 as Administrator."; continue }
    if ($DryRun) { Write-Plan "GET $($c.Url) (expect $($c.Expect))"; continue }
    $status = 0
    try {
        $status = [int](Invoke-WebRequest -Uri $c.Url -UseBasicParsing -TimeoutSec 15).StatusCode
    } catch {
        if ($null -ne $_.Exception.Response) { $status = [int]$_.Exception.Response.StatusCode } else { $status = -1 }
    }
    if ($status -eq $c.Expect) { Write-Ok "$($c.Url) -> $status" }
    else { Write-Warn2 "$($c.Url) -> $status (expected $($c.Expect)). Did you reload Apache from Laragon?" }
}

# ============================================================================ next steps
Write-Step 'Next steps'
Write-Info 'Tenant workspaces : http://demo.sroor.test/login   http://shop2.sroor.test/login'
Write-Info '                    admin@demo.sroor.test / admin@shop2.sroor.test (or 01000000201 / 01000000202)'
Write-Info 'Platform console  : http://admin.sroor.test/super-admin/login  (2FA setup with an authenticator app on first sign-in)'
Write-Info 'Central resolver  : http://sroor.test/connect'
Write-Info 'Queue worker      : cd backend; php artisan queue:work --tries=3 --timeout=120'
Write-Info 'Scheduler         : cd backend; php artisan schedule:work'
Write-Info 'Mail / reset link : backend/storage/logs/laravel-<date>.log'
Write-Info 'Guide             : docs/07-operations/local-testing.md'
if ($DryRun) { Write-Host ''; Write-Host 'Dry run finished: nothing was changed.' -ForegroundColor DarkYellow }
