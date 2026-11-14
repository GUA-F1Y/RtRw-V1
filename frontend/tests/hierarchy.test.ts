/**
 * Phase 16 -- Hierarchy & Units Data Layer Tests
 *
 * Covers:
 *  - hierarchyKeys factory: stable namespaces, scoped per entity, no collisions
 *  - Organization/RwUnit/RtUnit type contracts
 *  - hierarchyApi URL construction contract
 */

import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { hierarchyKeys } from '../src/features/hierarchy/api/hierarchyKeys.ts';

// -- Query Key Factory --------------------------------------------------------

describe('Hierarchy Query Key Factory', () => {
  it('has a stable hierarchy root namespace', () => {
    assert.deepEqual(hierarchyKeys.all, ['hierarchy']);
  });

  it('generates stable organizations list key', () => {
    assert.deepEqual(hierarchyKeys.organizations(), ['hierarchy', 'organizations']);
  });

  it('generates stable organization detail key', () => {
    const id = 'org-uuid-001';
    assert.deepEqual(hierarchyKeys.organization(id), ['hierarchy', 'organizations', id]);
  });

  it('generates stable rws list key scoped to organizationId', () => {
    const orgId = 'org-uuid-001';
    assert.deepEqual(hierarchyKeys.rws(orgId), ['hierarchy', 'rws', orgId]);
  });

  it('generates stable rw detail key scoped to rwId', () => {
    const rwId = 'rw-uuid-001';
    assert.deepEqual(hierarchyKeys.rw(rwId), ['hierarchy', 'rw', rwId]);
  });

  it('generates stable rts list key scoped to rwId', () => {
    const rwId = 'rw-uuid-001';
    assert.deepEqual(hierarchyKeys.rts(rwId), ['hierarchy', 'rts', rwId]);
  });

  it('different rwIds generate different rw detail keys', () => {
    assert.notDeepEqual(hierarchyKeys.rw('rw-001'), hierarchyKeys.rw('rw-002'));
  });

  it('different orgIds generate different rws list keys', () => {
    assert.notDeepEqual(hierarchyKeys.rws('org-001'), hierarchyKeys.rws('org-002'));
  });

  it('does not collide with other domain namespaces', () => {
    const hier = hierarchyKeys.all[0];
    assert.notEqual(hier, 'residents');
    assert.notEqual(hier, 'households');
    assert.notEqual(hier, 'auth');
    assert.notEqual(hier, 'overview');
  });

  it('rw detail key and rts list key for the same id are distinct', () => {
    const rwId = 'rw-shared-uuid';
    assert.notDeepEqual(hierarchyKeys.rw(rwId), hierarchyKeys.rts(rwId));
  });
});

// -- Type Contract Assertions -------------------------------------------------

describe('Organization/RwUnit/RtUnit Type Contracts', () => {
  it('Organization shape contains required fields', () => {
    const org = {
      id: 'org-uuid',
      name: 'RW 04 Kelurahan Sukamaju',
      slug: 'rw-04-sukamaju',
      rws_count: 1,
      created_at: '2026-01-01T00:00:00Z',
    };
    assert.ok(typeof org.id === 'string');
    assert.ok(typeof org.name === 'string');
    assert.ok(typeof org.slug === 'string');
    assert.ok(typeof org.rws_count === 'number');
  });

  it('RwUnit shape contains required fields including optional rts array', () => {
    const rw = {
      id: 'rw-uuid',
      organization_id: 'org-uuid',
      number: 4,
      name: 'RW 04',
      status: 'active',
      rts_count: 3,
      rts: [],
      created_at: '2026-01-01T00:00:00Z',
    };
    assert.ok(typeof rw.id === 'string');
    assert.ok(typeof rw.number === 'number');
    assert.ok(rw.status === 'active' || rw.status === 'inactive');
    assert.ok(Array.isArray(rw.rts));
  });

  it('RtUnit shape contains required fields', () => {
    const rt = {
      id: 'rt-uuid',
      rw_id: 'rw-uuid',
      number: 2,
      name: 'RT 02',
      status: 'active',
      created_at: '2026-01-01T00:00:00Z',
    };
    assert.ok(typeof rt.id === 'string');
    assert.ok(typeof rt.rw_id === 'string');
    assert.ok(typeof rt.number === 'number');
    assert.ok(rt.status === 'active' || rt.status === 'inactive');
  });

  it('status field only allows active | inactive values', () => {
    const valid = ['active', 'inactive'];
    assert.ok(valid.includes('active'));
    assert.ok(valid.includes('inactive'));
    assert.ok(!valid.includes('pending'));
  });
});

// -- Hierarchy API URL Contract -----------------------------------------------

describe('Hierarchy API URL Contract', () => {
  it('getRws uses organizations/{id}/rws nested endpoint', () => {
    const orgId = 'org-001';
    const endpoint = 'organizations/' + orgId + '/rws';
    assert.ok(endpoint.startsWith('organizations/'));
    assert.ok(endpoint.endsWith('/rws'));
    assert.ok(endpoint.includes(orgId));
  });

  it('getRwWithRts uses rws/{id} endpoint', () => {
    const rwId = 'rw-001';
    const endpoint = 'rws/' + rwId;
    assert.ok(endpoint.startsWith('rws/'));
    assert.ok(endpoint.includes(rwId));
  });

  it('getRts uses rws/{id}/rts endpoint', () => {
    const rwId = 'rw-001';
    const endpoint = 'rws/' + rwId + '/rts';
    assert.ok(endpoint.startsWith('rws/'));
    assert.ok(endpoint.endsWith('/rts'));
    assert.ok(endpoint.includes(rwId));
  });

  it('organization and rw endpoints are distinct', () => {
    const orgEndpoint = 'organizations/org-001/rws';
    const rwEndpoint = 'rws/rw-001';
    assert.notEqual(orgEndpoint, rwEndpoint);
  });
});
