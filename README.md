# Captura de Assinatura de Entrega

Aplicativo **desktop para Windows 11** que usa a **webcam** para capturar o canhoto/assinatura de entrega, **detecta automaticamente a região da assinatura** (recorta do fundo), **arquiva localmente** de forma organizada e **envia para um sistema web via API REST**.

Feito em **C# / .NET 8 (WPF)** com **OpenCV** (OpenCvSharp).

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

## Contrato da API REST (o que o seu sistema web recebe)

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

Header opcional: `Authorization: Bearer <AuthToken>`
Sucesso: qualquer **HTTP 2xx**.

Um endpoint de exemplo em PHP está em [`exemplo-servidor/receber.php`](exemplo-servidor/receber.php) — útil para testar antes de integrar ao seu sistema real.

---

## Estrutura do projeto

```
AssinaturaEntrega/
├─ AssinaturaEntrega.sln
├─ build.ps1                       # gera o .exe
├─ README.md
├─ exemplo-servidor/receber.php    # endpoint de teste
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
