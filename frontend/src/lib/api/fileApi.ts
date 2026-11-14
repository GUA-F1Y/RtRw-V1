import { API_BASE_URL, apiClient, getAuthToken } from './client.ts';
import { parseApiError } from './errors.ts';
import type { ApiSuccessResponse } from './types.ts';

export interface StoredFileMeta {
  id: string;
  original_name: string;
  extension: string;
  mime_type: string;
  size_bytes: number;
  visibility?: 'private' | 'public';
  category?: string | null;
  rw_id?: string | null;
  rt_id?: string | null;
  created_at?: string;
}

export interface UploadFileParams {
  file: File;
  scopeType: 'organization' | 'rw' | 'rt';
  scopeId: string;
  visibility?: 'private' | 'public';
  category?: string;
  signal?: AbortSignal;
}

export const fileApi = {
  /**
   * Upload an authorized document or evidence file.
   * POST /api/v1/files
   */
  uploadFile: async (params: UploadFileParams): Promise<ApiSuccessResponse<StoredFileMeta>> => {
    const formData = new FormData();
    formData.append('file', params.file);
    formData.append('scope_type', params.scopeType);
    formData.append('scope_id', params.scopeId);
    if (params.visibility) {
      formData.append('visibility', params.visibility);
    }
    if (params.category) {
      formData.append('category', params.category);
    }

    return apiClient.post<StoredFileMeta>('files', formData, {
      signal: params.signal,
    });
  },

  /**
   * Download a stored file with sanitized filename and authorization.
   * GET /api/v1/files/{id}/download
   */
  downloadFile: async (
    fileId: string,
    options?: { signal?: AbortSignal }
  ): Promise<{ blob: Blob; filename: string }> => {
    const url = `${API_BASE_URL}/files/${encodeURIComponent(fileId)}/download`;
    const headers = new Headers();
    const token = getAuthToken();
    if (token) {
      headers.set('Authorization', `Bearer ${token}`);
    }

    const res = await fetch(url, { headers, signal: options?.signal });
    if (!res.ok) {
      const err = await parseApiError(res);
      throw err;
    }

    const contentDisposition = res.headers.get('content-disposition') || '';
    const filenameMatch = contentDisposition.match(/filename="?([^"]+)"?/);
    const filename = filenameMatch ? filenameMatch[1] : 'unduhan';
    const blob = await res.blob();

    return { blob, filename };
  },
};
