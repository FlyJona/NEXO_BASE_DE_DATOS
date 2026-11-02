<#
.SYNOPSIS
    Genera un JWT (HS256) firmado con PGRST_JWT_SECRET para probar la API de PostgREST.

.DESCRIPTION
    Imprime solo el token. Úsalo como: Authorization: Bearer <token>
    Códigos de salida: 0 correcto · 2 falta .env o el secreto.

.EXAMPLE
    $token = .\scripts\new_jwt.ps1 -Role nexo_lector -Minutes 30
#>
[CmdletBinding()]
param(
    [ValidateSet('nexo_lector', 'web_anon')]
    [string]$Role = 'nexo_lector',
    [ValidateRange(1, 1440)]
    [int]$Minutes = 60
)

. "$PSScriptRoot\_comun.ps1"
$ErrorActionPreference = 'Stop'

$secreto = (Read-DotEnv (Join-Path $script:RaizRepo '.env'))['PGRST_JWT_SECRET']
if (-not $secreto) {
    Write-Error 'Falta PGRST_JWT_SECRET en .env'
    exit 2
}

function ConvertTo-Base64Url([byte[]]$Bytes) {
    return [Convert]::ToBase64String($Bytes).TrimEnd('=').Replace('+', '-').Replace('/', '_')
}

$expira = [int][Math]::Floor(([DateTimeOffset]::UtcNow.AddMinutes($Minutes)).ToUnixTimeSeconds())
$cabecera = ConvertTo-Base64Url ($script:Utf8SinBom.GetBytes('{"alg":"HS256","typ":"JWT"}'))
$datos = ConvertTo-Base64Url ($script:Utf8SinBom.GetBytes("{""role"":""$Role"",""exp"":$expira}"))
$hmac = New-Object System.Security.Cryptography.HMACSHA256 (, $script:Utf8SinBom.GetBytes($secreto))
$firma = ConvertTo-Base64Url ($hmac.ComputeHash($script:Utf8SinBom.GetBytes("$cabecera.$datos")))
$hmac.Dispose()
Write-Output "$cabecera.$datos.$firma"
exit 0
