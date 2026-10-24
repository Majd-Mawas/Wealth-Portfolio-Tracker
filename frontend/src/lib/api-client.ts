import type { ApiErrorBody } from './api-types'

const API_ORIGIN = (import.meta.env.VITE_API_URL as string | undefined)?.replace(/\/$/, '') ?? 'http://localhost:8000'

export class ApiError extends Error {
  constructor(
    message: string,
    public status: number,
    public code = 'request_failed',
    public fields: Record<string, string[]> = {},
    public requestId?: string,
  ) {
    super(message)
  }
}

function cookie(name: string) {
  const value = document.cookie.split('; ').find((part) => part.startsWith(`${name}=`))?.split('=')[1]
  return value ? decodeURIComponent(value) : undefined
}

async function parseError(response: Response) {
  let body: ApiErrorBody | undefined
  try { body = await response.json() as ApiErrorBody } catch { /* non-JSON server response */ }
  return new ApiError(
    body?.error?.message ?? 'The request could not be completed.',
    response.status,
    body?.error?.code,
    body?.error?.fields,
    body?.error?.request_id,
  )
}

export async function ensureCsrfCookie() {
  const response = await fetch(`${API_ORIGIN}/sanctum/csrf-cookie`, {
    credentials: 'include',
    headers: { Accept: 'application/json' },
  })
  if (!response.ok) throw await parseError(response)
}

export async function request<T>(path: string, init: RequestInit = {}): Promise<T> {
  const method = init.method?.toUpperCase() ?? 'GET'
  const headers = new Headers(init.headers)
  headers.set('Accept', 'application/json')
  if (init.body) headers.set('Content-Type', 'application/json')
  if (!['GET', 'HEAD'].includes(method)) {
    const token = cookie('XSRF-TOKEN')
    if (token) headers.set('X-XSRF-TOKEN', token)
  }

  const response = await fetch(`${API_ORIGIN}${path}`, { ...init, headers, credentials: 'include' })
  if (!response.ok) throw await parseError(response)
  if (response.status === 204) return undefined as T
  return response.json() as Promise<T>
}

export const apiClient = {
  get: <T>(path: string) => request<T>(path),
  post: <T>(path: string, body?: unknown) => request<T>(path, { method: 'POST', body: body === undefined ? undefined : JSON.stringify(body) }),
  patch: <T>(path: string, body?: unknown) => request<T>(path, { method: 'PATCH', body: body === undefined ? undefined : JSON.stringify(body) }),
  delete: <T>(path: string) => request<T>(path, { method: 'DELETE' }),
}

export function errorMessage(error: unknown) {
  return error instanceof ApiError ? error.message : 'Something went wrong. Please try again.'
}
