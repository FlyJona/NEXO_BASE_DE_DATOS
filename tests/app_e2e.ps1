<#
.SYNOPSIS
    Prueba de extremo a extremo de la app PHP (XAMPP) contra la base configurada en nexo/config.

.DESCRIPTION
    Requiere Apache de XAMPP en marcha, la app en http://localhost/nexo y los datos de prueba
    de instalar.php. Crea una publicación ficticia, la prueba con varios usuarios y la elimina al final.
    Sirve para comprobar que el cambio de MySQL a PostgreSQL no rompió ningún flujo.
    Códigos de salida: 0 todo correcto · 1 alguna prueba falló · 2 la app no responde.
#>
[CmdletBinding()]
param(
    [string]$Url = 'http://localhost/nexo',
    [string]$Contrasena = 'NexoPrueba2026'
)

. (Join-Path (Split-Path -Parent $PSScriptRoot) 'scripts\_comun.ps1')
$ErrorActionPreference = 'Stop'

$temporal = Join-Path $PSScriptRoot '.tmp\e2e'
New-Item -ItemType Directory -Path $temporal -Force | Out-Null
$fallas = 0
$total = 0
function Afirmar([bool]$Condicion, [string]$Texto) {
    $script:total++
    if ($Condicion) { Write-Ok $Texto } else { $script:fallas++; Write-Falla $Texto }
}
function Invoke-Curl([string[]]$Argumentos) { return (Invoke-Programa 'curl.exe' (@('-s') + $Argumentos)).Salida }
function Csrf([string]$Html) {
    if ($Html -match 'name="csrf" value="([0-9a-f]+)"') { return $Matches[1] }
    throw 'No se encontró el token CSRF'
}
function Pagina([string]$Galleta, [string]$Ruta) { return Invoke-Curl @('-L', '-b', $Galleta, '-c', $Galleta, "$Url/$Ruta") }
function Codigo([string]$Galleta, [string]$Ruta, [string[]]$Datos = @()) {
    $argumentos = @('-o', 'NUL', '-w', '%{http_code}')
    if ($Galleta) { $argumentos += @('-b', $Galleta) }
    foreach ($d in $Datos) { $argumentos += @('--data', $d) }
    return (Invoke-Curl ($argumentos + "$Url/$Ruta")).Trim()
}
function Entrar([string]$Nombre, [string]$Correo) {
    $galleta = Join-Path $temporal "$Nombre.txt"
    Remove-Item -LiteralPath $galleta -ErrorAction SilentlyContinue
    $csrf = Csrf (Pagina $galleta 'login.php')
    $html = Invoke-Curl @('-L', '-b', $galleta, '-c', $galleta, '--data-urlencode', "csrf=$csrf",
        '--data-urlencode', "correo=$Correo", '--data-urlencode', "contrasena=$Contrasena", "$Url/login.php")
    if ($html -notmatch 'Comunidad') { throw "No se pudo iniciar sesión como $Correo" }
    return $galleta
}

if ((Codigo $null 'login.php') -ne '200') {
    Write-Falla "La app no responde en $Url (¿Apache encendido?)"
    exit 2
}

try {
    Write-Paso 'Inicio de sesión de las cuentas de prueba'
    $luis = Entrar 'luis' 'luis.prueba@tesh.edu.mx'
    $ana = Entrar 'ana' 'ana.prueba@tesh.edu.mx'
    $mod = Entrar 'mod' 'moderador@tesh.edu.mx'
    Afirmar $true 'luis, ana y moderador inician sesión'

    Write-Paso 'Publicación resguardada con código y adjunto'
    $contenido = Join-Path $temporal 'contenido.txt'
    [IO.File]::WriteAllText($contenido, "Mi consulta falla:`n`n``````sql`nSELECT * FROM materias;`n```````n`nAyuda <b>porfa</b>", $script:Utf8SinBom)
    $adjunto = Join-Path $temporal 'notas.txt'
    [IO.File]::WriteAllText($adjunto, 'apuntes de prueba', $script:Utf8SinBom)
    $csrf = Csrf (Pagina $luis 'publicar.php')
    $html = Invoke-Curl @('-L', '-b', $luis, '-c', $luis, '-w', '\nURL:%{url_effective}', '-F', "csrf=$csrf", '-F', 'tipo=pregunta',
        '-F', 'materia_id=21', '-F', 'titulo=Prueba e2e PostgreSQL', '-F', "contenido=<$contenido", '-F', 'resguardada=on',
        '-F', "adjuntos[]=@$adjunto", "$Url/publicar.php")
    $id = if ($html -match 'URL:.*publicacion\.php\?id=(\d+)') { [int]$Matches[1] } else { 0 }
    Afirmar ($id -gt 0) "publicación creada (id $id)"
    Afirmar ($html -match 'language-sql') 'bloque de código con lenguaje'
    Afirmar ($html -match '&lt;b&gt;porfa') 'HTML del usuario escapado'
    Afirmar ($html -match 'notas\.txt') 'adjunto listado'

    Write-Paso 'Participación resguardada'
    $html = Pagina $ana "publicacion.php?id=$id"
    Afirmar ($html -match 'Estudiante resguardado' -and $html -notmatch 'Luis Prueba') 'otro estudiante no ve al autor'
    Afirmar ((Pagina $mod "publicacion.php?id=$id") -match 'visible para moderaci') 'el moderador sí lo ve'
    $csrf = Csrf $html
    foreach ($accion in 'votar', 'guardar') { Codigo $ana 'acciones.php' @("csrf=$csrf&accion=$accion&publicacion_id=$id") | Out-Null }
    $html = Invoke-Curl @('-L', '-b', $ana, '-c', $ana, '--data', "csrf=$csrf&accion=comentar&publicacion_id=$id&resguardado=on",
        '--data-urlencode', 'contenido=Te falta el punto y coma', "$Url/acciones.php")
    Afirmar ($html -match 'til \(1\)') 'voto contado'
    Afirmar ($html -match 'Guardada') 'publicación guardada'
    Afirmar ($html -match 'Te falta el punto y coma') 'respuesta publicada'

    Write-Paso 'Permisos y moderación'
    Afirmar ((Codigo $ana 'acciones.php' @("csrf=$csrf&accion=visibilidad&publicacion_id=$id")) -eq '403') 'un estudiante no cambia la visibilidad ajena'
    Afirmar ((Codigo $ana 'usuarios.php') -eq '403') 'un estudiante no entra a usuarios.php'
    $csrfMod = Csrf (Pagina $mod "publicacion.php?id=$id")
    Codigo $mod 'acciones.php' @("csrf=$csrfMod&accion=moderar&publicacion_id=$id") | Out-Null
    Afirmar ((Codigo $ana "publicacion.php?id=$id") -eq '404') 'publicación oculta no visible para otros'
    Afirmar ((Codigo $luis "publicacion.php?id=$id") -eq '200') 'el autor sí la ve'

    Write-Paso 'Perfil y búsqueda (sin distinguir mayúsculas ni acentos)'
    $csrf = Csrf (Pagina $ana 'perfil_editar.php')
    Invoke-Curl @('-L', '-b', $ana, '-c', $ana, '--data-urlencode', "csrf=$csrf", '--data-urlencode', 'nombre=Ana Prueba López',
        '--data-urlencode', 'carrera=ISC', '--data', 'semestre=3&materias[]=12&materias[]=7',
        '--data-urlencode', 'habilidades=Java, java, Python', '--data-urlencode', 'intereses=robótica', "$Url/perfil_editar.php") | Out-Null
    Afirmar ((Pagina $ana 'perfil.php') -match 'Java</a>\s*<a[^>]*>Python') 'perfil guardado sin etiquetas repetidas'
    Afirmar ((Pagina $luis 'estudiantes.php?q=PYTHON') -match 'Ana Prueba') 'busca "PYTHON" sin distinguir mayúsculas'
    Afirmar ((Pagina $luis 'estudiantes.php?q=robotica') -match 'Ana Prueba') 'busca "robotica" sin distinguir acentos'
    Afirmar ((Pagina $luis 'estudiantes.php?materia=12') -match 'Ana Prueba') 'busca por materia'

    Write-Paso 'Recuperación de contraseña'
    $galleta = Join-Path $temporal 'rec.txt'
    $csrf = Csrf (Pagina $galleta 'recuperar.php')
    $html = Invoke-Curl @('-b', $galleta, '-c', $galleta, '--data', "csrf=$csrf&correo=docente@tesh.edu.mx", "$Url/recuperar.php")
    $token = if ($html -match 'restablecer\.php\?token=([0-9a-f]+)') { $Matches[1] } else { '' }
    Afirmar ($token -ne '') 'enlace de recuperación generado'
    $csrf = Csrf (Pagina $galleta "restablecer.php?token=$token")
    $html = Invoke-Curl @('-L', '-b', $galleta, '-c', $galleta, '--data', "csrf=$csrf&token=$token&contrasena=$Contrasena&confirmacion=$Contrasena", "$Url/restablecer.php")
    Afirmar ($html -match 'se actualiz') 'contraseña restablecida'
    Afirmar ((Pagina $galleta "restablecer.php?token=$token") -match 'no es v') 'el enlace no sirve dos veces'

    Write-Paso 'Limpieza'
    $csrf = Csrf (Pagina $luis "publicacion.php?id=$id")
    Codigo $luis 'acciones.php' @("csrf=$csrf&accion=eliminar&publicacion_id=$id") | Out-Null
    Afirmar ((Codigo $luis "publicacion.php?id=$id") -eq '404') 'la publicación de prueba se eliminó'
} catch {
    $fallas++
    Write-Falla $_.Exception.Message
} finally {
    Remove-Item -LiteralPath $temporal -Recurse -Force -ErrorAction SilentlyContinue
}

Write-Host ''
if ($fallas -gt 0) { Write-Falla "$fallas de $total comprobaciones fallaron."; exit 1 }
Write-Ok "$total de $total comprobaciones correctas."
exit 0
