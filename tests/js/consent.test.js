// Run: node --test tests/js
const test = require('node:test');
const assert = require('node:assert');
const fs = require('fs');
const path = require('path');

const src = fs.readFileSync(path.join(__dirname, '../../assets/consent.js'), 'utf8');
const start = src.indexOf('function normalizePhone');
assert.ok(start > 0, 'normalizePhone not found');
let depth = 0, end = -1;
for (let i = src.indexOf('{', start); i < src.length; i++) {
  if (src[i] === '{') depth++;
  if (src[i] === '}' && --depth === 0) { end = i + 1; break; }
}
const fnSrc = src.slice(start, end);
const make = (cfg) => new Function('cfg', fnSrc + '; return normalizePhone;')(cfg);

const cfg = { default_cc: '91', calling_codes: { IN: '91', GB: '44', US: '1', DE: '49' } };
const np = make(cfg);

test('10-digit Indian number gets 91', () => assert.strictEqual(np('9876543210', ''), '919876543210'));
test('spaces/dashes ignored', () => assert.strictEqual(np('98765-43210', ''), '919876543210'));
test('leading trunk 0 stripped', () => assert.strictEqual(np('09876543210', ''), '919876543210'));
test('billing country decides code (GB)', () => assert.strictEqual(np('07911 123456', 'GB'), '447911123456'));
test('billing country US', () => assert.strictEqual(np('(415) 555-2671', 'US'), '14155552671'));
test('unknown country falls back to default', () => assert.strictEqual(np('9876543210', 'ZZ'), '919876543210'));
test('+ prefix kept as typed', () => assert.strictEqual(np('+44 7911 123456', 'IN'), '447911123456'));
test('00 prefix converted', () => assert.strictEqual(np('0044 7911 123456', 'IN'), '447911123456'));
test('already has country code (12 digits, no +) left alone', () => assert.strictEqual(np('919876543210', ''), '919876543210'));
test('empty / null input', () => { assert.strictEqual(np('', ''), ''); assert.strictEqual(np(null, ''), ''); });
test('no default cc and no country: digits only', () => assert.strictEqual(make({})('9876543210', ''), '9876543210'));
