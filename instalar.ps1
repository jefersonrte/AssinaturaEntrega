<#
.SYNOPSIS
  Instala (ou atualiza) o Captura de Assinatura de Entrega a partir do último Release do GitHub.

.DESCRIPTION
  Uso rápido (PowerShell, no PC do cliente):

    irm https://raw.githubusercontent.com/jefersonrte/AssinaturaEntrega/main/instalar.ps1 | iex

  O script baixa o Setup mais recente e o executa. Os dados do servidor podem vir:
    1. de variáveis de ambiente definidas antes do irm:
         $env:AE_URL='http://servidor:8080'; $env:AE_TOKEN='xxxx'; $env:AE_DEVICE='BALCAO-01'
         irm https://raw.githubusercontent.com/jefersonrte/AssinaturaEntrega/main/instalar.ps1 | iex
    2. ou perguntados na tela (modo interativo), se não houver variáveis.
  Em atualização (config já existente em C:\ProgramData\AssinaturaEntrega) nada é perguntado.

  Parâmetros opcionais: -Url, -Token, -Device, -Silencioso (sem janela de progresso), -Versao (ex.: v1.0.0).
#>
param(
    [string]$Url    = $env:AE_URL,
    [string]$Token  = $env:AE_TOKEN,
    [string]$Device = $env:AE_DEVICE,
    [string]$Versao = $env:AE_VERSAO,
    [switch]$Silencioso
)

$ErrorActionPreference = 'Stop'
$ProgressPreference    = 'SilentlyContinue'
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

$repo      = 'jefersonrte/AssinaturaEntrega'
$configDir = Join-Path $env:ProgramData 'AssinaturaEntrega'
$configOk  = Test-Path (Join-Path $configDir 'appsettings.json')

Write-Host ''
Write-Host '=== Captura de Assinatura de Entrega — instalador ===' -ForegroundColor Cyan

# ---- 1. Descobre o release ----
$api = if ($Versao) { "https://api.github.com/repos/$repo/releases/tags/$Versao" }
       else         { "https://api.github.com/repos/$repo/releases/latest" }
Write-Host "Procurando a versão mais recente em github.com/$repo ..."
$rel   = Invoke-RestMethod -Uri $api -Headers @{ 'User-Agent' = 'AssinaturaEntrega-instalador' }
$asset = $rel.assets | Where-Object { $_.name -like 'AssinaturaEntrega-Setup-*.exe' } | Select-Object -First 1
if (-not $asset) { throw "O release $($rel.tag_name) não tem o arquivo AssinaturaEntrega-Setup-*.exe." }
Write-Host ("Versão {0}  ({1:N0} MB)" -f $rel.tag_name, ($asset.size / 1MB))

# ---- 2. Baixa o Setup ----
$setup = Join-Path $env:TEMP $asset.name
Write-Host "Baixando $($asset.name) ..."
Invoke-WebRequest -Uri $asset.browser_download_url -OutFile $setup -UseBasicParsing
if ((Get-Item $setup).Length -ne $asset.size) { throw 'Download incompleto; tente novamente.' }

# ---- 3. Dados do servidor (só na primeira instalação) ----
if (-not $configOk) {
    $interativo = [Environment]::UserInteractive -and -not [Console]::IsInputRedirected
    if (-not $Url -and $interativo) {
        Write-Host ''
        Write-Host 'Dados fornecidos pelo seu fornecedor (pode deixar em branco e configurar depois):' -ForegroundColor Yellow
        $Url   = Read-Host '  URL do servidor (ex.: http://192.168.1.10:8080)'
        $Token = Read-Host '  Token do cliente'
        $d     = Read-Host "  Identificação deste computador [$env:COMPUTERNAME]"
        if ($d) { $Device = $d }
    }
    if (-not $Device) { $Device = $env:COMPUTERNAME }
} else {
    Write-Host "Configuração existente em $configDir será mantida (atualização)."
}

# ---- 4. Executa o Setup (pede elevação/UAC sozinho) ----
$args = @('/SP-', '/SUPPRESSMSGBOXES', '/NORESTART', '/CLOSEAPPLICATIONS')
$args += if ($Silencioso) { '/VERYSILENT' } else { '/SILENT' }
if ($Url)    { $args += "/URL=$Url" }
if ($Token)  { $args += "/TOKEN=$Token" }
if ($Device) { $args += "/DEVICE=$Device" }

Write-Host 'Instalando...'
$p = Start-Process -FilePath $setup -ArgumentList $args -Wait -PassThru
switch ($p.ExitCode) {
    0       { Write-Host "Pronto! Instalado em 'Arquivos de Programas\AssinaturaEntrega' — atalho no Menu Iniciar." -ForegroundColor Green }
    2       { throw 'Instalação cancelada pelo usuário.' }
    default { throw "O instalador retornou o código $($p.ExitCode)." }
}
Remove-Item $setup -ErrorAction SilentlyContinue
