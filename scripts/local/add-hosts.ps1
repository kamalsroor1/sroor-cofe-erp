<#
.SYNOPSIS
    Adds the Sroor ERP local test hostnames to the Windows hosts file (idempotent).

.DESCRIPTION
    Reads scripts/local/hosts-entries.txt and appends every "127.0.0.1 <host>" line that is
    not already mapped, inside a marked block. Existing lines (including Laragon's own
    "sroor.test #laragon magic!") are never modified or duplicated. A timestamped backup of
    the hosts file is written next to it before any change.

    Must run from an elevated PowerShell (Run as Administrator). Local machine only.

.PARAMETER DryRun
    Show what would be added; change nothing (no Administrator needed).

.PARAMETER Remove
    Remove the block this script added (lines between the BEGIN/END markers).

.PARAMETER ExtraHost
    Additional tenant hostnames to map, e.g. -ExtraHost shop3.sroor.test,shop4.sroor.test

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File D:\projects\sroor\scripts\local\add-hosts.ps1 -DryRun
    powershell -ExecutionPolicy Bypass -File D:\projects\sroor\scripts\local\add-hosts.ps1
#>
[CmdletBinding()]
param(
    [switch]$DryRun,
    [switch]$Remove,
    [string[]]$ExtraHost = @()
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$BeginMarker = '# >>> sroor-local-test BEGIN (scripts/local/add-hosts.ps1)'
$EndMarker = '# <<< sroor-local-test END'
$HostsPath = Join-Path $env:SystemRoot 'System32\drivers\etc\hosts'
$EntriesFile = Join-Path $PSScriptRoot 'hosts-entries.txt'
$HostPattern = '^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)*$'

function Test-IsAdministrator {
    $identity = [Security.Principal.WindowsIdentity]::GetCurrent()
    $principal = New-Object Security.Principal.WindowsPrincipal($identity)
    return $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
}

function Get-WantedHosts {
    $wanted = New-Object System.Collections.Generic.List[string]
    foreach ($line in (Get-Content -LiteralPath $EntriesFile)) {
        $trimmed = $line.Trim()
        if ($trimmed -eq '' -or $trimmed.StartsWith('#')) { continue }
        $parts = $trimmed -split '\s+'
        if ($parts.Count -ge 2) { $wanted.Add($parts[1].ToLowerInvariant()) }
    }
    foreach ($h in $ExtraHost) {
        foreach ($item in ($h -split ',')) {
            $name = $item.Trim().ToLowerInvariant()
            if ($name -ne '') { $wanted.Add($name) }
        }
    }
    foreach ($name in $wanted) {
        if (($name -notmatch $HostPattern) -or (-not $name.EndsWith('.test'))) {
            throw "Refusing hostname '$name': only *.test hostnames are allowed here."
        }
    }
    return $wanted | Select-Object -Unique
}

function Test-HostMapped([string[]]$lines, [string]$name) {
    foreach ($line in $lines) {
        $content = ($line -split '#', 2)[0]
        if ($content -match "^\s*(127\.0\.0\.1|::1)\s+(.+)$") {
            foreach ($alias in ($Matches[2].Trim() -split '\s+')) {
                if ($alias.ToLowerInvariant() -eq $name) { return $true }
            }
        }
    }
    return $false
}

if (-not (Test-Path -LiteralPath $HostsPath)) { throw "Hosts file not found: $HostsPath" }
if (-not (Test-Path -LiteralPath $EntriesFile)) { throw "Entries file not found: $EntriesFile" }

$lines = @(Get-Content -LiteralPath $HostsPath)

if ($Remove) {
    $inBlock = $false
    $kept = New-Object System.Collections.Generic.List[string]
    $removed = 0
    foreach ($line in $lines) {
        if ($line -eq $BeginMarker) { $inBlock = $true; $removed++; continue }
        if ($inBlock -and $line -eq $EndMarker) { $inBlock = $false; $removed++; continue }
        if ($inBlock) { $removed++; continue }
        $kept.Add($line)
    }
    if ($removed -eq 0) { Write-Host 'Nothing to remove: no sroor-local-test block in hosts.'; exit 0 }
    if ($DryRun) { Write-Host "[dry-run] Would remove $removed line(s) (the sroor-local-test block)."; exit 0 }
    if (-not (Test-IsAdministrator)) { throw 'Run this from an elevated PowerShell (Run as Administrator).' }
    $backup = "$HostsPath.sroor-bak-$(Get-Date -Format 'yyyyMMdd-HHmmss')"
    Copy-Item -LiteralPath $HostsPath -Destination $backup
    [System.IO.File]::WriteAllLines($HostsPath, $kept.ToArray(), (New-Object System.Text.ASCIIEncoding))
    ipconfig /flushdns | Out-Null
    Write-Host "Removed the sroor-local-test block. Backup: $backup"
    exit 0
}

$wanted = Get-WantedHosts
$missing = @($wanted | Where-Object { -not (Test-HostMapped $lines $_) })

foreach ($name in $wanted) {
    if ($missing -contains $name) { Write-Host "  [+] $name" } else { Write-Host "  [=] $name (already mapped)" }
}

if ($missing.Count -eq 0) {
    Write-Host 'All hostnames already mapped. Nothing to do.'
    exit 0
}

if ($DryRun) {
    Write-Host "[dry-run] Would add $($missing.Count) line(s) to $HostsPath."
    exit 0
}

if (-not (Test-IsAdministrator)) {
    throw 'Run this from an elevated PowerShell (right-click > Run as Administrator).'
}

$backupPath = "$HostsPath.sroor-bak-$(Get-Date -Format 'yyyyMMdd-HHmmss')"
Copy-Item -LiteralPath $HostsPath -Destination $backupPath

$hasBlock = $lines -contains $BeginMarker
$newLines = New-Object System.Collections.Generic.List[string]
if ($hasBlock) {
    # Insert the missing names just before our END marker.
    foreach ($line in $lines) {
        if ($line -eq $EndMarker) {
            foreach ($name in $missing) { $newLines.Add("127.0.0.1 $name") }
        }
        $newLines.Add($line)
    }
} else {
    foreach ($line in $lines) { $newLines.Add($line) }
    $newLines.Add('')
    $newLines.Add($BeginMarker)
    foreach ($name in $missing) { $newLines.Add("127.0.0.1 $name") }
    $newLines.Add($EndMarker)
}

[System.IO.File]::WriteAllLines($HostsPath, $newLines.ToArray(), (New-Object System.Text.ASCIIEncoding))
ipconfig /flushdns | Out-Null

Write-Host "Added $($missing.Count) hostname(s). Backup of the previous hosts file: $backupPath"
Write-Host 'DNS cache flushed. Next: reload Apache from Laragon (Menu > Apache > Reload).'
