import sys
import traceback

try:
    # Ensure current folder is on sys.path when run from workspace root
    sys.path.insert(0, r'c:\xampp\htdocs\timbanganpython')
    import app_timbangan
    print('IMPORT_OK')
except Exception:
    print('IMPORT_ERROR')
    traceback.print_exc()
