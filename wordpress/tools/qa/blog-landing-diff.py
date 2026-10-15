"""QA (module "blog"): compares two runs of blog-landing.mjs.
   python3 -I wordpress/tools/qa/blog-landing-diff.py <outDir> <tagA> <tagB>
Prints the share of differing pixels per screenshot and every element whose
box or key computed style changed between the two runs."""
import sys, os, glob, json
from PIL import Image, ImageChops

d, a, b = sys.argv[1], sys.argv[2], sys.argv[3]
for fa in sorted(glob.glob(os.path.join(d, a + '-*.png'))):
    fb = fa.replace(os.sep + a + '-', os.sep + b + '-')
    if not os.path.exists(fb):
        print('missing', os.path.basename(fb))
        continue
    ia, ib = Image.open(fa).convert('RGB'), Image.open(fb).convert('RGB')
    note = ''
    if ia.size != ib.size:
        note = f' SIZE {ia.size} vs {ib.size}'
        h, w = min(ia.size[1], ib.size[1]), min(ia.size[0], ib.size[0])
        ia, ib = ia.crop((0, 0, w, h)), ib.crop((0, 0, w, h))
    diff = ImageChops.difference(ia, ib).convert('L').point(lambda v: 255 if v > 16 else 0)
    n = sum(1 for v in diff.getdata() if v)
    print(f'{os.path.basename(fa)[len(a) + 1:]:28s} diff={n / (diff.size[0] * diff.size[1]) * 100:.3f}% bbox={diff.getbbox()}{note}')
    if n:
        diff.save(os.path.join(d, f'diff-{a}-{b}-' + os.path.basename(fa)[len(a) + 1:]))

ma = json.load(open(os.path.join(d, a + '-metrics.json')))
mb = json.load(open(os.path.join(d, b + '-metrics.json')))
for key in ma:
    ea, eb = ma[key], mb.get(key, [])
    if len(ea) != len(eb):
        print(key, 'element count', len(ea), 'vs', len(eb))
    shown = 0
    for x, y in zip(ea, eb):
        if x != y and shown < 8:
            changed = {k: (x[k], y.get(k)) for k in x if x[k] != y.get(k)}
            print(' ', key, x['el'][:70], changed)
            shown += 1
