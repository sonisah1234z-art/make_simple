import os, re
root = r'c:\xampp\htdocs\make_simple'
php_files = [f for f in os.listdir(root) if f.endswith('.php')]
refs = {}
all_targets = set()
for fn in php_files:
    path = os.path.join(root, fn)
    text = open(path, encoding='utf-8', errors='ignore').read()
    found = re.findall(r"(?:href|action)\s*=\s*['\"]([^'\"]+\.php)['\"]", text, flags=re.I)
    refs[fn] = found
    all_targets.update(found)
print(len(php_files))
for fn in sorted(refs):
    print(fn, '->', refs[fn])
print('UNREFERENCED')
for fn in sorted(php_files):
    if fn not in all_targets and fn not in ['index.php','admin-login.php','login.php','register.php','setup.php']:
        print(fn)
