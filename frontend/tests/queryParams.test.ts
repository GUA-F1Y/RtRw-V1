import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { buildQueryString } from '../src/lib/api/queryParams.ts';

describe('Query Parameter Serialization', () => {
  it('returns empty string when given empty, null, or undefined params', () => {
    assert.equal(buildQueryString(), '');
    assert.equal(buildQueryString(undefined), '');
    assert.equal(buildQueryString({}), '');
  });

  it('omits undefined, null, and empty string properties', () => {
    const result = buildQueryString({
      status: 'ACTIVE',
      q: '',
      rt_id: undefined,
      rw_id: null,
      page: 2,
    });

    assert.equal(result, '?status=ACTIVE&page=2');
  });

  it('serializes array parameters as comma-separated values', () => {
    const result = buildQueryString({
      include: ['household', 'movements', 'status_history'],
    });

    // In URL query string, commas may be encoded as %2C or literal
    assert.ok(
      result === '?include=household%2Cmovements%2Cstatus_history' ||
        result === '?include=household,movements,status_history'
    );
  });

  it('converts booleans properly to 1 and 0', () => {
    const result = buildQueryString({
      has_more: true,
      is_active: false,
    });

    assert.equal(result, '?has_more=1&is_active=0');
  });

  it('encodes special characters and spaces correctly', () => {
    const result = buildQueryString({
      q: 'Jl. Melati & Mawar #10',
    });

    assert.ok(result.includes('Jl.+Melati') || result.includes('Jl.%20Melati'));
    assert.ok(result.includes('%26'));
  });

  it('serializes combined filter, pagination, sort, and includes', () => {
    const result = buildQueryString({
      page: 1,
      per_page: 25,
      sort: '-created_at',
      status: 'ACTIVE',
      rt_id: '01924b12-9c9e-716b-9c3f-8461abde5003',
      include: ['head', 'members'],
    });

    assert.ok(result.startsWith('?'));
    assert.ok(result.includes('page=1'));
    assert.ok(result.includes('per_page=25'));
    assert.ok(result.includes('sort=-created_at'));
    assert.ok(result.includes('status=ACTIVE'));
    assert.ok(result.includes('rt_id=01924b12-9c9e-716b-9c3f-8461abde5003'));
    assert.ok(result.includes('include=head%2Cmembers') || result.includes('include=head,members'));
  });
});
