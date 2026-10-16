<#
    build.ps1 — Gera o .exe do aplicativo (Windows 11, PowerShell).

    Uso:
        .\build.ps1                 # .exe único, não precisa de .NET instalado no PC de destino
        .\build.ps1 -FrameworkDependent   # .exe menor, exige .NET 8 Desktop Runtime no destino

    Resultado em:  .\publish\AssinaturaEntrega.exe
#>

param(
    [switch]$FrameworkDependent
)

$ErrorActionPreference = "Stop"
$proj = Join-Path $PSScriptRoot "src\AssinaturaEntrega\AssinaturaEntrega.csproj"
$out  = Join-Path $PSScriptRoot "publish"

Write-Host "Limpando saída anterior..." -ForegroundColor Cyan
if (Test-Path $out) { Remove-Item $out -Recurse -Force }

if ($FrameworkDependent) {
    Write-Host "Publicando (framework-dependent)..." -ForegroundColor Cyan
    dotnet publish $proj -c Release -r win-x64 --self-contained false `
        -p:PublishSingleFile=true `
        -o $out
} else {
    Write-Host "Publicando (self-contained, .exe único)..." -ForegroundColor Cyan
    dotnet publish $proj -c Release -r win-x64 --self-contained true `
        -p:PublishSingleFile=true `
        -p:IncludeNativeLibrariesForSelfExtract=true `
        -p:EnableCompressionInSingleFile=true `
        -o $out
}

Write-Host ""
Write-Host "Pronto! Executável em: $out\AssinaturaEntrega.exe" -ForegroundColor Green
Write-Host "Lembre de editar o appsettings.json (ao lado do .exe) com a URL da sua API." -ForegroundColor Yellow
