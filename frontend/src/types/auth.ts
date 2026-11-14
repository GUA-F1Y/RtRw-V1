export type AccountStatus = 'pending' | 'active' | 'suspended';

export type ScopeLevel = 'organization' | 'rw' | 'rt';

export type AuthStatus =
  | 'idle'
  | 'loading'
  | 'authenticated'
  | 'unauthenticated'
  | 'account_inactive'
  | 'error';

export interface RoleRef {
  id: number;
  key: string;
  name: string;
}

export interface UnitRef {
  id: string;
  number?: number;
  name: string;
}

export interface Membership {
  id: string;
  scope_level: ScopeLevel;
  role: RoleRef;
  organization_id: string;
  organization?: UnitRef;
  rw_id: string | null;
  rw?: UnitRef | null;
  rt_id: string | null;
  rt?: UnitRef | null;
  is_active: boolean;
  created_at: string;
}

export interface User {
  id: string;
  name: string;
  email: string;
  status: AccountStatus;
  memberships?: Membership[];
  created_at?: string;
}

export interface AuthSession {
  token: string;
  token_type: string;
  user: User;
}

export interface ScopeContext {
  scopeLevel?: ScopeLevel;
  organization?: UnitRef;
  rw?: UnitRef | null;
  rt?: UnitRef | null;
  role?: RoleRef;
}

export interface NavigationItem {
  id: string;
  label: string;
  path: string;
  icon?: string;
  badge?: string;
  disabled?: boolean;
  requiredScopeLevel?: ScopeLevel;
  requiredRoleKey?: string;
}
