# ===============================
# CONFIG
# ===============================
$proxyUrl   = "http://localhost:8000/index.php"
$proxyKey   = "proxy"
$secret     = "zxc666"
$prompt     = "Explain quantum computing in simple terms"

# ===============================
# STEP 1: Generate HMAC signature
# ===============================
$sign = php -r "echo hash_hmac('sha256', '$prompt', '$secret');"

if (-not $sign) {
    Write-Host "Failed to generate HMAC. Make sure PHP is in PATH."
    exit 1
}

Write-Host "Generated sign: $sign"

# ===============================
# STEP 2: Send async request
# ===============================
$endpoint = "$proxyUrl" + "?action=request"

$body = @{
    prompt = $prompt
    sign   = $sign
} | ConvertTo-Json

try {
    $response = Invoke-RestMethod -Uri $endpoint `
        -Method POST `
        -ContentType "application/json" `
        -Headers @{ "X-Proxy-Key" = $proxyKey } `
        -Body $body
} catch {
    Write-Host "Failed to connect to proxy. Check that php -S is running."
    exit 1
}

if (-not $response.id) {
    Write-Host "Failed to create request:"
    $response | ConvertTo-Json -Depth 5
    exit 1
}

$id = $response.id
Write-Host "Request created. ID: $id"

# ===============================
# STEP 3: Poll for result
# ===============================
$maxAttempts = 20
$attempt = 0
$done = $false

Write-Host "Polling for result..."
do {
    Start-Sleep -Seconds 5

    $resultUrl = ($proxyUrl + "?action=result&id=" + $id)

    try {
        $result = Invoke-RestMethod -Uri $resultUrl -Method GET
    } catch {
        Write-Host "Error while checking result: $_"
        $attempt++
        continue
    }

    if ($result.status -eq "done") {
        Write-Host "`n=== RESULT RECEIVED ==="
        $result.response | ConvertTo-Json -Depth 8
        $done = $true
        break
    }
    elseif ($result.status -eq "error") {
        Write-Host "`nError during processing:"
        $result | ConvertTo-Json -Depth 8
        exit 1
    }

    $attempt++
    Write-Host "[$attempt/$maxAttempts] Still pending..."
} while ($attempt -lt $maxAttempts)

if (-not $done) {
    Write-Host "Timeout: no response received within polling limit."
    exit 1
}
