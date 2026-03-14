import sys
path = r'c:\laragon\www\BB\deposit.php'
text = open(path, 'r', encoding='utf-8', errors='replace').read()
balance = 0
neg = None
for idx, line in enumerate(text.splitlines(), start=1):
    for ch in line:
        if ch == '{':
            balance += 1
        elif ch == '}':
            balance -= 1
    if balance < 0 and neg is None:
        neg = idx

print('total open-close', text.count('{') - text.count('}'))
if neg:
    print('negative at line', neg)
print('final balance', balance)
