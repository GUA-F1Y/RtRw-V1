export interface Organization {
  id: string;
  name: string;
  slug: string;
  rws_count?: number;
  created_at: string;
}

export interface RwUnit {
  id: string;
  organization_id: string;
  number: number;
  name: string;
  status: 'active' | 'inactive';
  rts_count?: number;
  rts?: RtUnit[];
  created_at: string;
}

export interface RtUnit {
  id: string;
  rw_id: string;
  number: number;
  name: string;
  status: 'active' | 'inactive';
  created_at: string;
}
