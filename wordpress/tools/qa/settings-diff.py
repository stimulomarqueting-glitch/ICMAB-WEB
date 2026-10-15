"""QA (module "settings"): pixel + markup diff of the Astro vs WordPress
header / footer / open-menu shots made by settings-parity.mjs.
   python3 -I wordpress/tools/qa/settings-diff.py <outDir>
Prints size match + share of differing pixels, writes diff-*.png, and the
first differing lines of the markup descriptions."""
import sys, os, glob, difflib
from PIL import Image, ImageChops
import numpy as np

d = sys.argv[1]
worst = 0.0
for a in sorted(glob.glob(os.path.join(d, '*-astro-*.png'))):
    w = a.replace('-astro-', '-wp-')
    name = os.path.basename(a).replace('-astro', '')
    if not os.path.exists(w):
        print('missing', os.path.basename(w))
        continue
    ia, iw = Image.open(a).convert('RGB'), Image.open(w).convert('RGB')
    size = 'same' if ia.size == iw.size else f'SIZE astro={ia.size} wp={iw.size}'
    if ia.size != iw.size:
        h = min(ia.size[1], iw.size[1]); wd = min(ia.size[0], iw.size[0])
        ia, iw = ia.crop((0, 0, wd, h)), iw.crop((0, 0, wd, h))
    diff = ImageChops.difference(ia, iw).convert('L').point(lambda v: 255 if v > 24 else 0)
    n = int(np.count_nonzero(np.asarray(diff)))
    tot = diff.size[0] * diff.size[1]
    pct = n / tot * 100
    worst = max(worst, pct)
    print(f'{name:38s} {size:30s} diff={pct:.3f}% bbox={diff.getbbox()}')
    if n:
        diff.save(os.path.join(d, 'diff-' + name))

print(f'worst pixel diff: {worst:.3f}%')

for a in sorted(glob.glob(os.path.join(d, 'markup-astro-*.txt'))):
    w = a.replace('-astro-', '-wp-')
    if not os.path.exists(w):
        continue
    la = open(a).read().splitlines()
    lw = open(w).read().splitlines()
    delta = [l for l in difflib.unified_diff(la, lw, 'astro', 'wp', n=0, lineterm='') if not l.startswith(('---', '+++', '@@'))]
    print(f'{os.path.basename(a).replace("markup-astro-", ""):30s} markup: {"identical" if not delta else str(len(delta)) + " lines differ"}')
    for l in delta[:12]:
        print('    ' + l)
