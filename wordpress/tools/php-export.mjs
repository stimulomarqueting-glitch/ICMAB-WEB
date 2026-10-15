/** Serialises plain JSON data as a PHP array literal (WordPress style). */
export function phpExport(value, depth = 0) {
  const pad = '\t'.repeat(depth + 1);
  const end = '\t'.repeat(depth);
  if (value === null || value === undefined) return 'null';
  if (typeof value === 'boolean') return value ? 'true' : 'false';
  if (typeof value === 'number') return String(value);
  if (typeof value === 'string') return `'${value.replace(/\\/g, '\\\\').replace(/'/g, "\\'")}'`;
  if (Array.isArray(value)) {
    if (!value.length) return 'array()';
    return `array(\n${value.map((v) => `${pad}${phpExport(v, depth + 1)},`).join('\n')}\n${end})`;
  }
  const entries = Object.entries(value);
  if (!entries.length) return 'array()';
  return `array(\n${entries.map(([k, v]) => `${pad}${phpExport(k)} => ${phpExport(v, depth + 1)},`).join('\n')}\n${end})`;
}
