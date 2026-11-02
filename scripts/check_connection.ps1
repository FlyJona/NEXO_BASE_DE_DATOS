<#
.SYNOPSIS
    Comprueba que PostgreSQL y PostgREST estén iniciados Y listos para atender peticiones.

.DESCRIPTION
    Revisa .env (sin mostrar valores), docker compose, el estado de salud de los contenedores,
    conexiones reales con el rol de la app y del API, y los endpoints /live y /ready de PostgREST.
    Códigos de salida: 0 todo correcto · 1 alguna comprobación falló · 2 faltan requisitos (Docker o .env).
#>
[CmdletBinding()]
param()

. "$PSScriptRoot\_comun.ps1"
$ErrorActionPreference = 'Stop'
$fallas = 0
function Registrar([bool]$Correcto, [string]$Texto) {
    if ($Correcto) { Write-Ok $Texto } else { Write-Falla $Texto; $script:fallas++ }
}

# 1. Variables de entorno (solo se informa si existen, nunca su valor)
Write-Paso 'Variables de entorno (.env)'
$archivoEnv = Join-Path $script:RaizRepo '.env'
if (-not (Test-Path -LiteralPath $archivoEnv)) {
    Write-Falla 'No existe .env. Créalo con .\scripts\new_env.ps1'
    exit 2
}
$entorno = Read-DotEnv $archivoEnv
$requeridas = 'POSTGRES_DB', 'POSTGRES_SUPERUSER', 'POSTGRES_SUPERUSER_PASSWORD', 'NEXO_MIGRATOR_PASSWORD',
    'NEXO_APP_PASSWORD', 'PGRST_AUTHENTICATOR_PASSWORD', 'PGRST_JWT_SECRET'
foreach ($nombre in $requeridas) {
    $valor = $entorno[$nombre]
    Registrar ([bool]$valor -and $valor -notmatch '^cambia_esto') "$nombre definida"
}
if ($entorno['PGRST_JWT_SECRET']) {
    Registrar ($entorno['PGRST_JWT_SECRET'].Length -ge 32) 'PGRST_JWT_SECRET tiene al menos 32 caracteres'
}
foreach ($nombre in 'NEXO_APP_PASSWORD', 'PGRST_AUTHENTICATOR_PASSWORD') {
    if ($entorno[$nombre]) {
        Registrar ($entorno[$nombre] -match '^[A-Za-z0-9]+$') "$nombre solo usa letras y números (va dentro de una URL)"
    }
}
$base = $entorno['POSTGRES_DB']
$puertoApi = if ($entorno['POSTGREST_HOST_PORT']) { $entorno['POSTGREST_HOST_PORT'] } else { '3000' }
$puertoAdmin = if ($entorno['POSTGREST_ADMIN_HOST_PORT']) { $entorno['POSTGREST_ADMIN_HOST_PORT'] } else { '3001' }
$puertoDb = if ($entorno['POSTGRES_HOST_PORT']) { $entorno['POSTGRES_HOST_PORT'] } else { '5432' }

# 2. Docker Compose
Write-Paso 'Docker Compose'
if (-not (Test-Docker)) {
    Write-Falla 'docker compose no está disponible. Abre Docker Desktop y espera a que diga "Engine running".'
    exit 2
}
Write-Ok 'docker compose disponible'
$config = Invoke-Compose @('config', '--quiet')
Registrar ($config.ExitCode -eq 0) "compose.yaml y .env son coherentes $($config.Error.Trim())"

# 3. PostgreSQL: iniciado vs. listo
Write-Paso 'PostgreSQL (servicio db)'
$estadoDb = Get-EstadoServicio 'db'
Registrar ($estadoDb -ne '') "contenedor creado (estado: $estadoDb)"
Registrar ($estadoDb -eq 'healthy') 'healthcheck en "healthy" (pg_isready)'
if ($estadoDb -ne '') {
    $r = Invoke-Psql -Sql 'SELECT 1;' -Usuario 'nexo_migrator' -Base $base -Tabular
    Registrar ($r.ExitCode -eq 0 -and $r.Salida.Trim() -eq '1') 'consulta como nexo_migrator'

    # El rol de la app se prueba por TCP y con contraseña, como lo hace PHP.
    $r = Invoke-Compose @('exec', '-T', '-e', "PGPASSWORD=$($entorno['NEXO_APP_PASSWORD'])", 'db',
        'psql', '-X', '-A', '-t', '-h', '127.0.0.1', '-U', 'nexo_app', '-d', $base, '-c', 'SELECT count(*) FROM nexo.materias')
    Registrar ($r.ExitCode -eq 0) "conexión con contraseña como nexo_app ($($r.Salida.Trim()) materias)"

    $r = Invoke-Psql -Sql 'SELECT count(*) FROM nexo_meta.schema_migrations;' -Usuario 'nexo_migrator' -Base $base -Tabular
    Registrar ($r.ExitCode -eq 0) "historial de migraciones legible ($($r.Salida.Trim()) aplicadas)"
}
$tcp = New-Object System.Net.Sockets.TcpClient
try {
    $tcp.Connect('127.0.0.1', [int]$puertoDb)
    Registrar $tcp.Connected "puerto 127.0.0.1:$puertoDb abierto para la app PHP"
} catch {
    Registrar $false "puerto 127.0.0.1:$puertoDb abierto para la app PHP"
} finally {
    $tcp.Close()
}

# 4. PostgREST: proceso vivo, conectado a PostgreSQL y respondiendo
Write-Paso 'PostgREST (servicio api)'
$estadoApi = Get-EstadoServicio 'api'
Registrar ($estadoApi -eq 'running') "contenedor en ejecución (estado: $estadoApi)"
function Get-Estado([string]$Url) {
    try {
        return [int](Invoke-WebRequest -Uri $Url -UseBasicParsing -TimeoutSec 5).StatusCode
    } catch {
        if ($_.Exception.Response) { return [int]$_.Exception.Response.StatusCode }
        return 0
    }
}
Registrar ((Get-Estado "http://127.0.0.1:$puertoAdmin/live") -eq 200) '/live: el proceso responde'
Registrar ((Get-Estado "http://127.0.0.1:$puertoAdmin/ready") -eq 200) '/ready: conectado a PostgreSQL y esquema cargado'
Registrar ((Get-Estado "http://127.0.0.1:$puertoApi/materias?limit=1") -eq 200) 'GET /materias responde 200'

Write-Host ''
if ($fallas -gt 0) {
    Write-Falla "$fallas comprobaciones fallaron."
    exit 1
}
Write-Ok 'Todo listo.'
exit 0
