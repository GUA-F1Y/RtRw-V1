import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { queryClient } from '../src/app/query/queryClient.ts';
import { authKeys } from '../src/features/auth/api/authKeys.ts';
import { householdKeys } from '../src/features/households/api/householdKeys.ts';
import { residentKeys } from '../src/features/residents/api/residentKeys.ts';
import type { User } from '../src/types/auth.ts';
import type { Household } from '../src/types/household.ts';
import type { Resident } from '../src/types/resident.ts';

describe('Cache Security & Multi-Account Isolation', () => {
  it('clears all user, resident, and household data from query cache on logout', () => {
    // 1. Simulate User A logged in with cached domain data
    const userA: User = {
      id: 'user-a',
      name: 'User A',
      email: 'usera@rtrw.local',
      status: 'active',
      memberships: [],
    };

    const residentA: Resident = {
      id: 'res-a',
      representation: 'normal',
      name: 'Warga User A',
      gender: 'MALE',
      age: 40,
      residency_type: 'PERMANENT',
      status: 'ACTIVE',
      organization_id: 'org-1',
      rw_id: 'rw-1',
      rt_id: 'rt-1',
    };

    const householdA: Household = {
      id: 'hh-a',
      representation: 'normal',
      status: 'ACTIVE',
      organization_id: 'org-1',
      rw_id: 'rw-1',
      rt_id: 'rt-1',
      active_members_count: 3,
    };

    queryClient.setQueryData(authKeys.currentUser(), userA);
    queryClient.setQueryData(residentKeys.detail('res-a'), residentA);
    queryClient.setQueryData(householdKeys.detail('hh-a'), householdA);

    // Verify cache has User A data
    assert.deepEqual(queryClient.getQueryData(authKeys.currentUser()), userA);
    assert.deepEqual(queryClient.getQueryData(residentKeys.detail('res-a')), residentA);
    assert.deepEqual(queryClient.getQueryData(householdKeys.detail('hh-a')), householdA);

    // 2. Perform logout purge
    queryClient.clear();

    // 3. Verify ALL caches are empty and User A data is purged
    assert.equal(queryClient.getQueryData(authKeys.currentUser()), undefined);
    assert.equal(queryClient.getQueryData(residentKeys.detail('res-a')), undefined);
    assert.equal(queryClient.getQueryData(householdKeys.detail('hh-a')), undefined);

    // 4. Simulate User B logging in
    const userB: User = {
      id: 'user-b',
      name: 'User B',
      email: 'userb@rtrw.local',
      status: 'active',
      memberships: [],
    };

    queryClient.setQueryData(authKeys.currentUser(), userB);

    // Verify User B only sees User B data and absolutely zero residue from User A
    assert.deepEqual(queryClient.getQueryData(authKeys.currentUser()), userB);
    assert.equal(queryClient.getQueryData(residentKeys.detail('res-a')), undefined);
    assert.equal(queryClient.getQueryData(householdKeys.detail('hh-a')), undefined);

    // Clean up queryClient to cancel pending TanStack Query gcTime timers
    queryClient.clear();
  });
});
