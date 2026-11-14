import type { PaginationMeta } from './api';
import type { PrivacyTier, ResidentSummary } from './resident';

export type HouseholdStatus = 'ACTIVE' | 'HEAD_VACANT' | 'MOVED' | 'DISSOLVED' | 'ARCHIVED';

export type HouseholdMemberRelationship =
  | 'KEPALA_KELUARGA'
  | 'SUAMI'
  | 'ISTRI'
  | 'ANAK'
  | 'MENANTU'
  | 'CUCU'
  | 'ORANG_TUA'
  | 'MERTUA'
  | 'FAMILI_LAIN'
  | 'PEMBANTU'
  | 'LAINNYA';

export type HouseholdMoveType = 'INTER_RT' | 'INTER_RW';

export type HouseholdHeadAvailability = 'present' | 'vacant';

export interface HouseholdMember {
  id: string;
  household_id: string;
  resident_id: string;
  relationship: HouseholdMemberRelationship;
  is_active: boolean;
  joined_at: string;
  left_at?: string | null;
  left_reason?: string | null;
  resident?: ResidentSummary;
}

/**
 * Household representation from API.
 * Fields vary based on server-authorized PrivacyTier (directory | normal | sensitive).
 * Masked KK number (e.g. 320101******0001) is a valid string representation.
 */
export interface Household {
  id: string;
  representation: PrivacyTier;
  status: HouseholdStatus;
  organization_id: string;
  rw_id: string;
  rt_id: string;
  active_members_count: number;

  // Normal tier fields (masked when privacy tier is normal)
  kk_number?: string;
  kk_number_masked?: boolean;
  address?: string;
  notes?: string | null;
  created_at?: string;
  updated_at?: string;

  // Optional relations via ?include=
  head?: HouseholdMember | null;
  active_head?: HouseholdMember | null;
  members?: HouseholdMember[];
  active_members?: HouseholdMember[];
}

export type HouseholdIncludeToken =
  | 'organization'
  | 'rw'
  | 'rt'
  | 'head'
  | 'head.resident'
  | 'members'
  | 'members.resident';

export interface HouseholdQueryParams {
  page?: number;
  per_page?: number;
  q?: string;
  sort?: 'created_at' | '-created_at' | 'address' | '-address' | 'status' | '-status';
  status?: HouseholdStatus;
  rt_id?: string;
  rw_id?: string;
  head_availability?: HouseholdHeadAvailability;
  include?: HouseholdIncludeToken[] | string;
}

export interface HouseholdListResponseData {
  data: Household[];
  meta: {
    pagination: PaginationMeta;
    request_id?: string;
  };
}

// -------------------------------------------------------------
// Mutation Payloads
// Strictly omitting forbidden fields:
// organization_id, rw_id, status, from_*, is_external_move
// -------------------------------------------------------------

export interface CreateHouseholdPayload {
  kk_number: string;
  address: string;
  rt_id: string;
  notes?: string;
  initial_head_resident_id?: string;
}

export interface UpdateHouseholdPayload {
  address?: string;
  notes?: string;
}

export interface AddHouseholdMemberPayload {
  resident_id: string;
  relationship: HouseholdMemberRelationship;
  joined_at?: string;
}

export interface RemoveHouseholdMemberPayload {
  left_reason?: string;
  left_at?: string;
}

export interface ChangeHouseholdHeadPayload {
  new_head_resident_id: string;
  old_head_new_relationship?: HouseholdMemberRelationship;
}

export interface MoveHouseholdPayload {
  move_type: 'INTER_RT' | HouseholdMoveType; // 'INTER_RT' only accepted by backend
  target_rt_id: string;
  new_address: string;
  notes?: string;
}

export interface DissolveHouseholdPayload {
  reason?: string;
}
