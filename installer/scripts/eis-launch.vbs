' ============================================================
'  Employee Information System - launcher
'
'  Starts the bundled services silently, then opens the system in
'  its OWN application window - no address bar, no tabs, no
'  bookmarks bar - so it looks and behaves like a desktop program.
'
'  Nothing here may show a console window: WScript.Shell.Exec always
'  creates one, so the readiness check uses an HTTP request instead.
' ============================================================
Option Explicit

Const PORT      = 8080
Const WAIT_SECS = 90          ' a cold start also initialises the database

Dim shell, fso, appDir, i, ok, browser, profileDir
Set shell = CreateObject("WScript.Shell")
Set fso   = CreateObject("Scripting.FileSystemObject")

appDir = fso.GetParentFolderName(WScript.ScriptFullName)

' The browser profile lives under the user's own AppData. The install folder
' is usually inside Program Files, where a standard user cannot write - and a
' profile the browser cannot create makes the application window fail to open.
profileDir = shell.ExpandEnvironmentStrings("%LOCALAPPDATA%") & _
             "\EmployeeInformationSystem\appwindow"

' ---- 1. Make sure the services are up ----------------------
' Always run the service starter, even when the web port already answers.
' It checks the database and the web server separately and starts only what
' is missing - if Apache were up but the database were not, skipping this
' would leave the system showing "Database connection failed".
'
' Window style 0 keeps the batch and everything it spawns hidden.
shell.Run """" & appDir & "\eis-service.bat"" start", 0, False

ok = False
For i = 1 To WAIT_SECS * 2                ' poll twice a second
    If ServerResponds(PORT) Then
        ok = True
        Exit For
    End If
    WScript.Sleep 500
Next

If Not ok Then
    MsgBox "The Employee Information System could not start." & vbCrLf & vbCrLf & _
           "Another program may be using port " & PORT & ", or the services were " & _
           "blocked by Windows Firewall." & vbCrLf & vbCrLf & _
           "Try restarting your computer, then open the shortcut again. If the " & _
           "problem continues, check install.log in:" & vbCrLf & appDir, _
           vbExclamation, "Employee Information System"
    WScript.Quit 1
End If

' ---- 2. Open it as an application window --------------------
Dim launched
launched = False
browser  = FindAppWindowBrowser()

If browser <> "" Then
    EnsureFolder profileDir

    ' --app= gives a standalone window with no address bar, tabs or bookmarks.
    ' The private profile keeps the system out of the user's normal browsing
    ' session and gives it its own taskbar entry.
    '
    ' Every value containing a comma MUST be quoted: Shell.Run treats a bare
    ' comma as an argument separator, which silently mangles the command line
    ' and makes the browser start and exit again without showing a window.
    On Error Resume Next
    shell.Run """" & browser & """" & _
              " --app=""http://localhost:" & PORT & "/""" & _
              " --user-data-dir=""" & profileDir & """" & _
              " --window-size=""1360,880""" & _
              " --no-first-run --no-default-browser-check" & _
              " --disable-features=""Translate,AutofillServerCommunication""", 1, False
    If Err.Number = 0 Then launched = True
    Err.Clear
    On Error GoTo 0
End If

If Not launched Then
    ' No Chromium browser, or it refused to start - use the default browser.
    shell.Run "http://localhost:" & PORT & "/", 1, False
End If

' ------------------------------------------------------------
' True once the web server actually answers. Uses an HTTP request rather
' than netstat, because Shell.Exec would flash a console window.
Function ServerResponds(port)
    Dim http
    ServerResponds = False
    On Error Resume Next
    Set http = CreateObject("MSXML2.ServerXMLHTTP.6.0")
    If Err.Number <> 0 Then
        Err.Clear
        Set http = CreateObject("MSXML2.XMLHTTP")
    End If
    If Err.Number <> 0 Then Exit Function

    http.setTimeouts 1000, 1000, 2000, 3000
    http.open "GET", "http://localhost:" & port & "/", False
    http.send
    If Err.Number = 0 Then
        ' Any HTTP status means the server is listening and serving
        If http.status > 0 Then ServerResponds = True
    End If
    Err.Clear
    On Error GoTo 0
End Function

' First Edge or Chrome found on this computer, or "" when none.
Function FindAppWindowBrowser()
    Dim candidates, p, i2
    candidates = Array( _
        shell.ExpandEnvironmentStrings("%ProgramFiles(x86)%\Microsoft\Edge\Application\msedge.exe"), _
        shell.ExpandEnvironmentStrings("%ProgramFiles%\Microsoft\Edge\Application\msedge.exe"), _
        shell.ExpandEnvironmentStrings("%ProgramFiles%\Google\Chrome\Application\chrome.exe"), _
        shell.ExpandEnvironmentStrings("%ProgramFiles(x86)%\Google\Chrome\Application\chrome.exe"), _
        shell.ExpandEnvironmentStrings("%LocalAppData%\Google\Chrome\Application\chrome.exe") )

    FindAppWindowBrowser = ""
    For i2 = 0 To UBound(candidates)
        p = candidates(i2)
        If InStr(p, "%") = 0 Then
            If fso.FileExists(p) Then
                FindAppWindowBrowser = p
                Exit Function
            End If
        End If
    Next
End Function

' Create a folder and any missing parent folders, ignoring failures.
Sub EnsureFolder(path)
    Dim parent
    On Error Resume Next
    parent = fso.GetParentFolderName(path)
    If parent <> "" And Not fso.FolderExists(parent) Then EnsureFolder parent
    If Not fso.FolderExists(path) Then fso.CreateFolder path
    Err.Clear
    On Error GoTo 0
End Sub
