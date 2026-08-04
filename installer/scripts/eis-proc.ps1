<#
    eis-proc.ps1 - process helper for eis-service.bat

    Only ever touches processes whose executable lives under -Root, so a
    Laragon / XAMPP stack running on the same computer is never affected.

    Actions:
        count      print how many matching processes are ours (a bare number)
        kill       force-stop only our matching processes
        start-db   launch the bundled database, fully detached
        start-web  launch the bundled web server, fully detached

    Services are started with Start-Process rather than the batch "start"
    command: a process launched from batch inherits the console, which keeps
    cmd.exe alive and makes the shortcut launcher appear to hang.
#>
[CmdletBinding()]
param(
    [Parameter(Mandatory)]
    [ValidateSet('count', 'kill', 'start-db', 'start-web')]
    [string] $Action,

    [Parameter(Mandatory)] [string] $Root,

    [string] $Name
)

$ErrorActionPreference = 'SilentlyContinue'

function Get-OwnProcesses {
    if (-not $Name) { return @() }
    Get-CimInstance Win32_Process -Filter "Name='$Name'" |
        Where-Object {
            $_.ExecutablePath -and
            $_.ExecutablePath.StartsWith($Root, [StringComparison]::OrdinalIgnoreCase)
        }
}

switch ($Action) {

    'count' {
        Write-Output @(Get-OwnProcesses).Count
    }

    'kill' {
        foreach ($p in Get-OwnProcesses) {
            Stop-Process -Id $p.ProcessId -Force -ErrorAction SilentlyContinue
        }
    }

    'start-db' {
        Start-Process -FilePath "$Root\stack\mariadb\bin\mysqld.exe" `
                      -ArgumentList "--defaults-file=`"$Root\stack\mariadb\my.ini`"" `
                      -WindowStyle Hidden
    }

    'start-web' {
        Start-Process -FilePath "$Root\stack\apache\bin\httpd.exe" `
                      -ArgumentList '-f', "`"$Root\stack\apache\conf\httpd.conf`"" `
                      -WindowStyle Hidden
    }
}
