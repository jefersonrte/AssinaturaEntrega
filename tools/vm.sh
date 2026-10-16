#!/usr/bin/env bash
# tools/vm.sh — atalhos para a VM Windows 11 (quickemu) usada para compilar/testar o app.
#
#   tools/vm.sh start        inicia a VM (abre o visualizador spicy)
#   tools/vm.sh stop         desliga a VM (shutdown limpo do Windows)
#   tools/vm.sh ssh [cmd]    abre um PowerShell na VM (ou roda um comando)
#   tools/vm.sh build        envia os fontes, compila na VM e traz publish/AssinaturaEntrega.exe
#   tools/vm.sh run          abre o app compilado dentro da VM (na sessão gráfica)
#   tools/vm.sh webcam       lista webcams USB do host e explica como passar para a VM
#
# Requisitos (já configurados em 2026-10-15): quickemu, VM em ~/VMs, chave ~/.ssh/id_ed25519_vmwin,
# OpenSSH Server na VM (porta 22220 no host), .NET 8 SDK na VM.
set -euo pipefail

VM_DIR="$HOME/VMs"
VM_CONF="windows-11-Brazilian-Portuguese.conf"
SSH_KEY="$HOME/.ssh/id_ed25519_vmwin"
SSH_PORT=22220
SSH_USER="Quickemu"
GUEST_DIR='C:/dev/AssinaturaEntrega'

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SSH_OPTS=(-i "$SSH_KEY" -o BatchMode=yes -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o LogLevel=ERROR)

vm_ssh() { ssh "${SSH_OPTS[@]}" -p "$SSH_PORT" "$SSH_USER@localhost" "$@"; }
vm_scp() { scp "${SSH_OPTS[@]}" -P "$SSH_PORT" "$@"; }

vm_running() { pidof qemu-system-x86_64 >/dev/null 2>&1; }

wait_ssh() {
    echo "Aguardando SSH da VM..."
    for _ in $(seq 1 60); do
        vm_ssh 'exit' 2>/dev/null && return 0
        sleep 5
    done
    echo "SSH não respondeu em 5 min." >&2
    return 1
}

cmd_start() {
    if vm_running; then echo "VM já está rodando."; else (cd "$VM_DIR" && quickemu --vm "$VM_CONF"); fi
}

cmd_stop() {
    vm_running || { echo "VM não está rodando."; return 0; }
    vm_ssh 'Stop-Computer -Force' || true
    echo "Desligando o Windows..."
    for _ in $(seq 1 30); do vm_running || { echo "VM desligada."; return 0; }; sleep 2; done
    echo "Ainda ligada; forçando: quickemu --kill"
    (cd "$VM_DIR" && quickemu --vm "$VM_CONF" --kill)
}

cmd_ssh() {
    if [ $# -gt 0 ]; then vm_ssh "$@"; else ssh "${SSH_OPTS[@]}" -t -p "$SSH_PORT" "$SSH_USER@localhost"; fi
}

cmd_build() {
    vm_running || { echo "VM desligada. Rode: tools/vm.sh start" >&2; exit 1; }
    wait_ssh
    local zip; zip="$(mktemp --suffix=.zip)"
    rm -f "$zip"
    echo "Empacotando fontes..."
    (cd "$ROOT/.." && zip -qr "$zip" "$(basename "$ROOT")" \
        -x "*/.git/*" "*/bin/*" "*/obj/*" "*/publish/*")
    echo "Enviando para a VM..."
    vm_ssh 'New-Item -ItemType Directory -Force -Path C:\temp,C:\dev | Out-Null'
    vm_scp "$zip" "$SSH_USER@localhost:C:/temp/AssinaturaEntrega.zip"
    rm -f "$zip"
    echo "Compilando na VM..."
    vm_ssh "\$ErrorActionPreference='Stop'; \$ProgressPreference='SilentlyContinue';
        Stop-Process -Name AssinaturaEntrega -Force -ErrorAction SilentlyContinue;
        Expand-Archive C:\\temp\\AssinaturaEntrega.zip -DestinationPath C:\\dev -Force;
        Set-Location $GUEST_DIR;
        if (Test-Path publish) { Remove-Item publish -Recurse -Force };
        dotnet publish src\\AssinaturaEntrega\\AssinaturaEntrega.csproj -c Release -r win-x64 --self-contained true -p:PublishSingleFile=true -p:IncludeNativeLibrariesForSelfExtract=true -p:EnableCompressionInSingleFile=true -o publish -nologo -v minimal;
        if (\$LASTEXITCODE -ne 0) { exit \$LASTEXITCODE }"
    echo "Trazendo o .exe para $ROOT/publish/ ..."
    mkdir -p "$ROOT/publish"
    for f in AssinaturaEntrega.exe appsettings.json; do
        vm_scp "$SSH_USER@localhost:$GUEST_DIR/publish/$f" "$ROOT/publish/"
    done
    ls -lh "$ROOT/publish/AssinaturaEntrega.exe"
    echo "BUILD_OK"
}

cmd_run() {
    # Sessões SSH no Windows não enxergam a área de trabalho; tools/vm-run.ps1 abre o app
    # por uma tarefa agendada interativa, na sessão gráfica do usuário logado.
    vm_ssh 'powershell -NoProfile -Command -' < "$ROOT/tools/vm-run.ps1"
}

cmd_webcam() {
    echo "Dispositivos USB do host (procure a webcam):"
    lsusb | grep -ivE 'root hub|rate matching hub' || true
    echo
    echo "Dispositivos de vídeo reconhecidos pelo Linux:"
    ls -1 /dev/video* 2>/dev/null || echo "  (nenhum — conecte a webcam)"
    cat <<'EOF'

Como passar a webcam para a VM:

  OPÇÃO A — a quente, pelo visualizador (sem reiniciar a VM):
    na janela do spicy: menu Input → "Select USB Devices for redirection…"
    e marque a webcam. O Windows instala o driver sozinho (~10 s).

  OPÇÃO B — permanente (toda vez que a VM iniciar):
    1) anote o ID "VENDOR:PRODUCT" da webcam acima (ex.: 046d:0825)
    2) edite ~/VMs/windows-11-Brazilian-Portuguese.conf e descomente/ajuste:
         usb_devices=("046d:0825")
    3) tools/vm.sh stop && tools/vm.sh start
       (se o quickemu reclamar de permissão, ele imprime o comando
        'sudo chown' ou a regra udev a criar — basta seguir.)

  Depois, no app dentro da VM: Arquivo → Editar configurações, confira
  "Camera": { "DeviceIndex": 0 } (troque para 1, 2… se abrir a câmera errada).
EOF
}

case "${1:-}" in
    start)  cmd_start ;;
    stop)   cmd_stop ;;
    ssh)    shift; cmd_ssh "$@" ;;
    build)  cmd_build ;;
    run)    cmd_run ;;
    webcam) cmd_webcam ;;
    *) sed -n '2,12p' "$0"; exit 1 ;;
esac
