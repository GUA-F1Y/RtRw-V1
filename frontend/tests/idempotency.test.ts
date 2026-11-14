import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { generateIdempotencyKey } from '../src/lib/api/idempotency.ts';

describe('Idempotency Key Generation', () => {
  it('generates valid UUID formatted keys', () => {
    const key = generateIdempotencyKey();
    assert.equal(typeof key, 'string');
    // UUID regex pattern
    const uuidRegex = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;
    assert.ok(uuidRegex.test(key), `Key "${key}" is not a valid UUID`);
  });

  it('generates unique keys on subsequent calls', () => {
    const key1 = generateIdempotencyKey();
    const key2 = generateIdempotencyKey();
    const key3 = generateIdempotencyKey();

    assert.notEqual(key1, key2);
    assert.notEqual(key2, key3);
    assert.notEqual(key1, key3);
  });
});
