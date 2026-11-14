export interface PaginationMeta {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
  has_more: boolean;
}

export interface ApiMeta {
  request_id?: string;
  pagination?: PaginationMeta;
  [key: string]: unknown;
}

export interface ApiSuccessResponse<T> {
  data: T;
  meta?: ApiMeta;
}

export interface ApiErrorDetail {
  code: string;
  message: string;
  request_id?: string;
  errors?: Record<string, string[]>;
}

export interface ApiErrorResponse {
  error: ApiErrorDetail;
}

export interface RequestOptions extends Omit<RequestInit, 'body'> {
  body?: unknown;
  idempotencyKey?: string;
  params?: Record<string, unknown>;
  signal?: AbortSignal;
}
