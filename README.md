# Captura de Assinatura de Entrega

Aplicativo **desktop para Windows 11** que usa a **webcam** para capturar o canhoto/assinatura de entrega, **detecta automaticamente a região da assinatura** (recorta do fundo), **arquiva localmente** de forma organizada e **envia para um servidor via API REST**.

Feito em **C# / .NET 8 (WPF)** com **OpenCV** (OpenCvSharp). Acompanha:

- um **instalador Windows** (`Setup.exe`, Inno Setup) para distribuir aos clientes — pasta [`installer/`](installer/);
- um **servidor de armazenamento** (PHP 8 + SQLite, em Docker) com **painel web** e **tokens por cliente** — pasta [`servidor/`](servidor/).

```
 PC do cliente (Windows)                     Seu servidor (Linux + Docker)
 ┌──────────────────────────┐   POST /api/assinaturas   ┌─────────────────────────────┐
 │ AssinaturaEntrega.exe    │ ───── Bearer <token> ───▶ │ servidor/  (porta 8080)      │
 │ webcam → recorte → envio │                           │  • API (recebe e guarda)     │
 │ fila local se sem rede   │ ◀──── 201 {ok:true} ───── │  • painel web (busca/baixa)  │
 └──────────────────────────┘                           │  • clientes & tokens         │
                                                        └─────────────────────────────┘
```

---

## Instalação rápida (PC do cliente)

Abra o **PowerShell** no Windows 11 e cole:

```powershell
irm https://raw.githubusercontent.com/jefersonrte/AssinaturaEntrega/main/instalar.ps1 | iex
```

O script baixa o Setup mais recente dos [Releases](https://github.com/jefersonrte/AssinaturaEntrega/releases), pergunta
a URL do servidor / token / identificação do PC (ou usa `$env:AE_URL`, `$env:AE_TOKEN`, `$env:AE_DEVICE` se definidos antes)
e instala. Rodar de novo **atualiza** mantendo a configuração. Para instalação totalmente silenciosa pela TI:

```powershell
$env:AE_URL='http://servidor:8080'; $env:AE_TOKEN='token-do-cliente'; $env:AE_DEVICE='BALCAO-01'
& ([scriptblock]::Create((irm https://raw.githubusercontent.com/jefersonrte/AssinaturaEntrega/main/instalar.ps1))) -Silencioso
```

---

## O que o app faz

1. Mostra a **webcam ao vivo**.
2. Você clica em **Capturar** (ou tecla **Espaço**) → congela o frame.
3. Ele **detecta e recorta a assinatura** automaticamente (tinta escura sobre papel claro).
4. Você preenche **nº do pedido**, **destinatário** e **observações**.
5. **Salvar e enviar**: grava os arquivos localmente e envia para a sua API.
   - Se o envio falhar (sem internet), fica **pendente** e pode ser **reenviado** depois (menu *Envio → Reenviar pendentes*).

### Arquivamento local
```
Documentos\AssinaturasEntrega\2026\10\2026-10-15\PEDIDO123_143000_a1b2c3\
    original.png      (frame completo da câmera)
    signature.png     (recorte da assinatura)
    metadata.json     (pedido, destinatário, data, status de envio)
```

---

## Como gerar o .exe (na máquina Windows 11)

### Pré-requisitos (só para compilar)
- **.NET 8 SDK** → https://dotnet.microsoft.com/download/dotnet/8.0

### Build
Abra o **PowerShell** na pasta do projeto e rode:

```powershell
.\build.ps1
```

Isso gera um **.exe único** em `publish\AssinaturaEntrega.exe` que **roda em qualquer Windows 11 sem instalar nada**.

> Alternativa mais leve (exige o *.NET 8 Desktop Runtime* no PC de destino):
> ```powershell
> .\build.ps1 -FrameworkDependent
> ```

Ou direto pelo `dotnet`:
```powershell
dotnet publish src\AssinaturaEntrega\AssinaturaEntrega.csproj -c Release -r win-x64 --self-contained true -p:PublishSingleFile=true -p:IncludeNativeLibrariesForSelfExtract=true -o publish
```

### Rodar em modo de desenvolvimento
```powershell
dotnet run --project src\AssinaturaEntrega\AssinaturaEntrega.csproj
```

---

## Build automático na nuvem (GitHub Actions) — sem instalar nada

O projeto já traz [`.github/workflows/build.yml`](.github/workflows/build.yml), que compila o `.exe` no Windows **nos servidores do GitHub**. Você só baixa o arquivo pronto.

1. Crie um repositório no GitHub e suba este projeto:
   ```bash
   git init
   git add .
   git commit -m "App de captura de assinatura de entrega"
   git branch -M main
   git remote add origin https://github.com/SEU_USUARIO/SEU_REPO.git
   git push -u origin main
   ```
2. Vá na aba **Actions** do repositório. O build roda automaticamente a cada `push`.
3. Abra a execução concluída e baixe o artefato **`AssinaturaEntrega-win-x64`** (contém o `.exe` + `appsettings.json`).

**Gerar uma versão (Release) com download fixo:**
```bash
git tag v1.0.0
git push origin v1.0.0
```
O workflow publica automaticamente um **Release** com o `AssinaturaEntrega-win-x64.zip` anexado.

> Também dá para rodar manualmente em **Actions → Build EXE → Run workflow**.

---

## Ambiente de teste: VM Windows 11 no Linux (quickemu/KVM)

Como o app é WPF (só Windows) e o desenvolvimento é feito em Ubuntu, existe uma VM
Windows 11 pt-BR em `~/VMs/windows-11-Brazilian-Portuguese.conf` (16 threads, 10 GB, SPICE),
com .NET 8 SDK e OpenSSH Server já instalados. O script [`tools/vm.sh`](tools/vm.sh) concentra tudo:

```bash
tools/vm.sh start    # liga a VM e abre o visualizador (fechar o visualizador NÃO desliga a VM)
tools/vm.sh build    # envia os fontes, compila na VM e traz publish/AssinaturaEntrega.exe
tools/vm.sh run      # abre o app compilado dentro da VM
tools/vm.sh ssh      # PowerShell na VM (usuário Quickemu, chave ~/.ssh/id_ed25519_vmwin, porta 22220)
tools/vm.sh webcam   # lista webcams do host e explica como passá-las para a VM
tools/vm.sh stop     # desliga o Windows de forma limpa
```

### Webcam na VM
- **A quente** (sem reiniciar): na janela do `spicy`, menu *Input → Select USB Devices for redirection…* e marque a webcam.
- **Permanente**: pegue o ID `VENDOR:PRODUCT` com `lsusb`, coloque `usb_devices=("vvvv:pppp")` no `.conf` da VM e reinicie-a (`tools/vm.sh stop && tools/vm.sh start`).
- Se o app abrir a câmera errada, ajuste `Camera.DeviceIndex` no `appsettings.json`.

> Detalhes de como a VM foi montada (ISO oficial da Microsoft, correção do `autounattend.xml`
> do quickemu 4.9.7 — issue quickemu #1958 —, OpenSSH via MSI) ficaram anotados para
> reprodução; o projeto em si não depende da VM: o `.exe` também pode ser gerado pelo
> GitHub Actions ou em qualquer Windows com o .NET 8 SDK.

---

## Configuração (`appsettings.json`)

Fica **ao lado do `.exe`**. Edite pelo menu *Arquivo → Editar configurações* ou no bloco de notas.

```jsonc
{
  "Camera":  { "DeviceIndex": 0, "Width": 1280, "Height": 720 },

  "SignatureDetection": {
    "Enabled": true,
    "PaddingPercent": 6,       // margem ao redor do recorte
    "MinAreaPercent": 0.5,     // ignora manchas minúsculas
    "ThresholdBlockSize": 35,  // sensibilidade (ímpar)
    "ThresholdC": 15
  },

  "Archive": {
    "RootPath": "%USERPROFILE%\\Documents\\AssinaturasEntrega",
    "SaveOriginal": true,
    "SaveSignatureCrop": true
  },

  "Api": {
    "Enabled": true,
    "BaseUrl": "https://seu-sistema.com.br",   // <-- URL do seu sistema
    "UploadPath": "/api/assinaturas",
    "AuthToken": "",                            // opcional (vira Bearer token)
    "TimeoutSeconds": 30,
    "SendOriginal": false
  },

  "Device": { "DeviceId": "ENTREGA-01" }        // identifica o posto/entregador
}
```

- **Câmera errada?** Troque `DeviceIndex` (0, 1, 2...).
- **Detecção ruim?** Ajuste `ThresholdC` (↑ pega menos ruído) e `ThresholdBlockSize`.

---

## Instalador Windows (`installer/`)

O [`installer/AssinaturaEntrega.iss`](installer/AssinaturaEntrega.iss) gera um `Setup.exe` em português que:

- instala em `Arquivos de Programas\AssinaturaEntrega`, cria atalhos e desinstalador;
- **pergunta a URL do servidor, o token do cliente e a identificação do computador** e grava
  `C:\ProgramData\AssinaturaEntrega\appsettings.json` (editável por qualquer usuário);
- em atualização, mantém a configuração existente;
- aceita instalação silenciosa: `AssinaturaEntrega-Setup-1.0.0.exe /VERYSILENT /URL=http://servidor:8080 /TOKEN=xxxx /DEVICE=BALCAO-01`.

Gerar: com o `.exe` publicado e o [Inno Setup 6](https://jrsoftware.org/isinfo.php) instalado,
`ISCC.exe installer\AssinaturaEntrega.iss` → `installer\Output\AssinaturaEntrega-Setup-<versão>.exe`.
Na VM de desenvolvimento: `tools/vm.sh build && tools/vm.sh installer`. O GitHub Actions também gera o instalador.

> **SmartScreen:** sem assinatura de código, o Windows mostra "Aplicativo não reconhecido" na primeira execução
> do Setup (o cliente clica em *Mais informações → Executar assim mesmo*). Para distribuição comercial vale
> comprar um certificado de **code signing** (OV/EV) e assinar `Setup.exe` e `AssinaturaEntrega.exe`.

---

## Servidor de armazenamento (`servidor/`)

PHP 8.3 + Apache + SQLite, sem dependências externas, pronto para Docker. Guarda as imagens em disco e os
metadados em SQLite; tem painel web com login e **um token por cliente** (empresa que comprou o app).

```bash
cd servidor
cp .env.example .env          # defina ADMIN_USUARIO / ADMIN_SENHA
docker compose up -d --build  # sobe em http://IP-DO-SERVIDOR:8080
```

- **Painel:** `http://IP:8080/` → login → *Assinaturas* (busca por pedido, destinatário, data, dispositivo, cliente;
  miniaturas; download) e *Clientes* (criar cliente = gerar token; desativar; trocar token).
- **CLI:** `docker compose exec -u www-data assinaturas php bin/cliente.php criar "Nome"`.
- **Dados:** tudo fica em `servidor/data/` (`banco.sqlite` + `uploads/<cliente>/<ano>/<mês>/`). **Faça backup dessa pasta.**
- **Firewall:** libere a porta 8080 (ou a que definir em `.env`) para os PCs dos clientes alcançarem o servidor.
- **Internet / HTTPS:** para clientes fora da sua rede, coloque um proxy com TLS na frente (ex.: Caddy ou Nginx
  com Let's Encrypt) apontando para a porta 8080, e use `https://...` na instalação do app.

### Modelo comercial sugerido
1. Você hospeda **um** servidor (este Linux ou uma VPS) para todos os clientes.
2. Para cada cliente: painel → *Clientes* → **Criar e gerar token**.
3. Entrega ao cliente o `Setup.exe` + URL do servidor + token (ou um `.bat` com a instalação silenciosa).
4. Fim de contrato: *Desativar* o cliente (os envios passam a receber HTTP 403 e ficam pendentes no app).
5. O cliente consulta as assinaturas pelo painel (crie um usuário por cliente quando precisar — hoje o painel tem
   um único administrador; esse é o próximo passo natural) ou integra pela API `GET /api/assinaturas`.

---

## Contrato da API REST

**POST** `{BaseUrl}{UploadPath}` — `multipart/form-data`

| Campo           | Tipo    | Descrição                          |
|-----------------|---------|------------------------------------|
| `id`            | texto   | ID único da captura                |
| `orderNumber`   | texto   | Número do pedido                   |
| `recipientName` | texto   | Quem recebeu                       |
| `notes`         | texto   | Observações                        |
| `deviceId`      | texto   | Identificação do dispositivo       |
| `capturedAtUtc` | texto   | Data/hora ISO-8601 (UTC)           |
| `signature`     | arquivo | PNG do recorte da assinatura       |
| `original`      | arquivo | PNG do frame completo (se ligado)  |

Header: `Authorization: Bearer <token do cliente>`
Sucesso: qualquer **HTTP 2xx** (`201` criado; `200 {duplicado:true}` se o mesmo `id` for reenviado).
Erros: `401` token inválido, `403` cliente desativado, `400` campos/arquivo faltando.

**GET** `{BaseUrl}/api/ping` — testa servidor + token (menu *Envio → Testar conexão* no app).
**GET** `{BaseUrl}/api/assinaturas?pedido=&de=&ate=&dispositivo=&limite=` — lista em JSON as assinaturas do cliente do token.

A implementação de referência é o [`servidor/`](servidor/) deste repositório.

---

## Estrutura do projeto

```
AssinaturaEntrega/
├─ AssinaturaEntrega.sln
├─ build.ps1                       # gera o .exe
├─ README.md
├─ installer/AssinaturaEntrega.iss # instalador Windows (Inno Setup)
├─ servidor/                       # servidor de armazenamento (PHP + SQLite + Docker)
│  ├─ docker-compose.yml, Dockerfile, .env.example
│  ├─ public/  (painel: index, assinatura, clientes, login; API: api/assinaturas, api/ping)
│  ├─ src/bootstrap.php            # banco, auth por token, sessão do painel
│  └─ bin/cliente.php              # CLI de clientes/tokens
├─ tools/vm.sh                     # VM Windows de desenvolvimento (build/run/installer)
└─ src/AssinaturaEntrega/
   ├─ AssinaturaEntrega.csproj
   ├─ appsettings.json
   ├─ App.xaml(.cs)
   ├─ MainWindow.xaml(.cs)         # interface
   ├─ Models/                      # AppSettings, DeliveryRecord
   └─ Services/
      ├─ CameraService.cs          # webcam (OpenCV)
      ├─ SignatureDetector.cs      # detecção/recorte da assinatura
      ├─ ArchiveService.cs         # arquivamento local + fila de pendentes
      ├─ ApiExportService.cs       # envio REST
      └─ ConfigService.cs          # appsettings.json
```

---

## Observações / próximos passos possíveis
- **OCR** (ler automaticamente nº do pedido/nome do canhoto) com Tesseract — não incluído nesta versão.
- **Seleção manual da área** da assinatura (desenhar retângulo) como alternativa à detecção automática.
- **Assinatura digital em tela** (touch/caneta) em vez de foto, se o fluxo permitir.
- Ícone do app e assinatura de código (code signing) para evitar aviso do SmartScreen.
