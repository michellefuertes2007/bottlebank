import pathlib
p=pathlib.Path(r'c:\laragon\www\BB\deposit.php')
lines=p.read_text(encoding='utf-8').splitlines()
stack=[]
for i,line in enumerate(lines, start=1):
    for ch in line:
        if ch=='{':
            stack.append((i,line.strip()))
        elif ch=='}':
            if stack:
                stack.pop()
            else:
                print('Extra } at', i)
    if i in (205, 241, 251, 317, 374, 375, 376, 377):
        print('line', i, 'stack depth', len(stack), 'top', stack[-3:] if len(stack)>=3 else stack)

if stack:
    print('Unmatched { at', stack[-1][0], 'line:', stack[-1][1], 'total unmatched', len(stack))
