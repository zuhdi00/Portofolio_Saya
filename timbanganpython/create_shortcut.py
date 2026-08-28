import os
import sys
import subprocess
from pathlib import Path

PROJECT_DIR = Path(__file__).resolve().parent
SCRIPT = PROJECT_DIR / 'app_timbangan.py'
ICON_PNG = PROJECT_DIR / 'SPS_Logo1.png'
ICON_ICO = PROJECT_DIR / 'SPS_Logo1.ico'


def desktop_path():
    # Prefer SpecialFolders if possible, else USERPROFILE/Desktop
    try:
        from win32com.shell import shellcon, shell
        return Path(shell.SHGetFolderPath(0, shellcon.CSIDL_DESKTOP, None, 0))
    except Exception:
        return Path(os.path.join(os.environ.get('USERPROFILE', ''), 'Desktop'))


def find_pythonw():
    exe = Path(sys.executable)
    pythonw = exe.with_name('pythonw.exe')
    if pythonw.exists():
        return str(pythonw)
    return str(exe)


def create_via_win32com(lnk_path, target, args, workdir, icon):
    try:
        from win32com.client import Dispatch
        shell = Dispatch('WScript.Shell')
        shortcut = shell.CreateShortcut(str(lnk_path))
        shortcut.TargetPath = target
        shortcut.Arguments = args
        shortcut.WorkingDirectory = workdir
        shortcut.IconLocation = icon
        shortcut.Save()
        return True
    except Exception:
        return False


def create_via_powershell(lnk_path, target, args, workdir, icon):
    # Use PowerShell COM to create .lnk (works without pywin32)
    ps = f"$WshShell = New-Object -ComObject WScript.Shell; $s = $WshShell.CreateShortcut('{lnk_path}');"
    ps += f"$s.TargetPath = '{target}'; $s.Arguments = '{args}'; $s.WorkingDirectory = '{workdir}';"
    ps += f"$s.IconLocation = '{icon}'; $s.Save();"
    try:
        subprocess.run(["powershell", "-NoProfile", "-Command", ps], check=True)
        return True
    except Exception:
        return False


def create_fallback_bat(bat_path, python_exec, script_path):
    content = f'start "" "{python_exec}" "{script_path}"\n'
    bat_path.write_text(content, encoding='utf-8')
    return True


def main():
    desk = desktop_path()
    lnk = desk / 'Timbangan.lnk'

    python_exec = find_pythonw()
    target = python_exec
    args = str(SCRIPT)
    workdir = str(PROJECT_DIR)
    icon = str(ICON_ICO if ICON_ICO.exists() else ICON_PNG if ICON_PNG.exists() else '')

    print('Desktop:', desk)
    print('Creating shortcut ->', lnk)

    ok = create_via_win32com(lnk, target, args, workdir, icon)
    if ok:
        print('Shortcut created via win32com:', lnk)
        return

    ok = create_via_powershell(str(lnk), target, args, workdir, icon)
    if ok:
        print('Shortcut created via PowerShell COM:', lnk)
        return

    # Fallback: create a .bat on desktop
    bat = desk / 'Run_Timbangan.bat'
    create_fallback_bat(bat, python_exec, str(SCRIPT))
    print('win32com/PowerShell not available — created BAT fallback:', bat)


if __name__ == '__main__':
    main()
