# Funciones compartidas por los scripts de NEXO. Compatible con Windows PowerShell 5.1 y PowerShell 7.
# Se carga con: . "$PSScriptRoot\_comun.ps1"

Set-StrictMode -Version 2.0

$script:RaizRepo = Split-Path -Parent $PSScriptRoot
$script:Utf8SinBom = New-Object System.Text.UTF8Encoding $false

# Con la salida redirigida (otro script o un log), PowerShell 5.1 escribe en la página de códigos OEM
# y los acentos llegan rotos. Solo en ese caso se usa UTF-8; la consola interactiva no se toca.
if ([Console]::IsOutputRedirected) { [Console]::OutputEncoding = $script:Utf8SinBom }

function Write-Paso([string]$Texto) { Write-Host "==> $Texto" -ForegroundColor Cyan }
function Write-Ok([string]$Texto) { Write-Host "OK    $Texto" -ForegroundColor Green }
function Write-Falla([string]$Texto) { Write-Host "FALLA $Texto" -ForegroundColor Red }
function Write-Aviso([string]$Texto) { Write-Host "AVISO $Texto" -ForegroundColor Yellow }

# Lee un archivo .env (CLAVE=valor). Ignora comentarios y líneas vacías.
function Read-DotEnv([string]$Ruta) {
    $valores = @{}
    if (-not (Test-Path -LiteralPath $Ruta)) { return $valores }
    foreach ($linea in [IO.File]::ReadAllLines($Ruta, $script:Utf8SinBom)) {
        $linea = $linea.Trim()
        if ($linea -eq '' -or $linea.StartsWith('#')) { continue }
        $i = $linea.IndexOf('=')
        if ($i -lt 1) { continue }
        $valores[$linea.Substring(0, $i).Trim()] = $linea.Substring($i + 1).Trim().Trim('"').Trim("'")
    }
    return $valores
}

# Cita un argumento según las reglas de la línea de comandos de Windows.
function ConvertTo-Argumento([string]$Valor) {
    if ($Valor -ne '' -and $Valor -notmatch '[\s"]') { return $Valor }
    $escapado = $Valor -replace '(\\*)"', '$1$1\"' -replace '(\\+)$', '$1$1'
    return '"' + $escapado + '"'
}

# Ejecuta un programa y devuelve ExitCode, Salida y Error sin depender de $ErrorActionPreference.
# -Entrada se envía como UTF-8 por stdin (evita los problemas de codificación de PowerShell 5.1).
function Invoke-Programa {
    param([string]$Programa, [string[]]$Argumentos, [string]$Entrada = $null)
    $info = New-Object System.Diagnostics.ProcessStartInfo
    $info.FileName = $Programa
    $info.Arguments = ($Argumentos | ForEach-Object { ConvertTo-Argumento $_ }) -join ' '
    $info.UseShellExecute = $false
    $info.RedirectStandardOutput = $true
    $info.RedirectStandardError = $true
    $info.RedirectStandardInput = $true
    $info.StandardOutputEncoding = $script:Utf8SinBom
    $info.StandardErrorEncoding = $script:Utf8SinBom
    $info.CreateNoWindow = $true
    $info.WorkingDirectory = $script:RaizRepo
    try {
        $proceso = [System.Diagnostics.Process]::Start($info)
    } catch {
        return [pscustomobject]@{ ExitCode = 127; Salida = ''; Error = "No se pudo ejecutar ${Programa}: $($_.Exception.Message)" }
    }
    $salida = $proceso.StandardOutput.ReadToEndAsync()
    $errores = $proceso.StandardError.ReadToEndAsync()
    if ($null -ne $Entrada) {
        $bytes = $script:Utf8SinBom.GetBytes($Entrada)
        $proceso.StandardInput.BaseStream.Write($bytes, 0, $bytes.Length)
    }
    $proceso.StandardInput.Close()
    $proceso.WaitForExit()
    return [pscustomobject]@{ ExitCode = $proceso.ExitCode; Salida = $salida.Result; Error = $errores.Result }
}

function Test-Docker {
    if (-not (Get-Command docker -ErrorAction SilentlyContinue)) { return $false }
    return (Invoke-Programa 'docker' @('compose', 'version')).ExitCode -eq 0
}

# docker compose sobre el compose.yaml del repositorio (lee .env de la raíz).
function Invoke-Compose([string[]]$Argumentos, [string]$Entrada = $null) {
    $base = @('compose', '--project-directory', $script:RaizRepo, '-f', (Join-Path $script:RaizRepo 'compose.yaml'))
    return Invoke-Programa 'docker' ($base + $Argumentos) $Entrada
}

# Estado de salud del contenedor de un servicio: healthy, starting, unhealthy, running (sin healthcheck) o vacío.
function Get-EstadoServicio([string]$Servicio) {
    $id = (Invoke-Compose @('ps', '-q', $Servicio)).Salida.Trim()
    if ($id -eq '') { return '' }
    $formato = '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}'
    return (Invoke-Programa 'docker' @('inspect', '-f', $formato, $id)).Salida.Trim()
}

# Ejecuta SQL con psql dentro del contenedor db (socket local). Devuelve el resultado de Invoke-Programa.
function Invoke-Psql {
    param(
        [string]$Sql,
        [string]$Usuario,
        [string]$Base,
        [switch]$UnaTransaccion,
        [switch]$Tabular,
        [hashtable]$Variables = @{}
    )
    $argumentos = @('exec', '-T', 'db', 'psql', '-X', '-q', '-v', 'ON_ERROR_STOP=1', '-U', $Usuario, '-d', $Base)
    if ($UnaTransaccion) { $argumentos += '--single-transaction' }
    if ($Tabular) { $argumentos += @('-A', '-t', '-F', '|') }
    foreach ($clave in $Variables.Keys) { $argumentos += @('-v', "$clave=$($Variables[$clave])") }
    $argumentos += @('-f', '-')
    return Invoke-Compose $argumentos $Sql
}

# SHA-256 del archivo con saltos de línea normalizados a LF (igual en Windows y Linux).
function Get-ChecksumMigracion([string]$Ruta) {
    $texto = [IO.File]::ReadAllText($Ruta, $script:Utf8SinBom) -replace "`r`n", "`n"
    $sha = [System.Security.Cryptography.SHA256]::Create()
    try {
        $hash = $sha.ComputeHash($script:Utf8SinBom.GetBytes($texto))
    } finally {
        $sha.Dispose()
    }
    return -join ($hash | ForEach-Object { $_.ToString('x2') })
}
