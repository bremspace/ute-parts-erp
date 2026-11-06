; =====================================================================
; Bremspace ERP - Inno Setup 6 Script
; Native Desktop Single-Click Installer (.exe)
; =====================================================================

#define AppName "Bremspace ERP"
#ifndef AppVersion
  #define AppVersion "1.0.0"
#endif
#define AppPublisher "Bremspace"
#define AppURL "https://bremspace.id"
#define AppExeName "launcher.exe"

[Setup]
AppId={{B8A2B799-5C3A-4E90-B87E-125488A01741}
AppName={#AppName}
AppVersion={#AppVersion}
AppPublisher={#AppPublisher}
AppPublisherURL={#AppURL}
AppSupportURL={#AppURL}
AppUpdatesURL={#AppURL}

; Non-admin user level installation (No UAC hassle, writes to %LOCALAPPDATA%\Programs)
DefaultDirName={localappdata}\Programs\BremspaceERP
DefaultGroupName={#AppName}
DisableProgramGroupPage=yes
PrivilegesRequired=lowest

OutputDir=..\..\dist\windows
OutputBaseFilename=BremspaceERP-Setup-v{#AppVersion}
Compression=lzma2/ultra64
SolidCompression=yes
WizardStyle=modern
UninstallDisplayIcon={app}\{#AppExeName}

[Languages]
Name: "indonesian"; MessagesFile: "compiler:Languages\Indonesian.isl"
Name: "english"; MessagesFile: "compiler:Default.isl"

[Tasks]
Name: "desktopicon"; Description: "{cm:CreateDesktopIcon}"; GroupDescription: "{cm:AdditionalIcons}"

[Files]
; Launcher and Process Supervisor
Source: "payload\{#AppExeName}"; DestDir: "{app}"; Flags: ignoreversion

; Laravel Application Code (Production Assets)
Source: "payload\app\*"; DestDir: "{app}\app"; Flags: ignoreversion recursesubdirs createallsubdirs

; PHP 8.4 Runtime Engine
Source: "payload\php\*"; DestDir: "{app}\php"; Flags: ignoreversion recursesubdirs createallsubdirs

; Caddy Web Server
Source: "payload\caddy\*"; DestDir: "{app}\caddy"; Flags: ignoreversion recursesubdirs createallsubdirs

; Embedded MariaDB Portable
Source: "payload\db_engine\*"; DestDir: "{app}\db_engine"; Flags: ignoreversion recursesubdirs createallsubdirs

; Configuration Templates
Source: "config\*"; DestDir: "{app}\config"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "config\.env.desktop.template"; DestDir: "{app}\config"; Flags: ignoreversion

; WebView2 Bootstrapper (Temporary payload)
Source: "payload\webview\MicrosoftEdgeWebview2Setup.exe"; DestDir: "{tmp}"; Flags: deleteafterinstall

[Dirs]
; State & Database persistence (NEVER deleted on uninstall/update)
Name: "{localappdata}\BremspaceERP\db_data"; Flags: uninsneveruninstall
Name: "{localappdata}\BremspaceERP\storage"; Flags: uninsneveruninstall
Name: "{localappdata}\BremspaceERP\config"; Flags: uninsneveruninstall

[Icons]
Name: "{group}\{#AppName}"; Filename: "{app}\{#AppExeName}"
Name: "{group}\{cm:UninstallProgram,{#AppName}}"; Filename: "{uninstallexe}"
Name: "{autodesktop}\{#AppName}"; Filename: "{app}\{#AppExeName}"; Tasks: desktopicon

[Run]
; Auto-launch after installation
Filename: "{app}\{#AppExeName}"; Description: "{cm:LaunchProgram,{#StringChange(AppName, '&', '&&')}}"; Flags: nowait postinstall skipifsilent

[Code]
// Check if Microsoft Edge WebView2 is already installed
function IsWebView2Installed(): Boolean;
var
  InstalledVersion: String;
begin
  Result := RegQueryStringValue(HKLM, 'SOFTWARE\WOW6432Node\Microsoft\EdgeUpdate\Clients\{F3017226-2347-4E85-B68D-21075F5CD674}', 'pv', InstalledVersion) or
            RegQueryStringValue(HKCU, 'SOFTWARE\Microsoft\EdgeUpdate\Clients\{F3017226-2347-4E85-B68D-21075F5CD674}', 'pv', InstalledVersion);
end;

// Auto-install WebView2 if not detected
procedure CurStepChanged(CurStep: TSetupStep);
var
  ResultCode: Integer;
begin
  if CurStep = ssPostInstall then
  begin
    if not IsWebView2Installed() then
    begin
      WizardForm.StatusLabel.Caption := 'Memasang Microsoft Edge WebView2 Runtime...';
      Exec(ExpandConstant('{tmp}\MicrosoftEdgeWebview2Setup.exe'), '/silent /install', '', SW_HIDE, ewWaitUntilTerminated, ResultCode);
    end;
  end;
end;
