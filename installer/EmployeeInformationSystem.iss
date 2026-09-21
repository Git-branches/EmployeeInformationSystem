; ============================================================================
;  Employee Information System - Inno Setup script
;
;  Builds: EmployeeInformationSystem_Setup.exe
;
;  Expects the staging folder produced by build.ps1:
;     installer\build\www\      - the PHP application
;     installer\build\stack\    - apache, php, mariadb (portable)
;     installer\build\scripts\  - eis-service.bat, eis-launch.vbs, ...
;     installer\templates\      - httpd.conf, php.ini, my.ini (with {#APPDIR#})
;
;  Compile with:  ISCC.exe EmployeeInformationSystem.iss
; ============================================================================

#define AppName        "Employee Information System"
#define AppShortName   "EIS"
#define AppVersion     "1.2.0"
#define AppPublisher   AppName
#define AppYear        "2026"
#define AppURL         "http://localhost:8080/"
#define AppExe         "eis-launch.vbs"
#define BuildDir       "build"

[Setup]
AppId={{8F3C1E4A-9B27-4D65-A1F8-2E7C5B0D9A11}
AppName={#AppName}
AppVersion={#AppVersion}
AppVerName={#AppName} {#AppVersion}
AppPublisher={#AppPublisher}
AppComments=Centralized employee records, requirements monitoring and reporting
AppCopyright=Copyright (C) {#AppYear} {#AppPublisher}
; Shown under right-click > Properties > Details on the setup file itself
VersionInfoVersion={#AppVersion}
VersionInfoCompany={#AppPublisher}
VersionInfoDescription={#AppName} Setup
VersionInfoCopyright=Copyright (C) {#AppYear} {#AppPublisher}
VersionInfoProductName={#AppName}
VersionInfoProductVersion={#AppVersion}
DefaultDirName={autopf}\{#AppName}
DefaultGroupName={#AppName}
DisableProgramGroupPage=yes
DisableDirPage=no
AllowNoIcons=yes
OutputDir=dist
OutputBaseFilename=EmployeeInformationSystem_Setup
SetupIconFile=eis.ico
UninstallDisplayIcon={app}\eis.ico
UninstallDisplayName={#AppName}
Compression=lzma2/max
SolidCompression=yes
WizardStyle=modern
ArchitecturesInstallIn64BitMode=x64compatible
ArchitecturesAllowed=x64compatible
PrivilegesRequired=admin
MinVersion=10.0
DirExistsWarning=no
CloseApplications=no
LicenseFile=LICENSE.txt
InfoBeforeFile=READMEFIRST.txt

[Languages]
Name: "english"; MessagesFile: "compiler:Default.isl"

[Tasks]
Name: "desktopicon";  Description: "Create a &desktop shortcut";                    GroupDescription: "Shortcuts:"; Flags: checkedonce
Name: "startmenu";    Description: "Create a &Start Menu shortcut";                 GroupDescription: "Shortcuts:"; Flags: checkedonce
Name: "startupicon";  Description: "Start the system automatically when Windows starts"; GroupDescription: "Startup:"; Flags: unchecked
Name: "firewall";     Description: "Allow the web server through Windows Firewall (local network access)"; GroupDescription: "Network:"; Flags: unchecked

[Files]
; ---- Application (PHP source, assets, vendor libraries) --------------------
Source: "{#BuildDir}\www\*"; DestDir: "{app}\www"; Flags: ignoreversion recursesubdirs createallsubdirs

; ---- Portable stack: Apache, PHP, MariaDB ---------------------------------
Source: "{#BuildDir}\stack\*"; DestDir: "{app}\stack"; Flags: ignoreversion recursesubdirs createallsubdirs

; ---- Control scripts ------------------------------------------------------
Source: "{#BuildDir}\scripts\*"; DestDir: "{app}"; Flags: ignoreversion

; ---- Configuration templates (patched with the real path after install) ---
; Each config is patched with the real install path immediately after it is
; copied (AfterInstall), because the database setup in [Run] executes before
; the ssPostInstall step and needs the paths to be correct already.
Source: "templates\httpd.conf"; DestDir: "{app}\stack\apache\conf"; Flags: ignoreversion; AfterInstall: PatchApacheConf
; php.ini.generated is produced by build.ps1 and carries the extension list
; that matches the PHP build actually bundled in stack\php.
Source: "templates\php.ini.generated"; DestDir: "{app}\stack\php"; DestName: "php.ini"; Flags: ignoreversion; AfterInstall: PatchPhpIni
Source: "templates\my.ini";     DestDir: "{app}\stack\mariadb";     Flags: ignoreversion; AfterInstall: PatchMyIni

; ---- Branding and docs ----------------------------------------------------
Source: "eis.ico";          DestDir: "{app}"; Flags: ignoreversion
Source: "READMEFIRST.txt";  DestDir: "{app}"; Flags: ignoreversion isreadme

[Dirs]
Name: "{app}\stack\tmp";                    Permissions: users-modify
Name: "{app}\stack\apache\logs";            Permissions: users-modify
Name: "{app}\stack\php\logs";               Permissions: users-modify
Name: "{app}\stack\mariadb\data";           Permissions: users-modify
Name: "{app}\www\uploads";                  Permissions: users-modify
Name: "{app}\www\uploads\photos";           Permissions: users-modify
Name: "{app}\www\uploads\requirements";     Permissions: users-modify
Name: "{app}\www\uploads\imports";          Permissions: users-modify
Name: "{app}\www\backups";                  Permissions: users-modify
Name: "{app}\www\config";                   Permissions: users-modify

[Icons]
; Main shortcut. wscript.exe is named explicitly (rather than letting Windows
; pick the .vbs handler) so the launcher always runs windowless, whatever the
; user's file associations happen to be.
Name: "{group}\{#AppName}";        Filename: "{sys}\wscript.exe"; Parameters: """{app}\{#AppExe}"""; WorkingDir: "{app}"; IconFilename: "{app}\eis.ico"; Comment: "Open the {#AppName}"; Tasks: startmenu
Name: "{group}\Stop {#AppShortName} Services"; Filename: "{app}\eis-stop.bat"; WorkingDir: "{app}"; IconFilename: "{app}\eis.ico"; Comment: "Shut down Apache and the database"; Tasks: startmenu
Name: "{group}\Uninstall {#AppName}"; Filename: "{uninstallexe}"; Tasks: startmenu
Name: "{autodesktop}\{#AppName}";  Filename: "{sys}\wscript.exe"; Parameters: """{app}\{#AppExe}"""; WorkingDir: "{app}"; IconFilename: "{app}\eis.ico"; Comment: "Open the {#AppName}"; Tasks: desktopicon
; commonstartup (not userstartup) - the installer runs elevated, and the
; system should come up for whoever logs in on this computer.
Name: "{commonstartup}\{#AppName}"; Filename: "{sys}\wscript.exe"; Parameters: """{app}\{#AppExe}"""; WorkingDir: "{app}"; IconFilename: "{app}\eis.ico"; Tasks: startupicon

[Run]
; 1. Prepare and import the database (visible so the user sees progress)
Filename: "{cmd}"; Parameters: "/C ""{app}\eis-dbsetup.bat"""; WorkingDir: "{app}"; StatusMsg: "Setting up the database (this can take a minute)..."; Flags: waituntilterminated

; 2. Optional firewall rules
Filename: "{sys}\netsh.exe"; Parameters: "advfirewall firewall add rule name=""{#AppName} (HTTP 8080)"" dir=in action=allow protocol=TCP localport=8080"; Flags: runhidden waituntilterminated; Tasks: firewall

; 3. Launch the system when the wizard finishes (windowless, via wscript)
Filename: "{sys}\wscript.exe"; Parameters: """{app}\{#AppExe}"""; Description: "Launch the {#AppName} now"; WorkingDir: "{app}"; Flags: postinstall nowait skipifsilent

[UninstallRun]
; Stop Apache and MariaDB before files are removed
Filename: "{app}\eis-service.bat"; Parameters: "stop"; WorkingDir: "{app}"; Flags: runhidden waituntilterminated; RunOnceId: "StopEIS"
Filename: "{sys}\netsh.exe"; Parameters: "advfirewall firewall delete rule name=""{#AppName} (HTTP 8080)"""; Flags: runhidden waituntilterminated; RunOnceId: "DelFwRule"

[UninstallDelete]
Type: filesandordirs; Name: "{app}\stack\tmp"
Type: filesandordirs; Name: "{app}\stack\apache\logs"
Type: filesandordirs; Name: "{app}\stack\php\logs"
Type: files;          Name: "{app}\install.log"

[Code]
var
  DataPage: TInputOptionWizardPage;
  KeepDataOnUninstall: Boolean;

{ ---------------------------------------------------------------- helpers }

{ Replace the @@APPDIR@@ placeholder inside a config file with the real path
  (forward slashes - Apache, PHP and MariaDB all accept them on Windows). }
procedure PatchConfig(const FileName: string);
var
  Raw: AnsiString;
  Content, AppPath: string;
begin
  if not LoadStringFromFile(FileName, Raw) then
    Exit;
  Content := String(Raw);
  AppPath := ExpandConstant('{app}');
  StringChangeEx(AppPath, '\', '/', True);
  StringChangeEx(Content, '@@APPDIR@@', AppPath, True);
  SaveStringToFile(FileName, AnsiString(Content), False);
end;

{ AfterInstall hooks — run the moment each config file lands on disk. }
procedure PatchApacheConf;
begin
  PatchConfig(ExpandConstant('{app}\stack\apache\conf\httpd.conf'));
end;

procedure PatchPhpIni;
begin
  PatchConfig(ExpandConstant('{app}\stack\php\php.ini'));
end;

procedure PatchMyIni;
begin
  PatchConfig(ExpandConstant('{app}\stack\mariadb\my.ini'));
end;

{ Write config/local.php so the application talks to the bundled MariaDB. }
procedure WriteLocalDbConfig;
var
  S: string;
begin
  S := '<?php' + #13#10 +
       '// Written by the installer - points the system at the bundled database.' + #13#10 +
       'return [' + #13#10 +
       '    ''host'' => ''127.0.0.1'',' + #13#10 +
       '    ''port'' => ''3307'',' + #13#10 +
       '    ''name'' => ''employee_information_system'',' + #13#10 +
       '    ''user'' => ''root'',' + #13#10 +
       '    ''pass'' => '''',' + #13#10 +
       '];' + #13#10;
  SaveStringToFile(ExpandConstant('{app}\www\config\local.php'), AnsiString(S), False);
end;

{ True when something already listens on the given TCP port. }
function PortInUse(Port: string): Boolean;
var
  ResultCode: Integer;
  TempFile, Content: string;
  Output: AnsiString;
begin
  Result := False;
  TempFile := ExpandConstant('{tmp}\portcheck.txt');
  if Exec(ExpandConstant('{cmd}'), '/C netstat -an | findstr LISTENING | findstr :' + Port + ' > "' + TempFile + '"',
          '', SW_HIDE, ewWaitUntilTerminated, ResultCode) then
  begin
    if LoadStringFromFile(TempFile, Output) then
    begin
      Content := String(Output);
      Result := Pos(':' + Port, Content) > 0;
    end;
    DeleteFile(TempFile);
  end;
end;

{ ------------------------------------------------------------ wizard flow }

{ Product name and version on the finished page of the wizard. }
procedure CurPageChanged(CurPageID: Integer);
begin
  if CurPageID = wpFinished then
    WizardForm.FinishedLabel.Caption :=
      WizardForm.FinishedLabel.Caption + #13#10#13#10 +
      '{#AppName} {#AppVersion}';
end;

procedure InitializeWizard;
begin
  { A quiet product line at the bottom of every wizard page }
  WizardForm.BeveledLabel.Caption := '  {#AppName} {#AppVersion}  ';

  DataPage := CreateInputOptionPage(wpSelectTasks,
    'Existing data', 'What should happen to employee records if you uninstall?',
    'Choose how the uninstaller treats the database and uploaded files.',
    True, False);
  DataPage.Add('Keep employee data (recommended) - the database and uploads stay on disk');
  DataPage.Add('Remove everything - delete all employee records, documents and backups');
  DataPage.SelectedValueIndex := 0;
end;

function InitializeSetup: Boolean;
begin
  Result := True;
  if PortInUse('8080') then
  begin
    if MsgBox('Port 8080 is already in use on this computer (another web server such as ' +
              'Laragon or XAMPP may be running).' + #13#10#13#10 +
              'The Employee Information System needs this port. Stop the other program ' +
              'first, or continue and stop it before launching the system.' + #13#10#13#10 +
              'Continue with the installation?', mbConfirmation, MB_YESNO) = IDNO then
      Result := False;
  end;
end;

procedure CurStepChanged(CurStep: TSetupStep);
begin
  { The three service configs are patched by their AfterInstall hooks, which
    fire before the database setup in [Run]. Only the application's own
    database pointer is left to write here. }
  if CurStep = ssPostInstall then
    WriteLocalDbConfig;
end;

{ ------------------------------------------------------------- uninstall }

function InitializeUninstall: Boolean;
begin
  Result := True;
  KeepDataOnUninstall := True;
  if MsgBox('Do you want to KEEP the employee records and uploaded documents?' + #13#10#13#10 +
            'Yes  - the database and uploads folder are left on disk' + #13#10 +
            'No   - everything is permanently deleted',
            mbConfirmation, MB_YESNO) = IDNO then
    KeepDataOnUninstall := False;
end;

procedure CurUninstallStepChanged(CurUninstallStep: TUninstallStep);
begin
  if CurUninstallStep = usPostUninstall then
  begin
    if not KeepDataOnUninstall then
    begin
      DelTree(ExpandConstant('{app}\stack\mariadb\data'), True, True, True);
      DelTree(ExpandConstant('{app}\www\uploads'), True, True, True);
      DelTree(ExpandConstant('{app}\www\backups'), True, True, True);
      DeleteFile(ExpandConstant('{app}\www\config\local.php'));
      DeleteFile(ExpandConstant('{app}\www\config\google_token.json'));
      DeleteFile(ExpandConstant('{app}\www\config\google_oauth_client.json'));
    end;
  end;
end;
