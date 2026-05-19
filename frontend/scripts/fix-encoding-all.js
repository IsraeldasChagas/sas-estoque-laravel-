/**
 * Corrige mojibake UTF-8 em index.html e app.js
 */
const fs = require('fs');
const path = require('path');

const ROOT = path.join(__dirname, '..');
const FILES = ['index.html', 'app.js'];

/** Caractere “estendido” → byte original (cp1252 / Latin-1). */
const CHAR_TO_BYTE = new Map([
  [0x00f0, 0xf0], [0x0178, 0x9f], [0x0152, 0x8c], [0x017e, 0x9e],
  [0x0161, 0x9a], [0x0160, 0x8a], [0x017d, 0x8e],
  [0x2019, 0x92], [0x2018, 0x91], [0x201c, 0x93], [0x201d, 0x94], [0x201e, 0x84],
  [0x2039, 0x8b], [0x203a, 0x9b], [0x20ac, 0x80],
  [0x2013, 0x96], [0x2014, 0x97], [0x2020, 0x86], [0x2026, 0x85], [0x00a0, 0xa0],
  [0x00b0, 0xb0], [0x00e9, 0xe9], [0x00e7, 0xe7], [0x00e3, 0xe3],
  [0x00e1, 0xe1], [0x00ed, 0xed], [0x00f3, 0xf3], [0x00fa, 0xfa],
  [0x00c1, 0xc1], [0x00c0, 0xc0], [0x00e2, 0xe2], [0x00ef, 0xef],
  [0x00b8, 0xb8], [0x008f, 0x8f],
]);

function charToByte(ch) {
  const cp = ch.charCodeAt(0);
  if (CHAR_TO_BYTE.has(cp)) return CHAR_TO_BYTE.get(cp);
  if (cp <= 0xff) return cp;
  return null;
}

function isGoodUtf8(s) {
  return s && !s.includes('\uFFFD') && s.length > 0;
}

/** Tenta reparar sequência que começa com ð (emoji / símbolo UTF-8 3–4 bytes). */
function fixEmojiSequence(match) {
  const bytes = [];
  for (const ch of match) {
    const b = charToByte(ch);
    if (b === null) return match;
    bytes.push(b);
  }
  try {
    const out = Buffer.from(bytes).toString('utf8');
    if (isGoodUtf8(out) && out.length <= 8) return out;
  } catch (_) {}
  return match;
}

const PT_REPLACEMENTS = [
  ['Ã\u00A0', 'à'],
  ['Ã£', 'ã'], ['Ã©', 'é'], ['Ãµ', 'õ'], ['Ã¡', 'á'], ['Ã§', 'ç'],
  ['Ã‰', 'É'], ['Ã­', 'í'], ['Ãº', 'ú'], ['Ã³', 'ó'], ['Ã¢', 'â'],
  ['Ãª', 'ê'], ['Ã ', 'à'], ['Ã€', 'À'], ['Ã', 'Á'], ['Ã', 'Í'],
  ['â€¢', '•'],
  ['ï¸\u008F', '\uFE0F'],
  ['Ãµ', 'õ'], ['Ã§', 'ç'], ['Ã—', '×'],
  ['â€œ', '\u201C'], ['â€\u009D', '\u201D'], ['â€\u009d', '\u201D'],
  ['â€"', '\u2014'], ['â€"', '\u2014'], ['â€¦', '\u2026'],
  ['â€“', '\u2014'], ['â€"', '\u2014'],
  ['â†\u0090', '\u2190'], ['â†\u0092', '\u2192'],
  ['â–¼', '\u25BC'], ['â–¾', '\u25BE'],
  ['âœ…', '\u2705'], ['âœ\u008F', '\u270F'],
  ['âœ\u008Fï¸\u008F', '\u270F\uFE0F'],
  ['âš ï¸\u008F', '\u26A0\uFE0F'], ['âšï¸\u008F', '\u26A0\uFE0F'],
  ['â¬‡ï¸\u008F', '\u2B07\uFE0F'], ['â¬‡ï¸', '\u2B07\uFE0F'],
  ['âˆ’', '\u2212'],
  ['\u00e2\u20ac\u201c', '\u2014'],
  ['\u00e2\u20ac\u201d', '\u2014'],
  ['\u00e2\u20ac\u2013', '\u2014'],
];

function fixContent(text) {
  let c = text;
  for (const [from, to] of PT_REPLACEMENTS) {
    if (c.includes(from)) c = c.split(from).join(to);
  }
  // Emojis/símbolos UTF-8 lidos como cp1252: ð… ou â…
  c = c.replace(/[ðâ][\u0080-\u024F\u2010-\u2027\u2030-\u2047\u20A0-\u20BF\uFE00-\uFE0F]{0,6}/g, fixEmojiSequence);
  c = c.replace(/\\r\\n(\s*<!--)/g, '\n$1');
  return c;
}

function countIssues(text) {
  return (text.match(/Ã|â€|ðŸ|âœ|âš|â–|â¬|â†|âˆ/g) || []).length;
}

for (const file of FILES) {
  const fp = path.join(ROOT, file);
  if (!fs.existsSync(fp)) continue;
  let c = fs.readFileSync(fp, 'utf8');
  let prev;
  let n = 0;
  do {
    prev = c;
    c = fixContent(c);
    n++;
  } while (c !== prev && n < 5);
  fs.writeFileSync(fp, c, 'utf8');
  console.log(`${file}: ${countIssues(c)} problemas restantes (passes: ${n})`);
}

const idxPath = path.join(ROOT, 'index.html');
let idx = fs.readFileSync(idxPath, 'utf8');
const ver = '20260520-encoding-fix3';
idx = idx.replace(/style\.css\?v=[^"]+/, `style.css?v=${ver}`);
idx = idx.replace(/app\.js\?v=[^"]+/, `app.js?v=${ver}`);
fs.writeFileSync(idxPath, idx, 'utf8');
console.log(`Cache: ${ver}`);
