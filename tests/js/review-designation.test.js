import assert from 'node:assert/strict';
import fs from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const context = { document: { getElementById: () => null } };
const source = fs.readFileSync(new URL('../../public/js/review-designation.js', import.meta.url), 'utf8');
vm.runInNewContext(source, context);

test('a Saturday anniversary moves to Friday', () => {
    const result = context.suggestedDesignationEndDate('2026-08-21');
    assert.equal(result.date, '2027-08-20');
    assert.equal(result.movedFrom, 'sábado');
});

test('a Sunday anniversary moves to Friday', () => {
    const result = context.suggestedDesignationEndDate('2026-08-22');
    assert.equal(result.date, '2027-08-20');
    assert.equal(result.movedFrom, 'domingo');
});

test('a weekday anniversary remains unchanged', () => {
    const result = context.suggestedDesignationEndDate('2026-10-01');
    assert.equal(result.date, '2027-10-01');
    assert.equal(result.movedFrom, null);
});

test('a leap day uses the last day of February next year', () => {
    assert.equal(context.suggestedDesignationEndDate('2024-02-29').date, '2025-02-28');
    assert.equal(context.suggestedDesignationEndDate('2026-02-30'), null);
});
