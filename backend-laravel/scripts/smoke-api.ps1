param(
    [string]$BaseUrl = "http://127.0.0.1:8000",
    [switch]$SkipAuthChecks,
    [string]$ProtectedPath = "/api/users",
    [switch]$VerboseOutput
)

$ErrorActionPreference = "Stop"

function Write-Check {
    param(
        [string]$Name,
        [bool]$Passed,
        [string]$Detail
    )

    $status = if ($Passed) { "PASS" } else { "FAIL" }
    Write-Output ("[{0}] {1} - {2}" -f $status, $Name, $Detail)
}

function Invoke-Api {
    param(
        [string]$Method,
        [string]$Url,
        [hashtable]$Body = $null,
        [hashtable]$Headers = @{}
    )

    $params = @{
        Method      = $Method
        Uri         = $Url
        Headers     = $Headers
        ContentType = "application/json"
    }

    if ($Body -ne $null) {
        $params.Body = ($Body | ConvertTo-Json -Depth 10)
    }

    try {
        $response = Invoke-RestMethod @params
        return [pscustomobject]@{
            StatusCode = 200
            Body       = $response
        }
    }
    catch {
        $statusCode = 0
        $body = $null

        if ($_.Exception.Response -and $_.Exception.Response.StatusCode) {
            $statusCode = [int]$_.Exception.Response.StatusCode
        }

        if ($_.ErrorDetails -and $_.ErrorDetails.Message) {
            try {
                $body = $_.ErrorDetails.Message | ConvertFrom-Json
            }
            catch {
                $body = $_.ErrorDetails.Message
            }
        }

        return [pscustomobject]@{
            StatusCode = $statusCode
            Body       = $body
            Error      = $_
        }
    }
}

$checks = @()

Write-Output "Smoke API start: $BaseUrl"

# 1) 404 envelope
$r404 = Invoke-Api -Method "GET" -Url "$BaseUrl/api/__smoke__/missing-route"
$pass404 = $r404.StatusCode -eq 404 -and $r404.Body -and $r404.Body.message -eq "Risorsa non trovata" -and $r404.Body.error
$checks += [pscustomobject]@{ Name = "404 envelope"; Passed = $pass404; Detail = "status=$($r404.StatusCode) message=$($r404.Body.message)" }

# 2) 422 validation envelope on login
$r422 = Invoke-Api -Method "POST" -Url "$BaseUrl/api/auth/login" -Body @{ username = "smoke" }
$hasPasswordError = $false
if ($r422.Body -and $r422.Body.errors -and $r422.Body.errors.password) {
    $hasPasswordError = $true
}
$pass422 = $r422.StatusCode -eq 422 -and $r422.Body -and $r422.Body.message -eq "Dati non validi" -and $hasPasswordError
$checks += [pscustomobject]@{ Name = "422 envelope"; Passed = $pass422; Detail = "status=$($r422.StatusCode) message=$($r422.Body.message)" }

# 3) Optional auth flow checks (requires credentials in env)
if (-not $SkipAuthChecks) {
    $username = $env:SMOKE_USER
    $password = $env:SMOKE_PASS

    if ([string]::IsNullOrWhiteSpace($username) -or [string]::IsNullOrWhiteSpace($password)) {
        $checks += [pscustomobject]@{ Name = "Auth flow"; Passed = $true; Detail = "skipped (set SMOKE_USER/SMOKE_PASS to enable)" }
    }
    else {
        $protectedPre = Invoke-Api -Method "GET" -Url "$BaseUrl$ProtectedPath"
        $prePass = $protectedPre.StatusCode -in @(401, 403)
        $checks += [pscustomobject]@{ Name = "Protected route before login"; Passed = $prePass; Detail = "GET $ProtectedPath status=$($protectedPre.StatusCode)" }

        $login = Invoke-Api -Method "POST" -Url "$BaseUrl/api/auth/login" -Body @{ username = $username; password = $password }
        $token = $null
        $refreshToken = $null
        if ($login.Body -and $login.Body.accessToken) {
            $token = [string]$login.Body.accessToken
        }
        if ($login.Body -and $login.Body.refreshToken) {
            $refreshToken = [string]$login.Body.refreshToken
        }

        $authPass = $login.StatusCode -eq 200 -and -not [string]::IsNullOrWhiteSpace($token) -and -not [string]::IsNullOrWhiteSpace($refreshToken)
        $checks += [pscustomobject]@{ Name = "Auth login"; Passed = $authPass; Detail = "status=$($login.StatusCode) accessToken=$([bool]$token) refreshToken=$([bool]$refreshToken)" }

        if ($authPass) {
            $authHeaders = @{ Authorization = "Bearer $token" }
            $protected = Invoke-Api -Method "GET" -Url "$BaseUrl$ProtectedPath" -Headers $authHeaders
            $protectedPass = $protected.StatusCode -eq 200
            $checks += [pscustomobject]@{ Name = "Protected route with access token"; Passed = $protectedPass; Detail = "GET $ProtectedPath status=$($protected.StatusCode)" }

            $refresh = Invoke-Api -Method "POST" -Url "$BaseUrl/api/auth/refresh" -Body @{ refreshToken = $refreshToken }
            $newAccessToken = $null
            if ($refresh.Body -and $refresh.Body.accessToken) {
                $newAccessToken = [string]$refresh.Body.accessToken
            }
            $refreshPass = $refresh.StatusCode -eq 200 -and -not [string]::IsNullOrWhiteSpace($newAccessToken)
            $checks += [pscustomobject]@{ Name = "Auth refresh"; Passed = $refreshPass; Detail = "status=$($refresh.StatusCode) accessToken=$([bool]$newAccessToken)" }

            if ($refreshPass) {
                $newHeaders = @{ Authorization = "Bearer $newAccessToken" }
                $protected2 = Invoke-Api -Method "GET" -Url "$BaseUrl$ProtectedPath" -Headers $newHeaders
                $protected2Pass = $protected2.StatusCode -eq 200
                $checks += [pscustomobject]@{ Name = "Protected route with refreshed token"; Passed = $protected2Pass; Detail = "GET $ProtectedPath status=$($protected2.StatusCode)" }
            }

            $logout = Invoke-Api -Method "POST" -Url "$BaseUrl/api/auth/logout" -Body @{ refreshToken = $refreshToken }
            $logoutPass = $logout.StatusCode -eq 200 -and $logout.Body -and $logout.Body.message -eq "Logout effettuato"
            $checks += [pscustomobject]@{ Name = "Auth logout"; Passed = $logoutPass; Detail = "status=$($logout.StatusCode) message=$($logout.Body.message)" }

            if ($logoutPass) {
                $refreshAfterLogout = Invoke-Api -Method "POST" -Url "$BaseUrl/api/auth/refresh" -Body @{ refreshToken = $refreshToken }
                $refreshAfterLogoutPass = $refreshAfterLogout.StatusCode -eq 401
                $checks += [pscustomobject]@{ Name = "Refresh token invalid after logout"; Passed = $refreshAfterLogoutPass; Detail = "status=$($refreshAfterLogout.StatusCode)" }
            }
        }
    }
}

foreach ($c in $checks) {
    Write-Check -Name $c.Name -Passed $c.Passed -Detail $c.Detail

    if ($VerboseOutput -and -not $c.Passed) {
        Write-Output "  details: $($c | ConvertTo-Json -Depth 6)"
    }
}

$failed = ($checks | Where-Object { -not $_.Passed }).Count
if ($failed -gt 0) {
    Write-Output "Smoke API result: FAILED ($failed checks failed)"
    exit 1
}

Write-Output "Smoke API result: OK"
exit 0
