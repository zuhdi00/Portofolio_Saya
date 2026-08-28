from PyQt5 import uic
ui = r'c:\xampp\htdocs\timbanganpython\form_timbangan.ui'
try:
    uic.loadUiType(ui)
    print('UI parse: OK')
except Exception as e:
    print('UI parse: ERROR')
    print(e)
