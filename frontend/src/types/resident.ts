import type { PaginationMeta } from './api';

export type PrivacyTier = 'directory' | 'normal' | 'sensitive';

export type ResidentGender = 'MALE' | 'FEMALE';

export type ResidentStatus = 'PROSPECTIVE' | 'ACTIVE' | 'INACTIVE' | 'MOVED' | 'DECEASED';

export type ResidencyType = 'PERMANENT' | 'TEMPORARY' | 'SEASONAL';

export type ResidentMaritalStatus = 'SINGLE' | 'MARRIED' | 'DIVORCED' | 'WIDOWED';

export type ResidentBloodType = 'A' | 'B' | 'AB' | 'O';

export type ResidentEducation =
  | 'SD'
  | 'SMP'
  | 'SMA'
  | 'DIPLOMA'
  | 'SARJANA'
  | 'MAGISTER'
  | 'DOKTOR'
  | 'OTHER';

export type ResidencyMovementType = 'TRANSFER_INTERNAL' | 'MOVE_OUT_EXTERNAL' | 'HOUSEHOLD_SPLIT';

export type ResidentStatusIntent = 'DEACTIVATE' | 'REACTIVATE' | 'MARK_DECEASED';

export interface ScopeRefSummary {
  organization_id: string;
  rw_id?: string | null;
  rt_id?: string | null;
  household_id?: string | null;
  rw_number?: number | null;
  rt_number?: number | null;
}

export interface OfficerRef {
  id: string;
  name: string;
}

export interface ResidentSummary {
  id: string;
  name: string;
  gender?: ResidentGender;
  status?: ResidentStatus;
}

export interface ResidencyMovement {
  id: string;
  representation: PrivacyTier;
  resident_id: string;
  movement_type: ResidencyMovementType;
  effective_date: string;
  from_scope: ScopeRefSummary;
  to_scope: ScopeRefSummary;
  created_at: string;
  // Sensitive fields (present only when authorized with resident.view_sensitive)
  reference_letter_number?: string | null;
  notes?: string | null;
  attachment_file_id?: string | null;
  recorded_by?: OfficerRef | null;
}

export interface ResidentStatusHistory {
  id: string;
  representation: PrivacyTier;
  resident_id: string;
  previous_status: ResidentStatus;
  new_status: ResidentStatus;
  effective_at: string;
  created_at: string;
  // Sensitive fields (present only when authorized with resident.view_sensitive)
  reason?: string | null;
  reference_letter_number?: string | null;
  attachment_file_id?: string | null;
  recorded_by?: OfficerRef | null;
}

/**
 * Resident representation from API.
 * Fields vary based on server-authorized PrivacyTier (directory | normal | sensitive).
 * Masked values (e.g. 320101******0005) are valid string representations.
 */
export interface Resident {
  id: string;
  representation: PrivacyTier;
  name: string;
  gender: ResidentGender;
  age: number;
  residency_type: ResidencyType;
  status: ResidentStatus;
  organization_id: string;
  rw_id: string;
  rt_id: string;

  // Normal tier fields (masked when privacy tier is normal)
  birth_place?: string;
  birth_date?: string;
  marital_status?: ResidentMaritalStatus;
  occupation?: string | null;
  education?: ResidentEducation | null;
  current_household_id?: string | null;
  nik?: string;
  nik_masked?: boolean;
  phone?: string | null;
  phone_masked?: boolean;
  created_at?: string;
  updated_at?: string;

  // Sensitive tier fields (present only when authorized with resident.view_sensitive)
  religion?: string;
  blood_type?: ResidentBloodType | null;
  user_id?: string | null;

  // Optional included relations
  movements?: ResidencyMovement[];
  status_history?: ResidentStatusHistory[];
}

export type ResidentIncludeToken =
  | 'organization'
  | 'rw'
  | 'rt'
  | 'household'
  | 'memberships'
  | 'movements'
  | 'status_history';

export interface ResidentQueryParams {
  page?: number;
  per_page?: number;
  q?: string;
  sort?: 'name' | '-name' | 'created_at' | '-created_at' | 'status' | '-status';
  status?: ResidentStatus;
  rt_id?: string;
  rw_id?: string;
  household_id?: string;
  gender?: ResidentGender;
  residency_type?: ResidencyType;
  marital_status?: ResidentMaritalStatus;
  include?: ResidentIncludeToken[] | string;
}

export interface ResidentListResponseData {
  data: Resident[];
  meta: {
    pagination: PaginationMeta;
    request_id?: string;
  };
}

// -------------------------------------------------------------
// Mutation Payloads
// Strictly omitting forbidden fields:
// organization_id, rw_id, status, user_id, current_household_id, from_*, recorded_by
// -------------------------------------------------------------

export interface RegisterResidentPayload {
  nik: string;
  name: string;
  gender: ResidentGender;
  birth_place: string;
  birth_date: string;
  religion: string;
  marital_status: ResidentMaritalStatus;
  rt_id: string;
  blood_type?: ResidentBloodType;
  occupation?: string;
  education?: ResidentEducation;
  phone?: string;
  residency_type: ResidencyType;
}

export interface UpdateResidentProfilePayload {
  occupation?: string;
  education?: ResidentEducation;
  phone?: string;
  marital_status?: ResidentMaritalStatus;
}

export interface VerifyResidentPayload {
  notes?: string;
  reference_letter_number?: string;
  attachment_file_id?: string;
}

export interface RelocateResidentPayload {
  movement_type: ResidencyMovementType; // 'TRANSFER_INTERNAL' | 'MOVE_OUT_EXTERNAL' | 'HOUSEHOLD_SPLIT'
  target_rt_id?: string;
  target_household_id?: string;
  effective_date?: string;
  reference_letter_number?: string;
  attachment_file_id?: string;
  notes?: string;
}

export interface ChangeResidentStatusPayload {
  intent: ResidentStatusIntent;
  reason?: string;
  reference_letter_number?: string;
  attachment_file_id?: string;
  effective_at?: string;
}

export interface RemoveResidentFromHouseholdPayload {
  left_reason?: string;
  left_at?: string;
}
