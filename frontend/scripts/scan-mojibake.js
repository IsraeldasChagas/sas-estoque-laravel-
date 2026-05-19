const fs = require('fs');
const c = fs.readFileSync(require('path').join(__dirname, '../index.html'), 'utf8');
const set = new Set();
const re = /ðŸ[\s\S]{0,6}|â[\s\S]{0,8}|Ã[\s\S]{0,3}/g;
let m;
while ((m = re.exec(c)) !== null) {
  const s = m[0].replace(/[\s<>]/g, '');
  if (s.length >= 2 && s.length <= 12) set.add(s);
}
console.log([...set].sort().join('\n'));
