<#
.SYNOPSIS
    Crea .env con contraseñas aleatorias y nexo/config/config.local.php para la app PHP.

.DESCRIPTION
    Nunca sobrescribe archivos existentes. No muestra ninguna contraseña.
    Códigos de salida: 0 creado o ya existía · 1 error.
#>
[CmdletBinding()]
param()

. "$PSScriptRoot\_comun.ps1"
$ErrorActionPreference = 'Stop'

function New-Secreto([int]$Largo) {
    $letras = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789'
    $bytes = New-Object byte[] $Largo
    $generador = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    $generador.GetBytes($bytes)
    $generador.Dispose()
    return -join ($bytes | ForEach-Object { $letras[$_ % $letras.Length] })
}

$archivoEnv = Join-Path $script:RaizRepo '.env'
if (Test-Path -LiteralPath $archivoEnv) {
    Write-Ok '.env ya existe; no se modificó.'
} else {
    $plantilla = [IO.File]::ReadAllText((Join-Path $script:RaizRepo '.env.example'), $script:Utf8SinBom)
    $secretos = @{
        'POSTGRES_SUPERUSER_PASSWORD' = New-Secreto 32
        'NEXO_MIGRATOR_PASSWORD' = New-Secreto 32
        'NEXO_APP_PASSWORD' = New-Secreto 32
        'PGRST_AUTHENTICATOR_PASSWORD' = New-Secreto 32
        'PGRST_JWT_SECRET' = New-Secreto 48
    }
    foreach ($clave in $secretos.Keys) {
        $plantilla = [regex]::Replace($plantilla, "(?m)^$clave=.*$", "$clave=$($secretos[$clave])")
    }
    [IO.File]::WriteAllText($archivoEnv, $plantilla, $script:Utf8SinBom)
    Write-Ok '.env creado con contraseñas aleatorias (no se muestran).'
}

$entorno = Read-DotEnv $archivoEnv
$configLocal = Join-Path $script:RaizRepo 'nexo\config\config.local.php'
if (Test-Path -LiteralPath $configLocal) {
    Write-Ok 'nexo/config/config.local.php ya existe; no se modificó.'
} else {
    $puerto = if ($entorno['POSTGRES_HOST_PORT']) { $entorno['POSTGRES_HOST_PORT'] } else { '5432' }
    $php = @"
<?php
// Generado por scripts/new_env.ps1. No se sube a Git.
define('DB_MOTOR', 'pgsql');
define('DB_HOST', '127.0.0.1');
define('DB_PUERTO', $puerto);
define('DB_NOMBRE', '$($entorno['POSTGRES_DB'])');
define('DB_USUARIO', 'nexo_app');
define('DB_CONTRASENA', '$($entorno['NEXO_APP_PASSWORD'])');
"@
    [IO.File]::WriteAllText($configLocal, $php, $script:Utf8SinBom)
    Write-Ok 'nexo/config/config.local.php creado (la app PHP usará PostgreSQL con el rol nexo_app).'
}
exit 0
