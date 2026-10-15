"""QA (group "team"): compares the astro/wp entries of team-shots.mjs report.json
and the screenshots (pixel diff ratio).  python3 team-diff.py <outDir>"""
import json, sys, os
out = sys.argv[1]
r = json.load(open(os.path.join(out, 'report.json')))
keys = sorted({k.rsplit('-', 1)[0] for k in r})
try:
    from PIL import Image, ImageChops
except Exception:
    Image = None
def cmp(a, b, path, diffs):
    if isinstance(a, dict) and isinstance(b, dict):
        for k in a:
            cmp(a.get(k), b.get(k), path + '.' + k, diffs)
    elif isinstance(a, list) and isinstance(b, list):
        if len(a) != len(b):
            diffs.append((path, 'len %d vs %d' % (len(a), len(b))))
        for i, (x, y) in enumerate(zip(a, b)):
            cmp(x, y, path + '[%d]' % i, diffs)
    else:
        if isinstance(a, (int, float)) and isinstance(b, (int, float)):
            if abs(a - b) > 0.6:
                diffs.append((path, '%s vs %s' % (a, b)))
        elif a != b:
            diffs.append((path, '%s vs %s' % (a, b)))
for k in keys:
    a, b = r.get(k + '-astro'), r.get(k + '-wp')
    diffs = []
    if a is None or b is None:
        print(k, 'MISSING', a is None, b is None)
        continue
    cmp(a, b, '', diffs)
    pix = ''
    if Image:
        pa, pb = os.path.join(out, k + '-astro.png'), os.path.join(out, k + '-wp.png')
        ia, ib = Image.open(pa).convert('RGB'), Image.open(pb).convert('RGB')
        if ia.size != ib.size:
            pix = 'size %s vs %s' % (ia.size, ib.size)
        else:
            d = ImageChops.difference(ia, ib).convert('L').point(lambda v: 255 if v > 24 else 0)
            n = sum(d.histogram()[255:])
            pix = 'pixdiff %.4f%%' % (100.0 * n / (ia.size[0] * ia.size[1]))
    print('==', k, pix, '%d prop diffs' % len(diffs))
    for p, v in diffs[:40]:
        print('   ', p, v)
