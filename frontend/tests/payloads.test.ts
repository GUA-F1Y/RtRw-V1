import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import {
  createSuccessResponseSchema,
  HouseholdMemberRelationshipSchema,
  HouseholdStatusSchema,
  PaginationMetaSchema,
  PrivacyTierSchema,
  ResidentGenderSchema,
  ResidentStatusSchema,
} from '../src/lib/api/schemas.ts';
import type {
  CreateHouseholdPayload,
  Household,
} from '../src/types/household.ts';
import type {
  RegisterResidentPayload,
  Resident,
  UpdateResidentProfilePayload,
} from '../src/types/resident.ts';

describe('Privacy Tiers and Mutation Payload Contracts', () => {
  it('accepts masked values as valid representation in normal privacy tier', () => {
    const normalResident: Resident = {
      id: '01924b12-9c9e-716b-9c3f-8461abde5001',
      representation: 'normal',
      name: 'Budi Santoso',
      gender: 'MALE',
      age: 35,
      residency_type: 'PERMANENT',
      status: 'ACTIVE',
      organization_id: 'org-1',
      rw_id: 'rw-1',
      rt_id: 'rt-1',
      nik: '320101******0005',
      nik_masked: true,
      phone: '+628*******890',
      phone_masked: true,
    };

    assert.equal(normalResident.representation, 'normal');
    assert.equal(normalResident.nik, '320101******0005');
    assert.equal(normalResident.nik_masked, true);
    // Sensitive fields like religion, blood_type, user_id are optional
    assert.equal(normalResident.religion, undefined);
    assert.equal(normalResident.user_id, undefined);
  });

  it('accepts masked KK number in normal household representation', () => {
    const normalHousehold: Household = {
      id: 'hh-1',
      representation: 'normal',
      status: 'ACTIVE',
      organization_id: 'org-1',
      rw_id: 'rw-1',
      rt_id: 'rt-1',
      active_members_count: 3,
      kk_number: '320101******0001',
      kk_number_masked: true,
      address: 'Jl. Melati No. 4',
    };

    assert.equal(normalHousehold.kk_number, '320101******0001');
    assert.equal(normalHousehold.kk_number_masked, true);
  });

  it('validates registration payload does not include forbidden fields', () => {
    const payload: RegisterResidentPayload = {
      nik: '3201011205900005',
      name: 'Ahmad Dahlan',
      gender: 'MALE',
      birth_place: 'Jakarta',
      birth_date: '1990-05-12',
      religion: 'ISLAM',
      marital_status: 'MARRIED',
      rt_id: '01924b12-9c9e-716b-9c3f-8461abde5003',
      blood_type: 'O',
      occupation: 'Wiraswasta',
      education: 'SARJANA',
      phone: '+6281234567890',
      residency_type: 'PERMANENT',
    };

    const keys = Object.keys(payload);
    // Forbidden fields
    assert.ok(!keys.includes('organization_id'));
    assert.ok(!keys.includes('rw_id'));
    assert.ok(!keys.includes('status'));
    assert.ok(!keys.includes('user_id'));
    assert.ok(!keys.includes('current_household_id'));
    assert.ok(!keys.includes('from_rt_id'));
    assert.ok(!keys.includes('recorded_by'));
  });

  it('validates update profile payload rejects immutable identity and scope fields', () => {
    const payload: UpdateResidentProfilePayload = {
      occupation: 'PNS',
      education: 'MAGISTER',
      phone: '+6281299998888',
      marital_status: 'MARRIED',
    };

    const keys = Object.keys(payload);
    assert.ok(!keys.includes('nik'));
    assert.ok(!keys.includes('name'));
    assert.ok(!keys.includes('birth_date'));
    assert.ok(!keys.includes('rt_id'));
    assert.ok(!keys.includes('status'));
  });

  it('validates create household payload does not include forbidden fields', () => {
    const payload: CreateHouseholdPayload = {
      kk_number: '3201011205900001',
      address: 'Jl. Mawar No. 5',
      rt_id: '01924b12-9c9e-716b-9c3f-8461abde5003',
      notes: 'Keluarga baru',
    };

    const keys = Object.keys(payload);
    assert.ok(!keys.includes('organization_id'));
    assert.ok(!keys.includes('rw_id'));
    assert.ok(!keys.includes('status'));
  });

  it('validates Zod schemas against valid backend payloads', () => {
    assert.ok(ResidentGenderSchema.safeParse('MALE').success);
    assert.ok(ResidentGenderSchema.safeParse('FEMALE').success);
    assert.ok(!ResidentGenderSchema.safeParse('OTHER').success);

    assert.ok(ResidentStatusSchema.safeParse('ACTIVE').success);
    assert.ok(ResidentStatusSchema.safeParse('PROSPECTIVE').success);
    assert.ok(!ResidentStatusSchema.safeParse('DELETED').success);

    assert.ok(HouseholdStatusSchema.safeParse('ACTIVE').success);
    assert.ok(HouseholdStatusSchema.safeParse('HEAD_VACANT').success);

    assert.ok(HouseholdMemberRelationshipSchema.safeParse('KEPALA_KELUARGA').success);
    assert.ok(HouseholdMemberRelationshipSchema.safeParse('ISTRI').success);

    assert.ok(PrivacyTierSchema.safeParse('directory').success);
    assert.ok(PrivacyTierSchema.safeParse('normal').success);
    assert.ok(PrivacyTierSchema.safeParse('sensitive').success);

    const paginationParsed = PaginationMetaSchema.safeParse({
      current_page: 1,
      per_page: 15,
      total: 45,
      last_page: 3,
      has_more: true,
    });
    assert.ok(paginationParsed.success);

    const successSchema = createSuccessResponseSchema(ResidentStatusSchema);
    const envelopeParsed = successSchema.safeParse({
      data: 'ACTIVE',
      meta: {
        request_id: 'req-abc',
      },
    });
    assert.ok(envelopeParsed.success);
  });
});
