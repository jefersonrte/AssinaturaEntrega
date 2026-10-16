; Instalador do Captura de Assinatura de Entrega (Inno Setup 6)
;
; Compilar (na VM/PC Windows, após gerar publish\AssinaturaEntrega.exe):
;   "C:\Program Files (x86)\Inno Setup 6\ISCC.exe" installer\AssinaturaEntrega.iss
;   → installer\Output\AssinaturaEntrega-Setup-<versão>.exe
;
; Instalação silenciosa (ex.: via script de TI do cliente):
;   AssinaturaEntrega-Setup-1.0.0.exe /VERYSILENT /URL=http://servidor:8080 /TOKEN=xxxx /DEVICE=BALCAO-01

#ifndef AppVersion
  ; versão do .exe (1.0.0.0) sem o último componente → 1.0.0
  #define FullVer GetVersionNumbersString("..\publish\AssinaturaEntrega.exe")
  #define AppVersion Copy(FullVer, 1, RPos(".", FullVer) - 1)
#endif
#define AppName      "Captura de Assinatura de Entrega"
#define AppShortName "AssinaturaEntrega"
#define AppPublisher "TCK Soluções"
#define AppExe       "AssinaturaEntrega.exe"
#define ConfigDir    "{commonappdata}\AssinaturaEntrega"

[Setup]
AppId={{B3D7F0E4-9C2A-4F6B-8A1D-5E7C2A9B4F10}
AppName={#AppName}
AppVersion={#AppVersion}
AppVerName={#AppName} {#AppVersion}
AppPublisher={#AppPublisher}
DefaultDirName={autopf}\{#AppShortName}
DefaultGroupName={#AppName}
DisableProgramGroupPage=yes
OutputDir=Output
OutputBaseFilename={#AppShortName}-Setup-{#AppVersion}
Compression=lzma2/ultra64
SolidCompression=yes
LZMAUseSeparateProcess=yes
ArchitecturesAllowed=x64compatible
ArchitecturesInstallIn64BitMode=x64compatible
PrivilegesRequired=admin
WizardStyle=modern
UninstallDisplayIcon={app}\{#AppExe}
UninstallDisplayName={#AppName}
MinVersion=10.0.17763
CloseApplications=yes
RestartApplications=no

[Languages]
Name: "brazilianportuguese"; MessagesFile: "compiler:Languages\BrazilianPortuguese.isl"

[Tasks]
Name: "desktopicon"; Description: "{cm:CreateDesktopIcon}"; GroupDescription: "{cm:AdditionalIcons}"

[Dirs]
; Configuração editável por qualquer usuário do PC (o app abre no Bloco de Notas).
Name: "{#ConfigDir}"; Permissions: users-modify

[Files]
Source: "..\publish\{#AppExe}"; DestDir: "{app}"; Flags: ignoreversion
; Modelo da configuração, só para referência do usuário.
Source: "..\src\AssinaturaEntrega\appsettings.json"; DestDir: "{app}"; DestName: "appsettings.modelo.json"; Flags: ignoreversion

[Icons]
Name: "{group}\{#AppName}"; Filename: "{app}\{#AppExe}"
Name: "{group}\Editar configurações"; Filename: "notepad.exe"; Parameters: """{#ConfigDir}\appsettings.json"""
Name: "{autodesktop}\{#AppName}"; Filename: "{app}\{#AppExe}"; Tasks: desktopicon

[Run]
Filename: "{app}\{#AppExe}"; Description: "{cm:LaunchProgram,{#StringChange(AppName, '&', '&&')}}"; Flags: nowait postinstall skipifsilent

[UninstallDelete]
; A configuração e as assinaturas arquivadas (Documentos\AssinaturasEntrega) são preservadas de propósito.

[Code]
var
  PaginaServidor: TInputQueryWizardPage;

function ConfigPath: String;
begin
  Result := ExpandConstant('{#ConfigDir}\appsettings.json');
end;

procedure InitializeWizard;
begin
  PaginaServidor := CreateInputQueryPage(wpSelectDir,
    'Configuração do servidor',
    'Para onde as assinaturas capturadas serão enviadas?',
    'Informe os dados fornecidos pelo seu fornecedor. Você poderá alterá-los depois em ' +
    'Arquivo → Editar configurações, dentro do aplicativo.');
  PaginaServidor.Add('URL do servidor (ex.: http://192.168.1.10:8080):', False);
  PaginaServidor.Add('Token do cliente:', False);
  PaginaServidor.Add('Identificação deste computador (ex.: BALCAO-01):', False);

  { Pré-preenche com parâmetros de linha de comando (/URL= /TOKEN= /DEVICE=), se houver. }
  PaginaServidor.Values[0] := ExpandConstant('{param:URL|}');
  PaginaServidor.Values[1] := ExpandConstant('{param:TOKEN|}');
  PaginaServidor.Values[2] := ExpandConstant('{param:DEVICE|}');
  if PaginaServidor.Values[2] = '' then
    PaginaServidor.Values[2] := GetComputerNameString;
end;

{ Em atualização (config já existe) não pergunta de novo. }
function ShouldSkipPage(PageID: Integer): Boolean;
begin
  Result := (PageID = PaginaServidor.ID) and FileExists(ConfigPath);
end;

function NextButtonClick(CurPageID: Integer): Boolean;
begin
  Result := True;
  if CurPageID = PaginaServidor.ID then
  begin
    if Trim(PaginaServidor.Values[0]) = '' then
    begin
      MsgBox('Informe a URL do servidor. Se ainda não tiver, pode deixar em branco e configurar depois ' +
             'em Arquivo → Editar configurações.' + #13#10#13#10 + 'Deseja continuar sem servidor?',
             mbConfirmation, MB_YESNO);
      { O MsgBox acima é só aviso; segue de qualquer forma. }
    end;
  end;
end;

function JsonEscape(const S: String): String;
begin
  Result := S;
  StringChangeEx(Result, '\', '\\', True);
  StringChangeEx(Result, '"', '\"', True);
end;

procedure GravarConfiguracao;
var
  Linhas: TArrayOfString;
  Url, Token, Device: String;
begin
  Url    := Trim(PaginaServidor.Values[0]);
  Token  := Trim(PaginaServidor.Values[1]);
  Device := Trim(PaginaServidor.Values[2]);
  if Device = '' then Device := 'ENTREGA-01';

  SetArrayLength(Linhas, 30);
  Linhas[0]  := '{';
  Linhas[1]  := '  "Camera": { "DeviceIndex": 0, "Width": 1280, "Height": 720 },';
  Linhas[2]  := '  "SignatureDetection": {';
  Linhas[3]  := '    "Enabled": true,';
  Linhas[4]  := '    "PaddingPercent": 6,';
  Linhas[5]  := '    "MinAreaPercent": 0.5,';
  Linhas[6]  := '    "ThresholdBlockSize": 35,';
  Linhas[7]  := '    "ThresholdC": 15';
  Linhas[8]  := '  },';
  Linhas[9]  := '  "Archive": {';
  Linhas[10] := '    "RootPath": "%USERPROFILE%\\Documents\\AssinaturasEntrega",';
  Linhas[11] := '    "SaveOriginal": true,';
  Linhas[12] := '    "SaveSignatureCrop": true';
  Linhas[13] := '  },';
  Linhas[14] := '  "Api": {';
  Linhas[15] := '    "Enabled": true,';
  Linhas[16] := '    "BaseUrl": "' + JsonEscape(Url) + '",';
  Linhas[17] := '    "UploadPath": "/api/assinaturas",';
  Linhas[18] := '    "PingPath": "/api/ping",';
  Linhas[19] := '    "AuthToken": "' + JsonEscape(Token) + '",';
  Linhas[20] := '    "TimeoutSeconds": 30,';
  Linhas[21] := '    "SendOriginal": false';
  Linhas[22] := '  },';
  Linhas[23] := '  "Device": { "DeviceId": "' + JsonEscape(Device) + '" }';
  Linhas[24] := '}';
  SetArrayLength(Linhas, 25);

  ForceDirectories(ExpandConstant('{#ConfigDir}'));
  if not SaveStringsToUTF8File(ConfigPath, Linhas, False) then
    MsgBox('Não foi possível gravar ' + ConfigPath + '. Configure manualmente pelo aplicativo.', mbError, MB_OK);
end;

procedure CurStepChanged(CurStep: TSetupStep);
begin
  if (CurStep = ssPostInstall) and (not FileExists(ConfigPath)) then
    GravarConfiguracao;
end;
