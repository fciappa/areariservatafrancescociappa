param(
    [string]$ReportPath,
    [string]$OutputPath = "storage/logs/release-health.md",
    [string]$WorkflowName = "Backend CI",
    [string]$RunUrl = "",
    [string]$EventName = "",
    [string]$RefName = "",
    [string]$CommitSha = ""
)

$ErrorActionPreference = "Stop"

function SafeReadLines {
    param([string]$Path)

    if (-not (Test-Path $Path)) {
        return @()
    }

    return Get-Content -Path $Path -Encoding UTF8
}

function GetLineValue {
    param(
        [string[]]$Lines,
        [string]$Prefix
    )

    $line = $Lines | Where-Object { $_ -like "$Prefix*" } | Select-Object -First 1
    if (-not $line) {
        return "n/a"
    }

    return $line.Substring($Prefix.Length).Trim()
}

function EnsureDirectory {
    param([string]$Path)

    $dir = Split-Path -Parent $Path
    if (-not [string]::IsNullOrWhiteSpace($dir) -and -not (Test-Path $dir)) {
        New-Item -ItemType Directory -Path $dir -Force | Out-Null
    }
}

$lines = SafeReadLines -Path $ReportPath

$result = GetLineValue -Lines $lines -Prefix "- Result:"
$baseUrl = GetLineValue -Lines $lines -Prefix "- Base URL:"
$protected = GetLineValue -Lines $lines -Prefix "- Protected path:"
$passed = GetLineValue -Lines $lines -Prefix "- Passed:"

$tableRows = @()
$inTable = $false
foreach ($line in $lines) {
    if ($line -eq "| Check | Status | Detail |") {
        $inTable = $true
        continue
    }

    if (-not $inTable) {
        continue
    }

    if ($line -eq "|---|---|---|") {
        continue
    }

    if (-not $line.StartsWith("| ")) {
        continue
    }

    $tableRows += $line
}

$generatedAt = (Get-Date).ToString("yyyy-MM-dd HH:mm:ss K")

$out = @()
$out += "# Release Health Dashboard"
$out += ""
$out += "- Workflow: $WorkflowName"
$out += "- Generated at: $generatedAt"
$out += "- Event: $EventName"
$out += "- Ref: $RefName"
$out += "- Commit: $CommitSha"
$out += "- Result: $result"
$out += "- Passed checks: $passed"
$out += "- Base URL: $baseUrl"
$out += "- Protected path: $protected"
if (-not [string]::IsNullOrWhiteSpace($RunUrl)) {
    $out += "- Run URL: $RunUrl"
}
$out += ""
$out += "## Smoke Checks"
$out += ""
$out += "| Check | Status | Detail |"
$out += "|---|---|---|"

if ($tableRows.Count -eq 0) {
    $out += "| n/a | n/a | Smoke report not found or table unavailable |"
}
else {
    $out += $tableRows
}

EnsureDirectory -Path $OutputPath
Set-Content -Path $OutputPath -Value $out -Encoding UTF8
Write-Output "Release health dashboard written: $OutputPath"
