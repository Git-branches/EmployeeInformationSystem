' ============================================================
'  Employee Information System - launcher
'  Starts Apache + MariaDB silently (no console window),
'  waits for the web server, then opens the login page.
'  This is what the Desktop / Start Menu shortcuts run.
' ============================================================
Option Explicit

Dim shell, fso, appDir, i, ok
Set shell = CreateObject("WScript.Shell")
Set fso   = CreateObject("Scripting.FileSystemObject")

appDir = fso.GetParentFolderName(WScript.ScriptFullName)

' Start the services without showing a window (0) and wait for it (True)
shell.Run """" & appDir & "\eis-service.bat"" start", 0, True

' Give Apache a moment, then confirm it answers before opening the browser
ok = False
For i = 1 To 20
    If PortOpen("127.0.0.1", 8080) Then
        ok = True
        Exit For
    End If
    WScript.Sleep 500
Next

If ok Then
    shell.Run "http://localhost:8080/", 1, False
Else
    MsgBox "The Employee Information System could not start." & vbCrLf & vbCrLf & _
           "Another program may be using port 8080, or the services were blocked " & _
           "by Windows Firewall. Try restarting your computer, then open the " & _
           "shortcut again.", vbExclamation, "Employee Information System"
End If

' ------------------------------------------------------------
' True when something is listening on the given host/port.
Function PortOpen(host, port)
    Dim exec, out
    PortOpen = False
    On Error Resume Next
    Set exec = shell.Exec("netstat -an")
    If Err.Number <> 0 Then Exit Function
    out = exec.StdOut.ReadAll()
    If InStr(out, host & ":" & port) > 0 Then PortOpen = True
    On Error GoTo 0
End Function
