param(
    [Parameter(Mandatory = $true)][string]$Model,
    [Parameter(Mandatory = $true)][string]$PromptFile,
    [Parameter(Mandatory = $true)][string]$OutFile,
    [int]$MaxTokens = 4000
)

$ErrorActionPreference = 'Stop'
$base = 'http://127.0.0.1:20128/v1/chat/completions'
# NOT: 'sk-4c836...' anahtarı OmniRoute tarafında "uygunluga göre model seç"
# placeholder kombosuna bağlı ve gerçek modele yönlendirmez. Gerçek anahtar
# EVREN_LLM_API_KEY ortam değişkeninden gelir.
$key = if ($env:OMNIROUTE_API_KEY) { $env:OMNIROUTE_API_KEY } else { $env:EVREN_LLM_API_KEY }
if (-not $key) { throw 'OMNIROUTE_API_KEY / EVREN_LLM_API_KEY tanimli degil.' }

$prompt = Get-Content -Raw -LiteralPath $PromptFile

$body = @{
    model            = $Model
    messages         = @(@{ role = 'user'; content = $prompt })
    max_tokens       = $MaxTokens
    temperature      = 0.2
    reasoning_effort = 'none'
} | ConvertTo-Json -Depth 10

# PowerShell 7 varsayılan olarak UTF8 gönderir; eski sürümlerde body
# ASCII'ye düşer ve API 415 döner. Gövdeyi byte[] olarak gönder.
$bodyBytes = [System.Text.Encoding]::UTF8.GetBytes($body)

$headers = @{
    Authorization = "Bearer $key"
}

$started = Get-Date
try {
    $response = Invoke-RestMethod -Uri $base -Method Post -Headers $headers -Body $bodyBytes -ContentType 'application/json; charset=utf-8' -TimeoutSec 600
    $message = $response.choices[0].message
    $content = $message.content
    if ([string]::IsNullOrWhiteSpace($content) -and $message.reasoning_content) {
        # Bazi saglayicilar cevabi reasoning_content alanina yazar.
        $content = $message.reasoning_content
    }
    $meta = [ordered]@{
        model       = $Model
        ok          = $true
        id          = $response.id
        created     = $response.created
        usage       = $response.usage
        finish      = $response.choices[0].finish_reason
        durationSec = [math]::Round(((Get-Date) - $started).TotalSeconds, 1)
        content     = $content
    }
    $meta | ConvertTo-Json -Depth 10 | Set-Content -LiteralPath $OutFile -Encoding UTF8
    Write-Output "OK $Model -> $OutFile ($($response.usage.total_tokens) tokens, $($meta.durationSec)s)"
} catch {
    $err = [ordered]@{
        model       = $Model
        ok          = $false
        error       = $_.Exception.Message
        detail      = if ($_.ErrorDetails) { $_.ErrorDetails.Message } else { '' }
        durationSec = [math]::Round(((Get-Date) - $started).TotalSeconds, 1)
    }
    $err | ConvertTo-Json -Depth 10 | Set-Content -LiteralPath $OutFile -Encoding UTF8
    Write-Output "FAIL $Model -> $OutFile : $($_.Exception.Message)"
}
