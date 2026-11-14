import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import {
  APP_NAVIGATION_ITEMS,
  deriveActiveScope,
  getVisibleNavItems,
} from '../src/app/navigation/navCapabilities.ts';
import type { NavigationItem, User } from '../src/types/auth.ts';

describe('Navigation Capabilities & Scope Derivation', () => {
  it('returns null active scope when user has no memberships', () => {
    const user: User = {
      id: 'user-1',
      name: 'Warga Biasa',
      email: 'warga@rtrw.local',
      status: 'active',
      memberships: [],
    };

    assert.equal(deriveActiveScope(user), null);
    assert.deepEqual(getVisibleNavItems(APP_NAVIGATION_ITEMS, null), []);
  });

  it('prioritizes organization > rw > rt scope when user has multiple memberships', () => {
    const userWithMultiScope: User = {
      id: 'user-2',
      name: 'Pengurus RW',
      email: 'pengurus@rtrw.local',
      status: 'active',
      memberships: [
        {
          id: 'mem-rt',
          scope_level: 'rt',
          is_active: true,
          created_at: '2026-01-01',
          organization_id: 'org-1',
          rw_id: 'rw-1',
          rt_id: 'rt-1',
          role: { id: 1, key: 'rt_officer', name: 'Pengurus RT' },
          rt: { id: 'rt-1', number: 1, name: 'RT 01' },
        },
        {
          id: 'mem-rw',
          scope_level: 'rw',
          is_active: true,
          created_at: '2026-01-01',
          organization_id: 'org-1',
          rw_id: 'rw-1',
          rt_id: null,
          role: { id: 2, key: 'rw_chairman', name: 'Ketua RW' },
          rw: { id: 'rw-1', number: 1, name: 'RW 01' },
        },
      ],
    };

    const activeScope = deriveActiveScope(userWithMultiScope);
    assert.ok(activeScope !== null);
    assert.equal(activeScope?.scopeLevel, 'rw');
    assert.equal(activeScope?.rw?.name, 'RW 01');
    assert.equal(activeScope?.role?.name, 'Ketua RW');
  });

  it('filters navigation items based on required scope level', () => {
    const testItems: NavigationItem[] = [
      { id: '1', label: 'Public Nav', path: '/app' },
      { id: '2', label: 'RW Only', path: '/app/rw', requiredScopeLevel: 'rw' },
      { id: '3', label: 'Org Only', path: '/app/org', requiredScopeLevel: 'organization' },
    ];

    const rwUser: User = {
      id: 'user-rw',
      name: 'Ketua RW',
      email: 'ketua.rw@rtrw.local',
      status: 'active',
      memberships: [
        {
          id: 'mem-1',
          scope_level: 'rw',
          is_active: true,
          created_at: '2026-01-01',
          organization_id: 'org-1',
          rw_id: 'rw-1',
          rt_id: null,
          role: { id: 2, key: 'rw_chairman', name: 'Ketua RW' },
        },
      ],
    };

    const visible = getVisibleNavItems(testItems, rwUser);
    const ids = visible.map((v) => v.id);

    // RW user should see Public Nav (1) and RW Only (2), but NOT Org Only (3)
    assert.ok(ids.includes('1'));
    assert.ok(ids.includes('2'));
    assert.ok(!ids.includes('3'));
  });

  it('flags unreleased domain navigation items as disabled to prevent broken links', () => {
    const user: User = {
      id: 'user-3',
      name: 'User',
      email: 'user@rtrw.local',
      status: 'active',
      memberships: [
        {
          id: 'mem-1',
          scope_level: 'rw',
          is_active: true,
          created_at: '2026-01-01',
          organization_id: 'org-1',
          rw_id: 'rw-1',
          rt_id: null,
          role: { id: 2, key: 'rw_chairman', name: 'Ketua RW' },
        },
      ],
    };

    const visible = getVisibleNavItems(APP_NAVIGATION_ITEMS, user);

    const residentNav = visible.find((item) => item.id === 'residents');
    const householdNav = visible.find((item) => item.id === 'households');
    const financeNav = visible.find((item) => item.id === 'finance');

    // residents is now live (Phase 17 complete) — should NOT be disabled
    assert.equal(residentNav?.disabled, undefined);
    assert.equal(residentNav?.badge, undefined);
    // households is now live (Phase 18 complete) — should NOT be disabled
    assert.equal(householdNav?.disabled, undefined);
    assert.equal(householdNav?.badge, undefined);
    // finance is now live (Phase 22 complete) — should NOT be disabled
    assert.equal(financeNav?.disabled, undefined);
    assert.equal(financeNav?.badge, undefined);
    // governance remains unreleased
    const governanceNav = visible.find((item) => item.id === 'governance');
    assert.equal(governanceNav?.disabled, true);
    assert.equal(governanceNav?.badge, 'Planned');
  });
});
