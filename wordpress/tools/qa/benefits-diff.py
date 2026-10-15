"""QA (group "benefits"): pixel diff of the Astro vs WordPress section shots.
   python3 -I wordpress/tools/qa/benefits-diff.py <outDir>
Prints size match + share of differing pixels and writes diff-*.png."""
import sys, os, glob
from PIL import Image, ImageChops
d = sys.argv[1]
for a in sorted(glob.glob(os.path.join(d, '*-astro-*.png'))):
    w = a.replace('-astro-', '-wp-')
    if not os.path.exists(w):
        print('missing', os.path.basename(w)); continue
    ia, iw = Image.open(a).convert('RGB'), Image.open(w).convert('RGB')
    if ia.size != iw.size:
        print(f'{os.path.basename(a):45s} SIZE astro={ia.size} wp={iw.size}')
        h = min(ia.size[1], iw.size[1]); wd = min(ia.size[0], iw.size[0])
        ia, iw = ia.crop((0, 0, wd, h)), iw.crop((0, 0, wd, h))
    diff = ImageChops.difference(ia, iw).convert('L').point(lambda v: 255 if v > 24 else 0)
    n = sum(1 for v in diff.getdata() if v)
    tot = diff.size[0] * diff.size[1]
    print(f'{os.path.basename(a):45s} {ia.size} diff={n/tot*100:.3f}% bbox={diff.getbbox()}')
    if n:
        diff.save(os.path.join(d, 'diff-' + os.path.basename(a).replace('-astro', '')))
