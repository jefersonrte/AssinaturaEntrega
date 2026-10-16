# vm-run.ps1 — executado DENTRO da VM (via tools/vm.sh run [caminho]).
# Sessões SSH no Windows rodam na sessão 0, sem área de trabalho; por isso o app é
# aberto por uma tarefa agendada interativa, na sessão gráfica do usuário logado.
param(
    [string]$Exe = "C:\dev\AssinaturaEntrega\publish\AssinaturaEntrega.exe"
)
$ErrorActionPreference = "Stop"
$name = "AbrirAssinaturaEntrega"

if (-not (Test-Path $Exe)) { throw "Não encontrado: $Exe (rode tools/vm.sh build)" }
Stop-Process -Name AssinaturaEntrega -Force -ErrorAction SilentlyContinue

$action    = New-ScheduledTaskAction -Execute $Exe -WorkingDirectory (Split-Path $Exe)
$principal = New-ScheduledTaskPrincipal -UserId $env:USERNAME -LogonType Interactive -RunLevel Highest
$settings  = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -ExecutionTimeLimit 0
Register-ScheduledTask -TaskName $name -Action $action -Principal $principal -Settings $settings -Force | Out-Null
Start-ScheduledTask -TaskName $name

$ok = $false
for ($i = 0; $i -lt 20; $i++) {
    Start-Sleep 1
    if (Get-Process AssinaturaEntrega -ErrorAction SilentlyContinue) { $ok = $true; break }
}
if ($ok) {
    Start-Sleep 3
    if (Get-Process AssinaturaEntrega -ErrorAction SilentlyContinue) {
        Write-Output "App iniciado na VM (sessão gráfica): $Exe"
    } else {
        throw "App abriu e fechou em seguida (travou?). Veja o Visualizador de Eventos > Aplicativo."
    }
} else {
    $info = Get-ScheduledTaskInfo -TaskName $name
    throw "App não iniciou. LastTaskResult=$($info.LastTaskResult)"
}
