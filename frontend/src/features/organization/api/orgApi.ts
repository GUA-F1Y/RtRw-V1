import { api } from '../../../lib/api/client';
import type { Membership } from '../../../types/auth';
import type { Organization, RtUnit, RwUnit } from '../../../types/organization';

export const orgApi = {
  getOrganizations: async (): Promise<Organization[]> => {
    const res = await api.get<Organization[]>('organizations');
    return res.data;
  },

  getOrganization: async (id: string): Promise<Organization> => {
    const res = await api.get<Organization>(`organizations/${id}`);
    return res.data;
  },

  getRws: async (organizationId: string): Promise<RwUnit[]> => {
    const res = await api.get<RwUnit[]>(`organizations/${organizationId}/rws`);
    return res.data;
  },

  getRw: async (id: string): Promise<RwUnit> => {
    const res = await api.get<RwUnit>(`rws/${id}`);
    return res.data;
  },

  getRts: async (rwId: string): Promise<RtUnit[]> => {
    const res = await api.get<RtUnit[]>(`rws/${rwId}/rts`);
    return res.data;
  },

  getMemberships: async (): Promise<Membership[]> => {
    const res = await api.get<Membership[]>('memberships');
    return res.data;
  },
};
