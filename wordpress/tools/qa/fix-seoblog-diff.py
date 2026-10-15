"""QA (fix bucket "seoblog"): compares two folders of screenshots taken by
fix-seoblog-shots.mjs (same file names) and reports, per file, the size
change and the share / bounding box of changed pixels; writes a red-marked
diff image for every file that changed.
    python3 wordpress/tools/qa/fix-seoblog-diff.py <beforeDir> <afterDir> <diffDir>
"""
import os
import sys

from PIL import Image, ImageChops

before, after, out = sys.argv[1], sys.argv[2], sys.argv[3]
os.makedirs(out, exist_ok=True)
for name in sorted(os.listdir(before)):
    if not name.endswith('.png') or not os.path.exists(os.path.join(after, name)):
        continue
    a = Image.open(os.path.join(before, name)).convert('RGB')
    b = Image.open(os.path.join(after, name)).convert('RGB')
    if a.size != b.size:
        print(f'{name}: SIZE {a.size} -> {b.size}')
        h = min(a.size[1], b.size[1])
        a, b = a.crop((0, 0, a.size[0], h)), b.crop((0, 0, b.size[0], h))
    diff = ImageChops.difference(a, b).convert('L').point(lambda v: 255 if v > 24 else 0)
    box = diff.getbbox()
    if not box:
        print(f'{name}: identical')
        continue
    share = sum(diff.histogram()[255:]) / (diff.size[0] * diff.size[1])
    print(f'{name}: {share:.4%} changed, box {box}')
    mark = b.copy()
    mark.paste((255, 0, 0), mask=diff)
    mark.save(os.path.join(out, name))
