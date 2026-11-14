import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { authKeys } from '../src/features/auth/api/authKeys.ts';
import { householdKeys } from '../src/features/households/api/householdKeys.ts';
import { residentKeys } from '../src/features/residents/api/residentKeys.ts';

describe('Query Key Factory', () => {
  it('creates stable base namespaces', () => {
    assert.deepEqual(residentKeys.all, ['residents']);
    assert.deepEqual(householdKeys.all, ['households']);
    assert.deepEqual(authKeys.all, ['auth']);
  });

  it('generates distinct keys for distinct filters to prevent cache collisions', () => {
    const key1 = residentKeys.list({ status: 'ACTIVE', page: 1 });
    const key2 = residentKeys.list({ status: 'PROSPECTIVE', page: 1 });
    const key3 = residentKeys.list({ status: 'ACTIVE', page: 2 });

    assert.notDeepEqual(key1, key2);
    assert.notDeepEqual(key1, key3);
  });

  it('namespaces resident detail and household detail without collision', () => {
    const id = '01924b12-9c9e-716b-9c3f-8461abde5001';

    const residentDetailKey = residentKeys.detail(id);
    const householdDetailKey = householdKeys.detail(id);

    assert.deepEqual(residentDetailKey[0], 'residents');
    assert.deepEqual(householdDetailKey[0], 'households');
    assert.notDeepEqual(residentDetailKey, householdDetailKey);
  });

  it('includes relationships tokens in detail query keys', () => {
    const id = 'household-123';
    const keyWithoutIncludes = householdKeys.detail(id);
    const keyWithIncludes = householdKeys.detail(id, ['head', 'members']);

    assert.notDeepEqual(keyWithoutIncludes, keyWithIncludes);
    assert.deepEqual(keyWithIncludes, [
      'households',
      'detail',
      'household-123',
      { includes: ['head', 'members'] },
    ]);
  });
});
