import { z } from 'zod';

export const PaginationMetaSchema = z.object({
  current_page: z.number().int().min(1),
  per_page: z.number().int().min(1),
  total: z.number().int().min(0),
  last_page: z.number().int().min(1),
  has_more: z.boolean(),
});

export const ApiMetaSchema = z.object({
  request_id: z.string().optional(),
  pagination: PaginationMetaSchema.optional(),
}).passthrough();

export const createSuccessResponseSchema = <T extends z.ZodTypeAny>(dataSchema: T) =>
  z.object({
    data: dataSchema,
    meta: ApiMetaSchema.optional(),
  });

export const ApiErrorDetailSchema = z.object({
  code: z.string(),
  message: z.string(),
  request_id: z.string().optional(),
  errors: z.record(z.string(), z.array(z.string())).optional(),
});

export const ApiErrorResponseSchema = z.object({
  error: ApiErrorDetailSchema,
});

// Enums for runtime parsing
export const ResidentGenderSchema = z.enum(['MALE', 'FEMALE']);
export const ResidentStatusSchema = z.enum(['PROSPECTIVE', 'ACTIVE', 'INACTIVE', 'MOVED', 'DECEASED']);
export const ResidencyTypeSchema = z.enum(['PERMANENT', 'TEMPORARY', 'SEASONAL']);
export const ResidentMaritalStatusSchema = z.enum(['SINGLE', 'MARRIED', 'DIVORCED', 'WIDOWED']);
export const ResidentBloodTypeSchema = z.enum(['A', 'B', 'AB', 'O']);
export const ResidentEducationSchema = z.enum(['SD', 'SMP', 'SMA', 'DIPLOMA', 'SARJANA', 'MAGISTER', 'DOKTOR', 'OTHER']);
export const PrivacyTierSchema = z.enum(['directory', 'normal', 'sensitive']);

export const HouseholdStatusSchema = z.enum(['ACTIVE', 'HEAD_VACANT', 'MOVED', 'DISSOLVED', 'ARCHIVED']);
export const HouseholdMemberRelationshipSchema = z.enum([
  'KEPALA_KELUARGA',
  'SUAMI',
  'ISTRI',
  'ANAK',
  'MENANTU',
  'CUCU',
  'ORANG_TUA',
  'MERTUA',
  'FAMILI_LAIN',
  'PEMBANTU',
  'LAINNYA',
]);

export const OverviewScopeSchema = z.object({
  level: z.string(),
  id: z.string(),
  name: z.string(),
});

export const OverviewPopulationMetricsSchema = z.object({
  total: z.number().int().min(0),
  active: z.number().int().min(0),
  prospective: z.number().int().min(0),
  inactive: z.number().int().min(0),
  moved: z.number().int().min(0),
  deceased: z.number().int().min(0),
});

export const OverviewHouseholdMetricsSchema = z.object({
  active: z.number().int().min(0),
  head_assigned: z.number().int().min(0),
  head_vacant: z.number().int().min(0),
  moved: z.number().int().min(0),
  dissolved: z.number().int().min(0),
});

export const RtOverviewMetricSchema = z.object({
  rt_id: z.string(),
  rt_code: z.string(),
  resident_count: z.number().int().min(0),
  household_count: z.number().int().min(0),
  head_vacant_household_count: z.number().int().min(0),
});

export const OverviewSchema = z.object({
  scope: OverviewScopeSchema,
  population: OverviewPopulationMetricsSchema,
  households: OverviewHouseholdMetricsSchema,
  rt_breakdown: z.array(RtOverviewMetricSchema),
});

export const OverviewResponseSchema = createSuccessResponseSchema(OverviewSchema);
