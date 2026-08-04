# Deployment Guide

How `EmployeeInformationSystem_Setup.exe` is built and what it installs.

## What the installer contains

Everything needed to run the system on a Windows PC that has **nothing**
pre-installed — no Laragon, no XAMPP, no PHP, no MySQL.

| Component | Version | Port |
|---|---|---|
| Apache HTTP Server | 2.4.54 (VS16, x64) | **8080** |
| PHP (thread-safe) | 8.1.10 | — |
| MySQL / MariaDB | 8.0.30 | **3307** |
| The application | — | — |

Ports 8080 and 3307 are deliberate: they never clash with an existing
Laragon/XAMPP/WAMP installation (which use 80 and 3306).

## Building the installer

### One-time setup

1. Install **Inno Setup 6** — <https://jrsoftware.org/isdl.php>
2. Make sure a thread-safe PHP with `php8apache2_4.dll`, an Apache build and
   a MySQL/MariaDB build are available (Laragon's `bin` folder is fine).

### Build

```powershell
cd installer
powershell -ExecutionPolicy Bypass -File build.ps1
```

Result: `installer\dist\EmployeeInformationSystem_Setup.exe` (~61 MB).

Useful switches:

```powershell
# stage the files but do not compile (fast, for inspecting build\)
powershell -ExecutionPolicy Bypass -File build.ps1 -StageOnly

# point at specific component folders instead of auto-detecting
powershell -ExecutionPolicy Bypass -File build.ps1 `
    -ApacheSource "C:\httpd-2.4.x" -PhpSource "C:\php-8.x-ts" -DbSource "C:\mariadb-10.x"
```

### What the build does

1. Copies the application into `build\www`, **excluding** credentials
   (`google_*.json`, `local.php`), local backups and uploaded files.
2. Copies Apache, PHP and the database into `build\stack`, skipping manuals,
   test suites and any existing data directory.
3. Generates the `php.ini` extension list from the DLLs the bundled PHP
   actually ships (some builds compile `zip`/`gd` in statically).
4. **Verifies** before compiling: required files present, the bundled PHP
   provides every needed extension, all four Composer libraries load, and no
   credential file would be shipped. The build fails rather than produce a
   broken or leaky installer.
5. Compiles with Inno Setup.

## What the installer does on the target PC

1. Copies the application and the portable stack.
2. Rewrites the `@@APPDIR@@` placeholder in `httpd.conf`, `php.ini` and
   `my.ini` with the real install path — done through `AfterInstall` hooks so
   the paths are already correct when the database is initialised.
3. Initialises the database data directory and imports
   `employee_information_system.sql`.
4. Writes `www\config\local.php` pointing the application at port 3307.
5. Creates Desktop and Start Menu shortcuts.
6. Optionally adds a Windows Firewall rule and a startup entry.
7. Launches the system.

Silent install (for testing or mass deployment):

```powershell
EmployeeInformationSystem_Setup.exe /VERYSILENT /DIR="C:\EIS" /TASKS="desktopicon,startmenu"
```

## Files in this folder

| File | Purpose |
|---|---|
| `EmployeeInformationSystem.iss` | Inno Setup script |
| `build.ps1` | Stages the stack and compiles |
| `make-icon.php` | Draws `eis.ico` (no external image needed) |
| `templates/httpd.conf` | Apache config, `@@APPDIR@@` placeholder |
| `templates/php.ini` | PHP config, `{#EXTENSIONS#}` filled at build time |
| `templates/my.ini` | Database config |
| `scripts/eis-service.bat` | start / stop / status controller |
| `scripts/eis-launch.vbs` | Shortcut target: starts services, opens browser |
| `scripts/eis-dbsetup.bat` | First-run database initialisation and import |
| `scripts/eis-stop.bat` | "Stop Services" shortcut |
| `LICENSE.txt`, `READMEFIRST.txt` | Shown by the wizard / installed as docs |

`build\` and `dist\` are generated — safe to delete at any time.

## Notes learned while building this

Three problems were found and fixed during real install testing; they are
worth knowing if the stack is ever swapped for different versions.

- **MPM module** — some Apache builds (including 2.4.54 VS16) compile the
  WinNT MPM into the server instead of shipping `mod_mpm_winnt.so`. The
  config loads it inside `<IfFile>` so it works either way.
- **ERROR 1130 on 127.0.0.1** — MySQL 8's `--initialize-insecure` creates
  only `root@localhost`. A TCP client from `127.0.0.1` is refused unless name
  resolution is on. `skip-name-resolve` is therefore *not* set, and
  `eis-dbsetup.bat` additionally creates `root@127.0.0.1` via `--init-file`.
- **Config patch ordering** — `[Run]` entries execute *before*
  `CurStepChanged(ssPostInstall)`, so patching the configs there left the
  database being initialised with an unresolved `@@APPDIR@@` path. The
  patching now happens in `AfterInstall` hooks on the config files themselves.
