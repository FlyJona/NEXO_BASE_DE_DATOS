<#
.SYNOPSIS
    Aplica en orden las migraciones pendientes de migrations/ a PostgreSQL (servicio "db" de compose.yaml).

.DESCRIPTION
    - Cada migración corre en UNA transacción junto con su registro en nexo_meta.schema_migrations:
      si falla, no queda nada aplicado ni marcado. El fallo se anota aparte en nexo_meta.migration_failures.
    - Una migración ya aplicada nunca se vuelve a ejecutar. Si su archivo cambió (checksum distinto), se detiene.
    - Nunca borra datos ni volúmenes.

    Códigos de salida: 0 correcto · 1 falló una migración · 2 faltan requisitos · 3 conjunto de migraciones inválido.

.EXAMPLE
    .\scripts\apply_migrations.ps1
    .\scripts\apply_migrations.ps1 -DryRun
#>
[CmdletBinding()]
param(
    # Base de datos destino. Por defecto POSTGRES_DB de .env.
    [string]$Database,
    # Carpeta con los archivos VNNN__descripcion.sql.
    [string]$MigrationsPath,
    # Rol que ejecuta las migraciones (dueño de los objetos).
    [string]$User = 'nexo_migrator',
    # Solo muestra qué se aplicaría.
    [switch]$DryRun
)

. "$PSScriptRoot\_comun.ps1"
$ErrorActionPreference = 'Stop'

if (-not $MigrationsPath) { $MigrationsPath = Join-Path $script:RaizRepo 'migrations' }

# 1. Requisitos
Write-Paso 'Comprobando requisitos'
$archivoEnv = Join-Path $script:RaizRepo '.env'
if (-not (Test-Path -LiteralPath $archivoEnv)) {
    Write-Falla "No existe .env. Créalo con .\scripts\new_env.ps1"
    exit 2
}
$entorno = Read-DotEnv $archivoEnv
if (-not $Database) { $Database = $entorno['POSTGRES_DB'] }
if (-not $Database) {
    Write-Falla 'POSTGRES_DB no está definido en .env'
    exit 2
}
if (-not (Test-Docker)) {
    Write-Falla 'Docker / docker compose no está disponible. Abre Docker Desktop.'
    exit 2
}
Write-Ok 'docker compose disponible'

# 2. PostgreSQL listo (no solo iniciado)
$estado = Get-EstadoServicio 'db'
if ($estado -ne 'healthy') {
    Write-Falla "El servicio db no está listo (estado: '$estado'). Ejecuta: docker compose up -d"
    exit 2
}
$listo = Invoke-Compose @('exec', '-T', 'db', 'pg_isready', '-U', $User, '-d', $Database)
if ($listo.ExitCode -ne 0) {
    Write-Falla "PostgreSQL no acepta conexiones para $User en $Database. $($listo.Salida)$($listo.Error)"
    exit 2
}
Write-Ok "PostgreSQL acepta conexiones (base $Database, rol $User)"

# 3. Archivos de migración
Write-Paso "Leyendo migraciones de $MigrationsPath"
if (-not (Test-Path -LiteralPath $MigrationsPath)) {
    Write-Falla "No existe la carpeta $MigrationsPath"
    exit 3
}
$patron = '^V(\d{3})__([a-z0-9_]+)\.sql$'
$migraciones = @()
foreach ($archivo in Get-ChildItem -LiteralPath $MigrationsPath -Filter '*.sql' -File | Sort-Object Name) {
    if ($archivo.Name -notmatch $patron) {
        Write-Falla "Nombre no válido: $($archivo.Name) (se espera VNNN__descripcion.sql en minúsculas)"
        exit 3
    }
    $version = 'V' + $Matches[1]
    $descripcion = $Matches[2] -replace '_', ' '
    if ($migraciones | Where-Object { $_.Version -eq $version }) {
        Write-Falla "Versión repetida: $version"
        exit 3
    }
    $contenido = [IO.File]::ReadAllText($archivo.FullName, $script:Utf8SinBom)
    if ($contenido -match '(?im)^\s*(BEGIN|COMMIT|ROLLBACK|START\s+TRANSACTION)\s*;') {
        Write-Falla "$($archivo.Name) controla transacciones por su cuenta; el script ya abre una por migración."
        exit 3
    }
    $migraciones += [pscustomobject]@{
        Version = $version; Descripcion = $descripcion; Archivo = $archivo.Name
        Ruta = $archivo.FullName; Contenido = $contenido; Checksum = Get-ChecksumMigracion $archivo.FullName
    }
}
if ($migraciones.Count -eq 0) {
    Write-Falla 'No hay archivos de migración.'
    exit 3
}
Write-Ok "$($migraciones.Count) archivos encontrados"

# 4. Historial (se crea si no existe)
$historial = @'
CREATE SCHEMA IF NOT EXISTS nexo_meta;
CREATE TABLE IF NOT EXISTS nexo_meta.schema_migrations (
    version      TEXT PRIMARY KEY,
    descripcion  TEXT NOT NULL,
    archivo      TEXT NOT NULL,
    checksum     CHAR(64) NOT NULL,
    aplicada_en  TIMESTAMPTZ NOT NULL DEFAULT now(),
    aplicada_por TEXT NOT NULL DEFAULT current_user,
    duracion_ms  INTEGER NOT NULL
);
CREATE TABLE IF NOT EXISTS nexo_meta.migration_failures (
    id        BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    version   TEXT NOT NULL,
    archivo   TEXT NOT NULL,
    checksum  CHAR(64) NOT NULL,
    fallo_en  TIMESTAMPTZ NOT NULL DEFAULT now(),
    error     TEXT NOT NULL
);
COMMENT ON TABLE nexo_meta.schema_migrations IS 'Migraciones aplicadas con éxito (scripts/apply_migrations.ps1).';
COMMENT ON TABLE nexo_meta.migration_failures IS 'Intentos fallidos; la migración no quedó aplicada.';
REVOKE ALL ON SCHEMA nexo_meta FROM PUBLIC;
'@
$r = Invoke-Psql -Sql $historial -Usuario $User -Base $Database -UnaTransaccion
if ($r.ExitCode -ne 0) {
    Write-Falla "No se pudo preparar el historial de migraciones: $($r.Error.Trim())"
    exit 2
}
$r = Invoke-Psql -Sql 'SELECT version, checksum FROM nexo_meta.schema_migrations ORDER BY version;' -Usuario $User -Base $Database -Tabular
if ($r.ExitCode -ne 0) {
    Write-Falla "No se pudo leer el historial: $($r.Error.Trim())"
    exit 2
}
$aplicadas = @{}
foreach ($linea in ($r.Salida -split "`n")) {
    $linea = $linea.Trim()
    if ($linea -eq '') { continue }
    $partes = $linea.Split('|')
    $aplicadas[$partes[0]] = $partes[1].Trim()
}

# 5. Coherencia: lo aplicado no cambió y no hay versiones aplicadas sin archivo
foreach ($version in $aplicadas.Keys) {
    $migracion = $migraciones | Where-Object { $_.Version -eq $version }
    if (-not $migracion) {
        Write-Falla "La versión $version está aplicada pero su archivo no existe en $MigrationsPath"
        exit 3
    }
    if ($migracion.Checksum -ne $aplicadas[$version]) {
        Write-Falla "$($migracion.Archivo) cambió después de aplicarse. No edites migraciones aplicadas: crea una nueva."
        exit 3
    }
}
$pendientes = @($migraciones | Where-Object { -not $aplicadas.ContainsKey($_.Version) })
$ultimaAplicada = ($aplicadas.Keys | Sort-Object | Select-Object -Last 1)
foreach ($pendiente in $pendientes) {
    if ($ultimaAplicada -and $pendiente.Version -lt $ultimaAplicada) {
        Write-Falla "$($pendiente.Archivo) es anterior a la última aplicada ($ultimaAplicada). Usa un número mayor."
        exit 3
    }
}
Write-Ok "$($aplicadas.Count) aplicadas, $($pendientes.Count) pendientes"

if ($pendientes.Count -eq 0) {
    Write-Ok 'La base de datos está al día.'
    exit 0
}
if ($DryRun) {
    Write-Paso 'Modo -DryRun: se aplicarían'
    $pendientes | ForEach-Object { Write-Host "      $($_.Archivo)" }
    exit 0
}

# 6. Aplicar en orden; se detiene al primer error
foreach ($m in $pendientes) {
    Write-Paso "Aplicando $($m.Archivo)"
    $registro = "`n;`nINSERT INTO nexo_meta.schema_migrations (version, descripcion, archivo, checksum, duracion_ms) " +
        "VALUES ('$($m.Version)', '$($m.Descripcion)', '$($m.Archivo)', '$($m.Checksum)', " +
        "(extract(epoch FROM clock_timestamp() - now()) * 1000)::integer);`n"
    $r = Invoke-Psql -Sql ($m.Contenido + $registro) -Usuario $User -Base $Database -UnaTransaccion
    if ($r.ExitCode -ne 0) {
        $mensajeError = ($r.Error.Trim() -replace '\s*\r?\n\s*', ' | ')
        if ($mensajeError.Length -gt 2000) { $mensajeError = $mensajeError.Substring(0, 2000) }
        Write-Falla "$($m.Archivo) falló y se revirtió por completo:"
        Write-Host "      $mensajeError" -ForegroundColor Red
        $anotar = "INSERT INTO nexo_meta.migration_failures (version, archivo, checksum, error) VALUES (:'version', :'archivo', :'checksum', :'error');"
        $variables = @{ version = $m.Version; archivo = $m.Archivo; checksum = $m.Checksum; error = $mensajeError }
        $anotado = Invoke-Psql -Sql $anotar -Usuario $User -Base $Database -Variables $variables
        if ($anotado.ExitCode -ne 0) { Write-Aviso "No se pudo anotar el fallo: $($anotado.Error.Trim())" }
        exit 1
    }
    Write-Ok "$($m.Archivo) aplicada"
}

Write-Ok "Se aplicaron $($pendientes.Count) migraciones."
exit 0
