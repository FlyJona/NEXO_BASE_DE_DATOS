<#
.SYNOPSIS
    Pruebas de la base de datos de NEXO (PostgreSQL + PostgREST).

.DESCRIPTION
    Las pruebas de esquema crean una base TEMPORAL (nexo_prueba_XXXX) y la borran al terminar:
    la base "nexo" nunca se modifica ni se borra. Las pruebas de API y de persistencia solo leen "nexo".
    -IncluirReinicio reinicia los contenedores (docker compose restart; no borra volúmenes).
    Códigos de salida: 0 todo correcto · 1 alguna prueba falló · 2 faltan requisitos.
#>
[CmdletBinding()]
param(
    [switch]$IncluirReinicio
)

. (Join-Path (Split-Path -Parent $PSScriptRoot) 'scripts\_comun.ps1')
$ErrorActionPreference = 'Stop'

$entorno = Read-DotEnv (Join-Path $script:RaizRepo '.env')
$superusuario = $entorno['POSTGRES_SUPERUSER']
$baseReal = $entorno['POSTGRES_DB']
$puertoApi = if ($entorno['POSTGREST_HOST_PORT']) { $entorno['POSTGREST_HOST_PORT'] } else { '3000' }
$aplicar = Join-Path $script:RaizRepo 'scripts\apply_migrations.ps1'
$powershell = (Get-Process -Id $PID).Path
$temporal = Join-Path $PSScriptRoot '.tmp'
$fallas = 0
$total = 0

function Probar([string]$Nombre, [scriptblock]$Prueba) {
    $script:total++
    try {
        $detalle = & $Prueba
        Write-Ok "$Nombre $detalle"
    } catch {
        $script:fallas++
        Write-Falla "$Nombre -> $($_.Exception.Message)"
    }
}
function Afirmar([bool]$Condicion, [string]$Mensaje) { if (-not $Condicion) { throw $Mensaje } }
function Aplicar([string]$Base, [string]$Carpeta) {
    $argumentos = @('-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', $aplicar, '-Database', $Base)
    if ($Carpeta) { $argumentos += @('-MigrationsPath', $Carpeta) }
    return Invoke-Programa $powershell $argumentos
}
function Psql-Super([string]$Sql, [string]$Base) { return Invoke-Psql -Sql $Sql -Usuario $superusuario -Base $Base -Tabular }
function Valor([string]$Sql, [string]$Base) {
    $r = Psql-Super $Sql $Base
    if ($r.ExitCode -ne 0) { throw $r.Error.Trim() }
    return $r.Salida.Trim()
}
function Get-Http([string]$Ruta, [string]$Token, [string]$Metodo = 'GET') {
    $cabeceras = @{}
    if ($Token) { $cabeceras['Authorization'] = "Bearer $Token" }
    try {
        $respuesta = Invoke-WebRequest -Uri "http://127.0.0.1:$puertoApi$Ruta" -Method $Metodo -Headers $cabeceras `
            -UseBasicParsing -TimeoutSec 10 -ContentType 'application/json' -Body $(if ($Metodo -eq 'GET') { $null } else { '{}' })
        return [pscustomobject]@{ Estado = [int]$respuesta.StatusCode; Cuerpo = $respuesta.Content }
    } catch {
        if ($_.Exception.Response) { return [pscustomobject]@{ Estado = [int]$_.Exception.Response.StatusCode; Cuerpo = '' } }
        throw
    }
}

# --- Requisitos -------------------------------------------------------------------------------
Write-Paso 'Requisitos'
if (-not (Test-Docker) -or (Get-EstadoServicio 'db') -ne 'healthy') {
    Write-Falla 'Docker y el servicio db deben estar en marcha (docker compose up -d).'
    exit 2
}
if (Test-Path -LiteralPath $temporal) { Remove-Item -LiteralPath $temporal -Recurse -Force }
New-Item -ItemType Directory -Path $temporal | Out-Null
$basePrueba = 'nexo_prueba_' + (Get-Random -Minimum 1000 -Maximum 9999)
$r = Psql-Super "CREATE DATABASE $basePrueba OWNER nexo_migrator;" 'postgres'
if ($r.ExitCode -ne 0) { Write-Falla "No se pudo crear la base de prueba: $($r.Error.Trim())"; exit 2 }
Write-Ok "Base temporal $basePrueba creada"

try {
    # --- Migraciones ---------------------------------------------------------------------------
    Write-Paso 'Migraciones desde una base vacía'
    Probar 'aplica V000..V004 en orden' {
        $r = Aplicar $basePrueba
        Afirmar ($r.ExitCode -eq 0) "exit $($r.ExitCode): $($r.Salida)$($r.Error)"
        $versiones = Valor 'SELECT string_agg(version, '','' ORDER BY aplicada_en, version) FROM nexo_meta.schema_migrations;' $basePrueba
        Afirmar ($versiones -eq 'V000,V001,V002,V003,V004') "orden registrado: $versiones"
        "($versiones)"
    }
    Probar 'una segunda ejecución no aplica nada' {
        $r = Aplicar $basePrueba
        Afirmar ($r.ExitCode -eq 0 -and $r.Salida -match '0 pendientes') "exit $($r.ExitCode): $($r.Salida)"
        Afirmar ((Valor 'SELECT count(*) FROM nexo_meta.schema_migrations;' $basePrueba) -eq '5') 'el historial cambió'
    }
    Probar 'los datos semilla no se duplican al reejecutarse' {
        $semilla = [IO.File]::ReadAllText((Join-Path $script:RaizRepo 'migrations\V003__seed_data.sql'), $script:Utf8SinBom)
        $r = Invoke-Psql -Sql $semilla -Usuario 'nexo_migrator' -Base $basePrueba -UnaTransaccion
        Afirmar ($r.ExitCode -eq 0) $r.Error.Trim()
        $n = Valor 'SELECT count(*) FROM nexo.materias;' $basePrueba
        Afirmar ($n -eq '38') "hay $n materias"
        '(38 materias)'
    }

    # Copia de las migraciones para las pruebas de error (no se toca migrations/)
    $copia = Join-Path $temporal 'migraciones'
    Copy-Item -LiteralPath (Join-Path $script:RaizRepo 'migrations') -Destination $copia -Recurse
    Remove-Item -LiteralPath (Join-Path $copia 'archive') -Recurse -Force -ErrorAction SilentlyContinue
    Get-ChildItem -LiteralPath $copia -Filter '*.md' | Remove-Item

    Probar 'una migración con error se revierte entera y no se marca' {
        $falla = "CREATE TABLE nexo.no_debe_quedar (id integer);`nSELECT 1 / 0;`n"
        [IO.File]::WriteAllText((Join-Path $copia 'V900__falla_a_proposito.sql'), $falla, $script:Utf8SinBom)
        $r = Aplicar $basePrueba $copia
        Afirmar ($r.ExitCode -eq 1) "se esperaba exit 1, fue $($r.ExitCode)"
        Afirmar ((Valor "SELECT to_regclass('nexo.no_debe_quedar') IS NULL;" $basePrueba) -eq 't') 'la tabla quedó creada'
        Afirmar ((Valor "SELECT count(*) FROM nexo_meta.schema_migrations WHERE version = 'V900';" $basePrueba) -eq '0') 'quedó marcada'
        Afirmar ((Valor "SELECT count(*) FROM nexo_meta.migration_failures WHERE version = 'V900' AND error LIKE '%division by zero%';" $basePrueba) -eq '1') 'no se anotó el fallo'
        Remove-Item -LiteralPath (Join-Path $copia 'V900__falla_a_proposito.sql')
    }
    Probar 'detecta una migración aplicada que fue modificada (exit 3)' {
        $ruta = Join-Path $copia 'V003__seed_data.sql'
        [IO.File]::AppendAllText($ruta, "`n-- cambio no permitido`n", $script:Utf8SinBom)
        $r = Aplicar $basePrueba $copia
        Afirmar ($r.ExitCode -eq 3 -and $r.Salida -match 'cambió') "exit $($r.ExitCode): $($r.Salida)"
        Copy-Item -LiteralPath (Join-Path $script:RaizRepo 'migrations\V003__seed_data.sql') -Destination $ruta -Force
    }
    Probar 'rechaza nombres de archivo inválidos (exit 3)' {
        $ruta = Join-Path $copia 'v5-mal.sql'
        [IO.File]::WriteAllText($ruta, 'SELECT 1;', $script:Utf8SinBom)
        $r = Aplicar $basePrueba $copia
        Remove-Item -LiteralPath $ruta
        Afirmar ($r.ExitCode -eq 3) "exit $($r.ExitCode)"
    }
    Probar 'rechaza migraciones con BEGIN/COMMIT propios (exit 3)' {
        $ruta = Join-Path $copia 'V901__con_transaccion.sql'
        [IO.File]::WriteAllText($ruta, "BEGIN;`nSELECT 1;`nCOMMIT;`n", $script:Utf8SinBom)
        $r = Aplicar $basePrueba $copia
        Remove-Item -LiteralPath $ruta
        Afirmar ($r.ExitCode -eq 3) "exit $($r.ExitCode)"
    }
    Probar 'falla con exit 2 si la base no existe' {
        $r = Aplicar 'base_que_no_existe'
        Afirmar ($r.ExitCode -eq 2) "exit $($r.ExitCode)"
    }

    # --- SQL -----------------------------------------------------------------------------------
    Write-Paso 'Esquema, restricciones y permisos'
    foreach ($archivo in Get-ChildItem -LiteralPath (Join-Path $PSScriptRoot 'sql') -Filter '*.sql' | Sort-Object Name) {
        Probar $archivo.BaseName {
            $r = Invoke-Psql -Sql ([IO.File]::ReadAllText($archivo.FullName, $script:Utf8SinBom)) -Usuario $superusuario -Base $basePrueba
            Afirmar ($r.ExitCode -eq 0) (($r.Error.Trim() -split "`n" | Select-Object -First 3) -join ' ')
        }
    }
} finally {
    $r = Psql-Super "DROP DATABASE IF EXISTS $basePrueba WITH (FORCE);" 'postgres'
    if ($r.ExitCode -eq 0) { Write-Ok "Base temporal $basePrueba eliminada" } else { Write-Aviso "No se pudo borrar $basePrueba" }
    Remove-Item -LiteralPath $temporal -Recurse -Force -ErrorAction SilentlyContinue
}

# --- PostgREST (solo lectura sobre la base real) ---------------------------------------------
Write-Paso "PostgREST sobre la base $baseReal"
$token = & (Join-Path $script:RaizRepo 'scripts\new_jwt.ps1') -Role nexo_lector -Minutes 5
Probar 'anónimo: GET /materias = 200 con 38 filas' {
    $r = Get-Http '/materias'
    Afirmar ($r.Estado -eq 200) "estado $($r.Estado)"
    # ConvertFrom-Json directo (sin tubería) devuelve el arreglo completo en PowerShell 5.1 y 7
    $n = (ConvertFrom-Json $r.Cuerpo).Count
    Afirmar ($n -eq 38) "$n filas"
}
Probar 'anónimo: GET /publicaciones = 401' { $e = (Get-Http '/publicaciones').Estado; Afirmar ($e -eq 401) "estado $e" }
Probar 'tablas internas no expuestas: GET /usuarios = 404' { $e = (Get-Http '/usuarios').Estado; Afirmar ($e -eq 404) "estado $e" }
Probar 'nexo_lector: GET /publicaciones = 200' { $e = (Get-Http '/publicaciones' $token).Estado; Afirmar ($e -eq 200) "estado $e" }
Probar 'nexo_lector: POST /materias denegado' {
    $e = (Get-Http '/materias' $token 'POST').Estado
    Afirmar ($e -in 401, 403, 405) "estado $e"
    "($e)"
}
Probar 'anónimo: POST /materias denegado' { $e = (Get-Http '/materias' $null 'POST').Estado; Afirmar ($e -in 401, 403, 405) "estado $e"; "($e)" }
Probar 'token con firma falsa = 401' {
    $partes = $token.Split('.')
    $falso = $partes[0] + '.' + $partes[1] + '.firmaInvalida'
    $e = (Get-Http '/materias' $falso).Estado
    Afirmar ($e -eq 401) "estado $e"
}

# --- Persistencia ------------------------------------------------------------------------------
if ($IncluirReinicio) {
    Write-Paso 'Persistencia tras reiniciar los contenedores'
    Probar 'datos e historial siguen después de docker compose restart' {
        $antes = Valor 'SELECT (SELECT count(*) FROM nexo.usuarios) || ''/'' || (SELECT count(*) FROM nexo_meta.schema_migrations);' $baseReal
        $r = Invoke-Compose @('restart')
        Afirmar ($r.ExitCode -eq 0) $r.Error
        $limite = (Get-Date).AddSeconds(90)
        while ((Get-EstadoServicio 'db') -ne 'healthy' -and (Get-Date) -lt $limite) { Start-Sleep -Seconds 2 }
        $despues = Valor 'SELECT (SELECT count(*) FROM nexo.usuarios) || ''/'' || (SELECT count(*) FROM nexo_meta.schema_migrations);' $baseReal
        Afirmar ($antes -eq $despues) "antes $antes, después $despues"
        $limite = (Get-Date).AddSeconds(60)
        do { Start-Sleep -Seconds 2; $e = (Get-Http '/materias?limit=1').Estado } while ($e -ne 200 -and (Get-Date) -lt $limite)
        Afirmar ($e -eq 200) "PostgREST no volvió (estado $e)"
        "(usuarios/migraciones: $despues)"
    }
}

Write-Host ''
if ($fallas -gt 0) {
    Write-Falla "$fallas de $total pruebas fallaron."
    exit 1
}
Write-Ok "$total de $total pruebas correctas."
exit 0
