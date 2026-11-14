import type { NavigationItem, ScopeContext, User } from '../../types/auth.ts';

/**
 * Structural Navigation Definition
 * - RW-centric coordination is a first-class context.
 * - Non-implemented domain screens are flagged to prevent broken navigation.
 */
export const APP_NAVIGATION_ITEMS: NavigationItem[] = [
  {
    id: 'overview',
    label: 'RW Overview',
    path: '/app',
    icon: 'layout-dashboard',
  },
  {
    id: 'hierarchy',
    label: 'Hierarchy & Units',
    path: '/app/hierarchy',
    icon: 'network',
  },
  {
    id: 'residents',
    label: 'Resident Directory',
    path: '/app/residents',
    icon: 'users',
  },
  {
    id: 'households',
    label: 'Household Registry',
    path: '/app/households',
    icon: 'home',
  },
  {
    id: 'finance',
    label: 'Kas & Keuangan',
    path: '/app/finance',
    icon: 'wallet',
  },
  {
    id: 'governance',
    label: 'RW Governance',
    path: '/app/governance',
    icon: 'shield-check',
    badge: 'Planned',
    disabled: true,
    requiredScopeLevel: 'rw',
  },
];

/**
 * Derives the active organizational scope from user memberships.
 * Default primary scope is the highest level membership (organization > rw > rt).
 */
export function deriveActiveScope(user: User | null): ScopeContext | null {
  if (!user || !user.memberships || user.memberships.length === 0) {
    return null;
  }

  // Active membership preference: organization level > rw level > rt level
  const activeMemberships = user.memberships.filter((m) => m.is_active);
  if (activeMemberships.length === 0) {
    return null;
  }

  // Sort by scope level priority
  const priorityOrder = { organization: 3, rw: 2, rt: 1 };
  const sorted = [...activeMemberships].sort(
    (a, b) => (priorityOrder[b.scope_level] || 0) - (priorityOrder[a.scope_level] || 0)
  );

  const primary = sorted[0];

  return {
    scopeLevel: primary.scope_level,
    organization: primary.organization,
    rw: primary.rw,
    rt: primary.rt,
    role: primary.role,
  };
}

/**
 * Filter visible navigation items based on current user scope.
 * Note: Hiding navigation items is a UX convenience only; backend authorization remains authoritative.
 */
export function getVisibleNavItems(
  items: NavigationItem[] = APP_NAVIGATION_ITEMS,
  user: User | null
): NavigationItem[] {
  if (!user) {
    return [];
  }

  const activeScope = deriveActiveScope(user);

  return items.filter((item) => {
    // If item requires a specific scope level, ensure user holds at least that scope or higher
    if (item.requiredScopeLevel) {
      if (!activeScope?.scopeLevel) return false;

      const priorityOrder = { organization: 3, rw: 2, rt: 1 };
      const userLevel = priorityOrder[activeScope.scopeLevel] || 0;
      const requiredLevel = priorityOrder[item.requiredScopeLevel] || 0;

      if (userLevel < requiredLevel) {
        return false;
      }
    }

    if (item.requiredRoleKey) {
      if (activeScope?.role?.key !== item.requiredRoleKey) {
        return false;
      }
    }

    return true;
  });
}
