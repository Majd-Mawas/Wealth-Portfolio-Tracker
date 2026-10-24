import { apiClient, ensureCsrfCookie } from './api-client'
import type {
  Asset, AssetInput, CursorCollection, Dashboard, Membership, Portfolio, PortfolioInput,
  Resource, Role, Transaction, TransactionInput, User,
} from './api-types'

const v1 = '/api/v1'
const query = (values: Record<string, string | number | undefined | null>) => {
  const params = new URLSearchParams()
  Object.entries(values).forEach(([key, value]) => { if (value !== undefined && value !== null && value !== '') params.set(key, String(value)) })
  const string = params.toString()
  return string ? `?${string}` : ''
}

export const authApi = {
  user: () => apiClient.get<Resource<User>>(`${v1}/user`),
  login: async (input: { email: string; password: string; remember?: boolean }) => {
    await ensureCsrfCookie()
    return apiClient.post<Resource<User>>(`${v1}/auth/login`, input)
  },
  register: async (input: { name: string; email: string; password: string; password_confirmation: string }) => {
    await ensureCsrfCookie()
    return apiClient.post<Resource<User>>(`${v1}/auth/register`, input)
  },
  logout: () => apiClient.post<void>(`${v1}/auth/logout`),
}

export const portfoliosApi = {
  list: (cursor?: string | null) => apiClient.get<CursorCollection<Portfolio>>(`${v1}/portfolios${query({ cursor })}`),
  get: (id: number) => apiClient.get<Resource<Portfolio>>(`${v1}/portfolios/${id}`),
  create: (input: PortfolioInput) => apiClient.post<Resource<Portfolio>>(`${v1}/portfolios`, input),
  update: (id: number, input: Partial<PortfolioInput>) => apiClient.patch<Resource<Portfolio>>(`${v1}/portfolios/${id}`, input),
  delete: (id: number) => apiClient.delete<void>(`${v1}/portfolios/${id}`),
  dashboard: (id: number) => apiClient.get<Resource<Dashboard>>(`${v1}/portfolios/${id}/dashboard`),
}

export const assetsApi = {
  list: (portfolioId: number, params: { cursor?: string | null; type?: string; include_archived?: string } = {}) =>
    apiClient.get<CursorCollection<Asset>>(`${v1}/portfolios/${portfolioId}/assets${query(params)}`),
  create: (portfolioId: number, input: AssetInput) => apiClient.post<Resource<Asset>>(`${v1}/portfolios/${portfolioId}/assets`, input),
  update: (portfolioId: number, id: number, input: Partial<AssetInput>) => apiClient.patch<Resource<Asset>>(`${v1}/portfolios/${portfolioId}/assets/${id}`, input),
  archive: (portfolioId: number, id: number) => apiClient.delete<void>(`${v1}/portfolios/${portfolioId}/assets/${id}`),
}

export const transactionsApi = {
  list: (portfolioId: number, params: { cursor?: string | null; asset_id?: number; kind?: string; occurred_from?: string; occurred_to?: string } = {}) =>
    apiClient.get<CursorCollection<Transaction>>(`${v1}/portfolios/${portfolioId}/transactions${query(params)}`),
  create: (portfolioId: number, assetId: number, input: TransactionInput) => apiClient.post<Resource<Transaction>>(`${v1}/portfolios/${portfolioId}/assets/${assetId}/transactions`, input),
  reverse: (portfolioId: number, id: number, reason: string) => apiClient.post<Resource<Transaction>>(`${v1}/portfolios/${portfolioId}/transactions/${id}/reverse`, { reason }),
}

export const membersApi = {
  list: (portfolioId: number, cursor?: string | null) => apiClient.get<CursorCollection<Membership>>(`${v1}/portfolios/${portfolioId}/members${query({ cursor })}`),
  create: (portfolioId: number, input: { email: string; role: Role }) => apiClient.post<Resource<Membership>>(`${v1}/portfolios/${portfolioId}/members`, input),
  update: (portfolioId: number, id: number, role: Role) => apiClient.patch<Resource<Membership>>(`${v1}/portfolios/${portfolioId}/members/${id}`, { role }),
  delete: (portfolioId: number, id: number) => apiClient.delete<void>(`${v1}/portfolios/${portfolioId}/members/${id}`),
}
