# vm-run.ps1 — executado DENTRO da VM (via tools/vm.sh run).
# Sessões SSH no Windows rodam na sessão 0, sem área de trabalho; por isso o app é
# aberto por uma tarefa agendada interativa, na sessão gráfica do usuário logado.
$ErrorActionPreference = "Stop"
$exe  = "C:\dev\AssinaturaEntrega\publish\AssinaturaEntrega.exe"
$name = "AbrirAssinaturaEntrega"

if (-not (Test-Path $exe)) { throw "Não encontrado: $exe (rode tools/vm.sh build)" }
Stop-Process -Name AssinaturaEntrega -Force -ErrorAction SilentlyContinue

$action    = New-ScheduledTaskAction -Execute $exe -WorkingDirectory (Split-Path $exe)
$principal = New-ScheduledTaskPrincipal -UserId $env:USERNAME -LogonType Interactive -RunLevel Highest
$settings  = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -ExecutionTimeLimit 0
Register-ScheduledTask -TaskName $name -Action $action -Principal $principal -Settings $settings -Force | Out-Null
Start-ScheduledTask -TaskName $name

$ok = $false
for ($i = 0; $i -lt 20; $i++) {
    Start-Sleep 1
    if (Get-Process AssinaturaEntrega -ErrorAction SilentlyContinue) { $ok = $true; break }
}
if ($ok) { Write-Output "App iniciado na VM (sessão gráfica)." }
else {
    $info = Get-ScheduledTaskInfo -TaskName $name
    throw "App não iniciou. LastTaskResult=$($info.LastTaskResult)"
}
